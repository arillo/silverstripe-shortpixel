# Silverstripe Shortpixel

[![Latest Stable Version](https://poser.pugx.org/arillo/silverstripe-shortpixel/v/stable)](https://packagist.org/packages/arillo/silverstripe-shortpixel)
[![Total Downloads](https://poser.pugx.org/arillo/silverstripe-shortpixel/downloads)](https://packagist.org/packages/arillo/silverstripe-shortpixel)
[![License](https://poser.pugx.org/arillo/silverstripe-shortpixel/license)](https://packagist.org/packages/arillo/silverstripe-shortpixel)

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

  # you can turn off auto file re-hashing, if you want to.
  # plays nice with beforeShortPixelCall and afterShortPixelCall hooks, to create your own recovery strategy
  use_simple_image_recovering: false # default true

  # optionally you can set the root folder
  root_folder: <ABSOLUTE_PATH_TO_ROOT_FOLDER> # default ASSETS_PATH

```

Setup an cronjob to execute the task:

```
*/1 * * * * php vendor/silverstripe/framework/cli-script.php dev/tasks/ShortpixelFolderTask
```

You might need to play around with cronjob timing and `shortpixel_settings` to avoid multiple overlapping executions.

Find more information about options & settings in `Arillo\Shortpixel\Tasks\FolderTask`.
