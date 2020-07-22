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

It is highly recommended to install using Composer:

`composer require ised/remote-manage`

However, before you can do that, you probably need to reference the repository in your composer.json file.
Add this to the repositories section:

```
"repositories": [
    {
        "type": "package",
        "package": {
            "name": "ised/remote-manage",
            "version": "1.0",
            "type": "library",
            "source": {
                "url": "https://github.com/ised-isde-canada/remote-manage.git",
                "type": "git",
                "reference": "master"
            }
        }
    }
```

In order to use remote management through HTTPS, you will require a soft-link from your web root to
the main script in this package. The manual step would look like this:

```
cd /opt/app-root/src/html
ln -s /opt/app-root/src/vendor/ised/remote-manage/manage.php manage.php
```

You might want to automate this in an install script which can be triggered by composer. Follow this example,
but you may need to change your namespace:

```
"scripts": {
    "post-install-cmd": [
        "DrupalWxT\\WxT\\ScriptHandler::myInstall"
    ],
    "post-update-cmd": [
        "DrupalWxT\\WxT\\ScriptHandler::myInstall"
    ]
}
```

Then in scripts/ScriptHandler.php, create a function like this (or tap into an existing one):

```
public static function myInstall(Event $event) {
    $fs = new Filesystem();
    $root = static::getDrupalRoot(getcwd());
    $src = dirname($root);

    if ($fs->exists("$src/vendor/ised/remote-manage/manage.php")) {
        $fs->symlink("$src/vendor/ised/remote-manage/manage.php", "$root/manage.php");
    }
}
```
