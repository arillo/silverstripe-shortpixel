<?php
namespace Arillo\Shortpixel\Tasks;

use Psr\Log\LoggerInterface;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\Debug;
use SilverStripe\Assets\File;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Config\Config;
use Arillo\Shortpixel\Shortpixel;
use Exception;

/**
 * Runs short pixel optimization on a given folder.
 * Purposed to run via cronjob.
 *
 * @author Bumbus <sf@arillo.ch>
 */
class FolderTask extends BuildTask
{
    private static $segment = 'ShortpixelFolderTask';

    /**
     * Options for \ShortPixel::fromFolder API call.
     * @var array
     */
    private static $shortpixel_settings = [
        'max_allowed_files_per_call' => 10,
        'client_max_body_size' => 48,
        'wait' => 500,
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
    public static function root_folder()
    {
        return Config::inst()->get(__CLASS__, 'root_folder') ?? ASSETS_PATH;
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

        Shortpixel::init();

        $startTime = time();
        $sc = SiteConfig::current_site_config();
        $rootFolder = self::root_folder();

        $this->log("Processing images in {$rootFolder}");

        // run recovering on previously processed images, if enabled.
        if ($this->config()->use_simple_image_recovering) {
            $this->log("Check: needs recover images from previous run...");
            $recoverFromPreviousRun = json_decode($sc->ShortpixelFolderTaskLastImages);
            if ($recoverFromPreviousRun && count($recoverFromPreviousRun)) {
                $this->log("Recovering images from previous run.");
                $this->recoverImages($recoverFromPreviousRun, $rootFolder);

                // reset store
                $sc->update([
                    'ShortpixelFolderTaskLastImages' => null
                ])->write();

            } else {
                $this->log("Nothing to recover.");
            }
        }


        $this->extend('beforeShortPixelCall');

        $this->log("Run Shortpixel call...");
        // run shortpixel from folder API call.
        try {
            $result = \ShortPixel\fromFolder(
                $rootFolder,
                $this->config()->shortpixel_settings['max_allowed_files_per_call'],
                $this->config()->exclude_folders,
                false,
                $this->config()->shortpixel_settings['client_max_body_size']
            )
                ->wait($this->config()->shortpixel_settings['wait'])
                ->toFiles($rootFolder)
            ;
        } catch (Exception | \ShortPixel\AccountException $e) {
            $this->log("ERROR: " . $e->getMessage());
            die;
        }

        // log some stats
        $this
            ->log("Status code: {$result->status['code']}")
            ->log("Status message: {$result->status['message']}")
            ->log("Succeeded: " . count($result->succeeded))
            ->log("Pending: " . count($result->pending))
            ->log("Failed: " . count($result->failed))
            ->log("Same: " . count($result->same))
        ;

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
