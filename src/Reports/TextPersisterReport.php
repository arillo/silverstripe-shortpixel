<?php
namespace Arillo\ShortPixel\Reports;

use Arillo\Shortpixel\Tasks\FolderTask;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use RegexIterator;
use RecursiveRegexIterator;

/**
 * Collects information from all .shortpixel files inside of FolderTask::root_folder();
 *
 * @author Bumbus <sf@arillo.ch>
 */
class TextPersisterReport
{
    const FILENAME = '.shortpixel';
    const FILENAME_REGEX = '/^.+\.shortpixel$/i';
    const ALLOWED_STATUSES = ['pending', 'success', 'skip', 'deleted'];
    const LINE_LENGTH = 465;

    protected $report = [];

    /**
     * Actually generates the report
     * @return array
     */
    public function generate()
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(FolderTask::root_folder())
        );

        $filePaths = new RegexIterator(
            $iterator,
            self::FILENAME_REGEX,
            RecursiveRegexIterator::GET_MATCH
        );

        $this->report = [];

        foreach ($filePaths as $path => $void) {
            $folderPath = str_replace(self::FILENAME, '', $path);
            $folderPath = str_replace(FolderTask::root_folder(), '', $folderPath);
            $fp = @fopen($path, "r");
            while (($line = fgets($fp)) !== FALSE) {
                $data = $this->parse($line, $folderPath);
                if ($data && $data->type != 'D') $this->report[] = $data;
            }
            fclose($fp);
        }

        return $this->report;
    }

    /**
     * Borrowed from ShortPixel\persist\TextPersister.
     *
     * @param  string $line
     * @param  string $folder
     * @return object | false
     */
    protected function parse(string $line, string $folder)
    {
        if (strlen(rtrim($line, "\r\n")) != (self::LINE_LENGTH - 2)) return false;

        $percent = trim(substr($line, 52, 6));
        $optimizedSize = trim(substr($line, 58, 9));
        $originalSize = trim(substr($line, 454, 9));
        $file = rtrim(substr($line, 87, 256));
        $ret = (object) [
            'type' => trim(substr($line, 0, 2)),
            'status' => trim(substr($line, 2, 11)),
            'retries' => trim(substr($line, 13, 2)),
            'compressionType' => trim(substr($line, 15, 9)),
            'keepExif' => trim(substr($line, 24, 2)),
            'cmyk2rgb' => trim(substr($line, 26, 2)),
            'resize' => trim(substr($line, 28, 2)),
            'resizeWidth' => trim(substr($line, 30, 6)),
            'resizeHeight' => trim(substr($line, 36, 6)),
            'convertto' => trim(substr($line, 42, 10)),
            'percent' => is_numeric($percent) ? floatval($percent) : 0.0,
            'optimizedSize' => is_numeric($optimizedSize) ? intval($optimizedSize) : 0,
            'changeDate' => strtotime(trim(substr($line, 67, 20))),
            'file' => $file,
            'filePath' => $folder . $file,
            'message' => trim(substr($line, 343, 111)),
            'originalSize' => is_numeric($originalSize) ? intval($originalSize) : 0,
        ];

        if (!in_array($ret->status, self::ALLOWED_STATUSES) || !$ret->changeDate) {
            return false;
        }
        return $ret;
    }
}
