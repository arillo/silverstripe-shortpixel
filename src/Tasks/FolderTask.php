<?php
namespace Arillo\Shortpixel\Tasks;

use Psr\Log\LoggerInterface;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\Debug;
use SilverStripe\Assets\File;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Core\Environment;
use Arillo\Shortpixel\Shortpixel;
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

    private static $dependencies = [
        'logger' => '%$' . LoggerInterface::class,
    ];


    /**
     * Specify folder names to exclude
     * @var array
     */
    private static $exclude_folders = [];

    protected $title = 'Shortpixel folder task';
    protected $description = 'Run short pixel optimization on a given folder.';

    private $logger;

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

        $this->log("Processing images in {$rootFolder}");

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
            $this->log("ERROR: " . $e->getMessage());
            die;
        }

        // \SilverStripe\Dev\Debug::dump($result);

        $this->log("Status code: {$result->status['code']}");
        $this->log("Status message: {$result->status['message']}");
        $this->log("Succeeded: " . count($result->succeeded));
        $this->log("Pending: " . count($result->pending));
        $this->log("Failed: " . count($result->failed));
        $this->log("Same: " . count($result->same));

        $manipulatedFiles = array_merge($result->succeeded, $result->pending);

        // fix manipulated files in assets store
        if (count($manipulatedFiles)) {
            sleep(4);
            foreach ($manipulatedFiles as $fileResult) {
                $processedFilename = $fileResult->OriginalFile;
                $processedFilename = substr($processedFilename, strlen($rootFolder) + 1);

                $this->log("Check needs fix assets store: {$processedFilename}");
                $recover = Shortpixel::fix_assetsstore_file($processedFilename);

                switch ($recover) {
                    case Shortpixel::FILE_RECOVERED:
                        $this->log("Fixed assets store: {$processedFilename}");
                        break;

                    default:
                        $this->log("Skipped: {$processedFilename}, {$recover}");
                        break;
                }
            }
        }
        
        $this->log("Done");
    }

    /**
     * @param  string $message
     * @return FolderTask
     */
    public function log($message, $method = 'notice')
    {
        switch (true) {
             case Environment::isCli():
                 $this->logger->{$method}('[shortpixel.foldertask] ' . $message);
                 break;

             default:
                 Debug::message($message, true);
                 break;
         }

         return $this;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }

}
