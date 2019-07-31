# Silverstripe Shortpixel

It will gradually optimze all images in a given folder (e.g. assets folder) via [shortpixel api](https://shortpixel.com) .
Purposed to run via cronjob. It will process `MAX_ALLOWED_FILES_PER_CALL` images per run.

Adds a Shortpixel-Tab to your `SiteConfig`, where you can switch off task execution and where you can find api status information.

## Setup

Get a shortpixel api key and set it in your `.env`:

```
SP_APIKEY="<YOUR_SHORTPIXEL_API_KEY>"
```

Create a configuration file (e.g. shortpixel.yml) with following contents:

```
---
Name: myshortpixelconfig
After:
  - '#shortpixelconfig'
---
Arillo\Shortpixel\Tasks\FolderTask:
  exclude_folders:
    - '.protected'

SilverStripe\SiteConfig\SiteConfig:
  extensions:
    - Arillo\Shortpixel\Extensions\SiteConfig

```

Setup an cronjob to execute the task

```
*/1 * * * * php vendor/silverstripe/framework/cli-script.php dev/tasks/ShortpixelFolderTask
```

Find more information about options & settings in `Arillo\Shortpixel\Tasks\FolderTask`.
