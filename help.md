Remote Manage v3.0.3 - March 2021 - Backup or restore a web application.
https://github.com/ised-isde-canada/remote-manage
by Duncan Sutter, Samantha Tripp and Michael Milette

COMMANDS

`php manage.php <option> [command] <parameter>`

You must specify only one of the following commands:

```
backup                 Backup this site.
delete <app-name>      Delete database and persistent volume files.
                       Specified app-name must match the current system's app-name.
help                   Display this information.
maint [on/off]         Set site in maintenance (on), prod (off) mode.
                       If neither is specified, will return current state.
s3list [filter]        List available backups. Optional filename substring filter.
restore <filename>     Restore the specified backup file.
                       --exclude <file or directory> parameter to exclude a file or directory.
download <filename>    Download a backup file from S3 without restoring it.
space                  List disk space information.
app-name               Display the name of this application.
```

OPTIONS

```
--verbose              Display additional information during execution.
--format=[bytes/human] Format file sizes. Default is in bytes (CLI in human).
--log-stderr           Also log verbose messages to stderr.
```

Note: To use backup, restore and s3list from the CLI, you will need an .env file in the same directory as manage.php.

EXAMPLES

    php manage.php backup
    php manage.php delete learning-sandbox
    php manage.php help
    php manage.php maint
    php manage.php maint on
    php manage.php restore filename.tar.gz  OR  filename.zip
    php manage.php download filename.tar.gz  OR  filename.zip
    php manage.php s3list
    php manage.php space
    php manage.php --format=bytes space
    php manage.php app-name
