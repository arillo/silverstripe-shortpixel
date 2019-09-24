<?php
namespace Arillo\Shortpixel\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\{FieldList, CheckboxField, HTMLReadonlyField};
use Arillo\Shortpixel\Shortpixel;

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

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab('Root.Shortpixel', [
            CheckboxField::create(
                'ShortpixelFolderTaskDisabled',
                _t(__CLASS__ . '.ShortpixelFolderTaskDisabled', 'Folder task disabled')
            ),
            HTMLReadonlyField::create(
                'ApiStatus',
                'Api status',
                '<pre>' . $this->formatApiStatus(Shortpixel::get_api_status()) . '</pre>'
            )
        ]);
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
