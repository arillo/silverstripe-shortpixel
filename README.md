# Silverstripe Shortpixel

**CAUTION: use at your own risk!**

It will gradually optimze all images in a given folder (e.g. assets folder) via [shortpixel api](https://shortpixel.com) .
Purposed to run via cronjob. It will process `MAX_ALLOWED_FILES_PER_CALL` images per run.

Adds a Shortpixel-Tab to your `SiteConfig`, where you can switch off task execution and where you can find api status information.

## Setup

Get a shortpixel api key and set it in your `.env`:

```
SP_APIKEY="<YOUR_SHORTPIXEL_API_KEY>"
```

Configuration file (e.g. shortpixel.yml):

```
---
Name: myshortpixelconfig
After:
  - '#shortpixelconfig'
---
Arillo\Shortpixel\Tasks\FolderTask:
  exclude_folders:
    - '.protected' # omit .protected, default nothing

  # configure ShortPixel
  shortpixel_settings:
    MAX_ALLOWED_FILES_PER_CALL: 5 # default: 10
    CLIENT_MAX_BODY_SIZE: 20 # default: 48
    WAIT: 300 # default: 500

```

Setup an cronjob to execute the task:

```
*/1 * * * * php vendor/silverstripe/framework/cli-script.php dev/tasks/ShortpixelFolderTask
```

You might need to play around with cronjob timing and `shortpixel_settings` to avoid multiple overlapping executions.

Find more information about options & settings in `Arillo\Shortpixel\Tasks\FolderTask`.
