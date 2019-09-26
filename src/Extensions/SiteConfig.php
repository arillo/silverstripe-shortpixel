<?php
namespace Arillo\Shortpixel\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\{FieldList, CheckboxField, HTMLReadonlyField};

use SilverStripe\Forms\GridField\{
    GridField,
    GridFieldConfig_Base,
    GridFieldDataColumns
};

use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;

use Arillo\Shortpixel\Shortpixel;
use Arillo\Shortpixel\Reports\TextPersisterReport;

/**
 * Add shortpixel stuff to site config.
 *
 * @author Bumbus <sf@arillo.ch>
 */
class SiteConfig extends DataExtension
{
    private static $db = [
        'ShortpixelFolderTaskDisabled' => 'Boolean',
        'ShortpixelFolderTaskLastImages' => 'Text',
    ];

    /**
     * Show shortpixel report?
     * @var boolean
     */
    private static $shortpixel_enable_report = true;

    /**
     * Show shortpixel quota
     * @var boolean
     */
    private static $shortpixel_show_quota = true;

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab('Root.Shortpixel', [
            CheckboxField::create(
                'ShortpixelFolderTaskDisabled',
                _t(__CLASS__ . '.ShortpixelFolderTaskDisabled', 'Folder task disabled')
            )
        ]);

        if ($this->owner->config()->shortpixel_show_quota) {
            $fields->addFieldsToTab('Root.Shortpixel', [
                HTMLReadonlyField::create(
                    'ApiStatus',
                    'Api status',
                    '<pre>' . $this->formatApiStatus(Shortpixel::get_api_status()) . '</pre>'
                )
            ]);
        }

        if ($this->owner->config()->shortpixel_enable_report) {
            $report = new TextPersisterReport();
            $reportDataRaw = $report->generate();
            $reportData = ArrayList::create([]);

            foreach ($reportDataRaw as $data) {
                $record = ArrayData::create($data);
                $record->dateNice = date('d.m.Y H:i:s', $record->changeDate);
                $reportData->push($record);
            }

            $fields->addFieldsToTab('Root.Shortpixel', [
                GridField::create(
                    'Report',
                    'Report',
                    $reportData->sort('changeDate'),
                    $config = GridFieldConfig_Base::create()
                )
            ]);

            $config
                ->getComponentByType(GridFieldDataColumns::class)
                ->setDisplayFields([
                    'dateNice' => 'Date',
                    'filePath' => 'filePath',
                    'status' => 'status',
                    'percent' => 'percent',
                ])
            ;
        }
    }

    protected function formatApiStatus($status)
    {
        $result = [];
        foreach ($status as $key => $value) {
            if (is_string($value)) $result[] = "{$key}: {$value}";
        }

        return implode($result, '<br>');
    }
}
