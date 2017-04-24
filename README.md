Install Extension for Yii 2
===========================

This extension provides ability for automated initialization of the project working copy, including local directories and
files creation, running DB migrations and so on.

For license information check the [LICENSE](LICENSE.md)-file.

[![Latest Stable Version](https://poser.pugx.org/yii2tech/install/v/stable.png)](https://packagist.org/packages/yii2tech/install)
[![Total Downloads](https://poser.pugx.org/yii2tech/install/downloads.png)](https://packagist.org/packages/yii2tech/install)
[![Build Status](https://travis-ci.org/yii2tech/install.svg?branch=master)](https://travis-ci.org/yii2tech/install)


Requirements
------------

This extension requires Linux OS.


Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist yii2tech/install
```

or add

```json
"yii2tech/install": "*"
```

to the require section of your composer.json.

If you wish to setup crontab during project installation, you will also need to install [yii2tech/crontab](https://github.com/yii2tech/crontab),
which is not required by default. In order to do so either run

```
php composer.phar require --prefer-dist yii2tech/crontab
```

or add

```json
"yii2tech/crontab": "*"
```

to the require section of your composer.json.


Usage
-----

This extension provides special console controller [[yii2tech\install\InitController]], which allows initialization of the
project working copy. Such initialization includes:

 - check if current environment matches project requirements.
 - create local directories (the ones, which may be not stored in version control system) and make them write-able.
 - create local files, such as configuration files, from templates.
 - run extra shell commands, like 'yii migrate' command.
 - setup cron jobs.

In order to create an installer, you should create a separated console application entry script. This script should
be absolutely stripped from the local configuration files, database and so on!
See [examples/install.php](examples/install.php) for the example of such script.

Once you have such script you can run installation process, using following command:

```
php install.php init
```


## Working with local files <span id="working-with-local-files"></span>

The most interesting feature introduced by [[yii2tech\install\InitController]] is creating local project files, such as
configuration files, from thier examples in interactive mode.
For each file, which content may vary depending on actual project environment, you should create a template file named in
format `{filename}.sample`. This file should be located under the same directory, where the actual local file should appear.
Inside the template file you can use placeholders in format: `{{placeholderName}}`. For example:

```php
defined('YII_DEBUG') or define('YII_DEBUG', {{yiiDebug}});
defined('YII_ENV') or define('YII_ENV', '{{yiiEnv}}');

return [
    'components' => [
        'db' => [
            'dsn' => 'mysql:host={{dbHost}};dbname={{dbName}}',
            'username' => '{{dbUser}}',
            'password' => '{{dbPassword}}',
        ],
    ],
];
```

While being processed, file templates are parsed, and for all found placeholders user will be asked to enter a value for them.
You can make this process more user-friendly by setting [[yii2tech\install\InitController::localFilePlaceholders]], specifying
hints, type and validation rules. See [[yii2tech\install\LocalFilePlaceholder]] for more details.


## Non interactive installation <span id="non-interactive-installation"></span>

Asking user for particular placeholder value may be not efficient and sometimes not acceptable. You may need to run
project intallation in fully automatic mode without user input, for example after updating source code from version
control system inside automatic project update.
In order to disable any user-interaction, you should use `interactive` option:

```
php install.php init --interactive=0
```

In this mode for all local file placeholders the default values will be taken, but only in case such values are explicitely
defined via [[yii2tech\install\InitController::localFilePlaceholders]]. Because install entry script usually stored under
version control system and local file placeholder values (as well as other installation parameters) may vary depending
on particular environment, [[yii2tech\install\InitController]] instroduce 'config' option. Using this option you may
specify extra configuration file, which should be merged with predefined parameters.
In order to create such configuration file, you can use following:

```
php install.php init/config @app/config/install.php
```

Once you have adjusted created configuration file, you can run installation with it:

```
php install.php init --config=@app/config/install.php --interactive=0
```
