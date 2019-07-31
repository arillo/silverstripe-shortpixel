<?php
namespace Arillo\Shortpixel;

use SilverStripe\Core\Environment;
use Exception;

class Shortpixel
{
    public static function get_api_status()
    {
        $apiKey = Environment::getEnv('SP_APIKEY');
        if (empty($apiKey)) user_error("Env 'SP_APIKEY' not set");

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
}