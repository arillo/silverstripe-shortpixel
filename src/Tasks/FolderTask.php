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
        'WAIT' => 500,
    ];

    private static $dependencies = [
        'logger' => '%$' . LoggerInterface::class,
    ];

    /**
     * Specify folder names to exclude
     * @var array
     */
    private static $exclude_folders = [];

    /**
     * Turn on simple image recovering / re-hashing
     * @var bool
     */
    private static $use_simple_image_recovering = true;

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

    /**
     * Run it!
     * @param mixed     $request
     */
    public function run($request)
    {
        set_time_limit(0);
        $startTime = time();

        $apiKey = Environment::getEnv('SP_APIKEY');

        if (empty($apiKey)) user_error("Env 'SP_APIKEY' not set");

        $sc = SiteConfig::current_site_config();
        $rootFolder = $this->rootFolder();

        $this->log("Processing images in {$rootFolder}");

        // run recovering on previously processed images, if enabled.
        if ($this->config()->use_simple_image_recovering) {
            $this->log("Check: needs recover images from previous run...");
            $revoverFromPreviousRun = json_decode($sc->ShortpixelFolderTaskLastImages);
            if ($revoverFromPreviousRun && count($revoverFromPreviousRun)) {
                $this->log("Recovering images from previous run.");
                // Debug::dump(json_decode($sc->ShortpixelFolderTaskLastImages));
                $this->recoverImages($revoverFromPreviousRun, $rootFolder);
                $sc->update([
                    'ShortpixelFolderTaskLastImages' => null
                ])->write();
            } else {
                $this->log("Nothing to recover.");
            }
        }

        \ShortPixel\setKey($apiKey);
        \ShortPixel\ShortPixel::setOptions($this->config()->shortpixel_options);

        $this->extend('beforeShortPixelCall');

        $this->log("Run Shortpixel call...");

        // run shortpixel from folder API call.
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

        // log some stats
        $this->log("Status code: {$result->status['code']}");
        $this->log("Status message: {$result->status['message']}");
        $this->log("Succeeded: " . count($result->succeeded));
        $this->log("Pending: " . count($result->pending));
        $this->log("Failed: " . count($result->failed));
        $this->log("Same: " . count($result->same));

        $this->extend('afterShortPixelCall', $result);

        // merge succeded & pendign images
        $manipulatedFiles = array_merge($result->succeeded, $result->pending);

        // write stats into siteconfig, for next run for recovering purpose.
        $sc->update([
            'ShortpixelFolderTaskLastImages' => json_encode($manipulatedFiles)
        ])
        ->write();

        // fix manipulated files in assets store
        if (count($manipulatedFiles)) {
            sleep(4);
            if ($this->config()->use_simple_image_recovering) {
                $this->recoverImages($manipulatedFiles, $rootFolder);
            }
        }

        $duration = time() - $startTime;
        $this->log("Done after {$duration} seconds.");
    }

    /**
     * Recover images by regeneration the file hash
     * @param  array $manipulatedFiles
     * @param  string $rootFolder
     * @return FolderTask
     */
    public function recoverImages(
        array $manipulatedFiles,
        string $rootFolder
    ) {
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

        return $this;
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
     * @return FolderTask
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }
}
