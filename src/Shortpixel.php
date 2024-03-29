<?php
namespace Arillo\Shortpixel;

use SilverStripe\Core\Environment;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Assets\File;
use SilverStripe\Assets\FilenameParsing\HashFileIDHelper;
use SilverStripe\AssetAdmin\Controller\AssetAdmin;
use SilverStripe\Core\Config\{Config, Configurable};

use Exception;

/**
 * @author Bumbus <sf@arillo.ch>
 */
class Shortpixel
{
    use Configurable;

    const ENV_APIKEY = 'SP_APIKEY';
    const FILE_HEALTHY = 'healthy';
    const FILE_RECOVERED = 'recovered';
    const FILE_NOT_FOUND = 'file_not_found';

    /**
     * Shortpixel options can be specified here @see ShortPixel\ShortPixel::$options:
     * @var array
     */
    private static $options = [
        'persist_type' => 'text',
        'lossy' => 0, // 1 - lossy, 2 - glossy, 0 - lossless
    ];

    public static function init($options = null)
    {
        $apiKey = Environment::getEnv(self::ENV_APIKEY);

        if (empty($apiKey)) user_error("Env '" . self::ENV_APIKEY . "' not set");

        \ShortPixel\setKey($apiKey);
        \ShortPixel\ShortPixel::setOptions(
            $options ?? Config::inst()->get(__CLASS__, 'options')
        );
    }

    /**
     * Request api status for your apy key.
     * @return array
     */
    public static function get_api_status()
    {
        $apiKey = Environment::getEnv(self::ENV_APIKEY);
        if (empty($apiKey)) user_error("Env '" . self::ENV_APIKEY . "' not set");

        \ShortPixel\setKey($apiKey);

        try {
            $result = \ShortPixel\ShortPixel::getClient()->apiStatus($apiKey);
            if (count($result)) return $result[0];
        } catch (Exception | \ShortPixel\AccountException $e) {
            return [
                'Error' => $e->getMessage()
            ];
        }

        return [
            "Error" => 'Something went wrong'
        ];
    }

    /**
     * Fix assetsstore file, re-generates file hashes,
     * otherwise images seem to be broken in assets admin.
     *
     * @param  string $fileName
     * @return string
     */
    public static function fix_assetsstore_file(string $fileName)
    {
        $searchFilename = $fileName;

        // parse hash filename
        if ($parsed = (new HashFileIDHelper())->parseFileID($fileName)) {
            $searchFilename = $parsed->getFilename();
        }

        $files = File::get()->filter('FileFilename', $searchFilename)->limit(1);

        $filePath = ASSETS_PATH . '/' . $fileName;
        if ($files->exists()) {
            $file = $files->first();
            if (!$file->File->exists() && file_exists($filePath)) {
                $file->File->setFromLocalFile(
                    $filePath,
                    $file->FileFilename
                    // $file->generateFilename()
                );
                $file->write();
                AssetAdmin::singleton()->generateThumbnails($file);
                if ($file->isPublished()) $file->publishRecursive();
                return self::FILE_RECOVERED;
            }
            return self::FILE_HEALTHY;
        }
        return self::FILE_NOT_FOUND;
    }
}
