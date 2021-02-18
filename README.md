Remote Management for Websites
==============================

![PHP](https://img.shields.io/badge/PHP-v7.3%2Fv7.4-blue.svg)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-v9.6%2Fv10.2-blue.svg)
[![GitHub Issues](https://img.shields.io/github/issues/ised-isde-canada/remote-manage.svg)](https://github.com/ised-isde-canada/remote-manage/issues)
[![Contributions welcome](https://img.shields.io/badge/contributions-welcome-green.svg)](#contributing)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Written by: Duncan Sutter, Samantha Tripp and Michael Milette<br>
Initial release: July 2020<br>
License: MIT

Note: This is the initial draft of the documentation.

This script is the main entry point for Remote Management. Operations are:

- Backup
- Restore

For more information and other options, see the [Help file](https://github.com/ised-isde-canada/remote-manage/blob/master/help.txt).

It has been tested with Moodle and Drupal sites. The backup function only includes your application and data files and database, not the operating system.
# Contributing

For more information about contributing, see [CONTRIBUTING.md](https://github.com/ised-isde-canada/remote-manage/blob/master/CONTRIBUTING.md).
## Coding Guidelines

Please follow the PHP Standards Recommendations at https://www.php-fig.org/psr/

# Installation
## Dependencies

- PHP 7.3 or 7.4 (may support PHP 8.x in the future)
- PostgreSQL 9.6/10.2 or later (may support MySQL/MariaDB in the future)
- AWS S3 Bucket
- Composer
- Info-ZIP 64-bit
- Drush (required for Drupal only)
- Has only been tested with RedHat 8 Linux but may work with others.

You will also need enough space, up to 120% of the space used by your application, data files and database export, in your /tmp area in order to store the temporary backup files.

## Using composer

Installation is done using (composer)[https://getcomposer.org/]:

    composer require ised-isde/remote-manage

## Configuration

You can backup, list backups and restore a backup of your application using the command line. Before you can do anything, you need to
set your environment variables so that your host can communicate with the S3 bucket. Set these variables accordingly:

    AWS_ACCESS_KEY_ID
    AWS_SECRET_ACCESS_KEY
    AWS_S3_BUCKET
    AWS_S3_REGION

Also set an environment variable called APP_NAME which will be used as part of the backup filenames.

In order to backup and restore application with a database, you will need to set the following environment variables:
    DB_HOST
    DB_USERNAME
    DB_PASSWORD
    DB_NAME
    DB_PORT

If these are not set, Remote Manage will skip backing up the database.

In order to backup and restore application files, set the following environment variable:
    HOME - set to your application's webroot.
    MOODLE_DATA_DIR - set to the moodledata directory (for Moodle only).

If these are not set, Remote Manage will skip backing up all files.

You can specify an alternate location for temporary storage of backup files by setting the RM_TEMP environment variable to an alternate directory path. If not specified, /tmp will be used.
# Usage
## Backup and restore your application

Note: Backup of application files is only currently supported for Moodle.

To backup your application:

    php vendor/ised-isde/remote-manage/manage backup <app-name>

To get a list of previous backups:

    php vendor/ised-isde/remote-manage/manage s3list <app-name>

To restore:

    php vendor/ised-isde/remote-manage/manage restore <backup-file-in-s3-bucket>

## Troubleshooting

Be sure to check out the --verbose option. See the [Help file](https://github.com/ised-isde-canada/remote-manage/blob/master/help.txt).
