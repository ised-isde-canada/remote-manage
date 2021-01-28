# Remote management for a website

Written by: Duncan Sutter, Samantha Tripp and Michael Milette<br>
Date: July 2020<br>
License: MIT

This script is the main entry point for remote management. Operations are:
- backup
- restore

# Coding Guidelines

Please follow the PHP Standards Recommendations at https://www.php-fig.org/psr/

# How to use this package in your application

Install using Composer:

`composer require ised-isde/remote-manage`

# How to restore your app from a backup

You can restore your application from a backup using the command line. Before you can do anything, you need to
set your environment variables so that your host can communicate with the S3 bucket. Set these variables accordingly:

```
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_S3_BUCKET=
AWS_S3_REGION=
```

Now you need to get a list of the backups for the app that you are trying to restore from.

`vendor/ised-isde/remote-manage/manage s3list <app-name>`

You should see a list of the most recent backups. Now you are ready to restore:

`vendor/ised-isde/remote-manage/restore <path-to-backup>`

