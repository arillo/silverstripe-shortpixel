<?php
namespace Arillo\Shortpixel\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\Debug;
use SilverStripe\Assets\File;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Core\Environment;
use Exception;

/**
 * Runs short pixel optimization on a given folder.
 * It processes MAX_ALLOWED_FILES_PER_CALL per run.
 * Purposed to run via cronjob.
 * 
 * @author Bumbus <sf@arillo.ch>
 */
class FolderTask extends BuildTask
{
    private static $segment = 'ShortpixelFolderTask';

    /**
     * Shortpixel options can be specified here @see ShortPixel\ShortPixel::$options:
     * @var array
     */
    private static $shortpixel_options = [
        'persist_type' => 'text'
    ];

    /**
     * Options for \ShortPixel::fromFolder API call.
     * @var array
     */
    private static $shortpixel_settings = [
        'MAX_ALLOWED_FILES_PER_CALL' => 10,
        'CLIENT_MAX_BODY_SIZE' => 48,
        'WAIT' => 300,
    ];

    /**
     * Specify folder names to exclude
     * @var array
     */
    private static $exclude_folders = [];

    protected $title = 'Shortpixel folder task';
    protected $description = 'Run short pixel optimization on a given folder.';

    /**
     * Root folder can be specified by setting, otherwise it will fallback to `ASSETS_PATH`:
     *     private static $root_folder = "<ABSOLUTE_FOLDER_PATH>";
     *     
     * @return string
     */
    public function rootFolder()
    {
        return $this->config()->root_folder ?? ASSETS_PATH;
    }

    public function isEnabled()
    {
        return parent::isEnabled() && !SiteConfig::current_site_config()->ShortpixelFolderTaskDisabled;
    }

    public function run($request)
    {
        set_time_limit(0);
        $apiKey = Environment::getEnv('SP_APIKEY');

        if (empty($apiKey)) user_error("Env 'SP_APIKEY' not set");

        $rootFolder = $this->rootFolder();

        Debug::message("Processing images in {$rootFolder}", true);

        \ShortPixel\setKey($apiKey);
        \ShortPixel\ShortPixel::setOptions($this->config()->shortpixel_options);

        try {
            $result = \ShortPixel\fromFolder(
                $rootFolder,
                $this->config()->shortpixel_settings['MAX_ALLOWED_FILES_PER_CALL'],
                $this->config()->exclude_folders,
                false,
                $this->config()->shortpixel_settings['CLIENT_MAX_BODY_SIZE']
            )
                ->wait($this->config()->shortpixel_settings['CLIENT_MAX_BODY_SIZE'])
                ->toFiles($rootFolder)
            ;
        } catch (Exception | \ShortPixel\AccountException $e) {
            Debug::message("ERROR: " . $e->getMessage(), true);
            die;
        }

        // \SilverStripe\Dev\Debug::dump($result);

        Debug::message("Status code: {$result->status['code']}", true);
        Debug::message("Status message: {$result->status['message']}", true);
        Debug::message("Succeeded: " . count($result->succeeded), true);
        Debug::message("Pending: " . count($result->pending), true);
        Debug::message("Failed: " . count($result->failed), true);
        Debug::message("Same: " . count($result->same), true);

        // fix manipulated files in assets store
        if (count($result->succeeded)) {
            foreach ($result->succeeded as $fileResult) {
                $processedFilename = $fileResult->OriginalFile;
                $processedFilename = substr($processedFilename, strlen($rootFolder) + 1);

                Debug::message("Check needs fix assetstore: {$processedFilename}", true);
                $file = File::get()->filter('FileFilename', $processedFilename)->limit(1);
                if ($file->exists()) {
                    Debug::message("Fixing assetstore: {$processedFilename}", true);
                    $file = $file->first();
                    $file->File->setFromLocalFile(
                        ASSETS_PATH . '/' . $file->FileFilename,
                        $file->generateFilename()
                    );
                    $file->write();
                    if ($file->isPublished()) $file->publishRecursive();
                } else {
                    Debug::message("Skipping: {$processedFilename}", true);
                }
            }
        }
        
        Debug::message("Done", true);
    }
}
