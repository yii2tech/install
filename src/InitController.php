<?php
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2tech\install;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\console\Controller;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use yii\helpers\VarDumper;
use yii\log\EmailTarget;
use yii\log\FileTarget;
use yii\log\Logger;
use YiiRequirementChecker;
use yii2tech\crontab\CronTab;

/**
 * InitController is a console command, which performs basic application initialization.
 * This command allows to create local folders and files, to apply necessary file permissions and to perform the
 * basic application initialization.
 * This command should be run, for example, after the application has been check out from the version control system,
 * in order to prepare it to work.
 *
 * Any local file can have an example (which can be stored under the version control system).
 * The example file name should correspond a pattern [[localFileExampleNamePattern]].
 * Local file example can contain the placeholders marked in format: {{placeholderName}}.
 * While creating the local file from example the value for placeholder will be asked from user dialog.
 *
 * Use action "all" (method [[actionAll()]]) to perform all initializations.
 * You can strap external command configuration file using [[config]] property.
 *
 * This console command can maintain logs on its own. You can setup [[logFile]] or/and [[logEmail]],
 * to enable logging.
 *
 * Note: the console application, which will run this command should be absolutely stripped from the local
 * configuration files and database.
 *
 * @see YiiRequirementsChecker
 *
 * @property array|CronTab $cronTab crontab instance or its array configuration.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class InitController extends Controller
{
    /**
     * @var string the name of the default action.
     */
    public $defaultAction = 'all';
    /**
     * @var array list of local directories, which should be created and available to write by web server.
     * Path aliases can be used here. For example:
     *
     * ```php
     * [
     *     '@app/web/assets',
     *     '@runtime',
     * ]
     * ```
     */
    public $localDirectories = [];
    /**
     * @var array list of temporary directories, which should be cleared during application initialization/update.
     * Path aliases can be used here. For example:
     *
     * ```php
     * [
     *     '@app/web/assets',
     *     '@runtime',
     * ]
     * ```
     */
    public $tmpDirectories = [];
    /**
     * @var array list of local files, which should be created from the example files.
     * Path aliases can be used here. For example:
     *
     * ```php
     * [
     *     '@app/web/.htaccess',
     *     '@app/config/local.php',
     * ]
     * ```
     */
    public $localFiles = [];
    /**
     * @var string pattern, which is used to determine example file name for the local file.
     * This pattern should contain "{filename}" placeholder, which will be replaced by local file self name.
     */
    public $localFileExampleNamePattern = '{filename}.sample';
    /**
     * @var array list of local file placeholders in format: 'placeholderName' => [config].
     * Each placeholder value should be a valid configuration for [[LocalFilePlaceholder]].
     */
    public $localFilePlaceholders = [];
    /**
     * @var array list of files, which should be executable.
     * Path aliases can be used here. For example:
     *
     * ```php
     * [
     *     '@app/yii',
     *     '@app/install.php',
     * ]
     * ```
     */
    public $executeFiles = [];
    /**
     * @var string requirements list file name.
     * @see YiiRequirementsChecker
     */
    public $requirementsFileName = '@app/requirements.php';
    /**
     * @var array list of shell commands, which should be executed during project installation.
     * If command is a string it will be executed as shell command, otherwise as PHP callback.
     * For example:
     *
     * ```php
     * [
     *     'php /path/to/project/yii migrate/up --interactive=0'
     * ],
     * ```
     */
    public $commands = [];
    /**
     * @var bool whether to output log messages via "stdout". Defaults to true.
     * Set this to false to cease console output.
     */
    public $outputLog = true;
    /**
     * @var string configuration file name. Settings from this file will be merged with the default ones.
     * Such configuration file can be created, using action 'config'.
     * Path alias can be used here, for example: '@app/config/install.php'.
     */
    public $config = '';
    /**
     * @var string name of the file, which collect the process logs.
     */
    public $logFile = '';
    /**
     * @var string email address, which should receive the process error logs,
     * it can be comma-separated email addresses.
     * Inside the config file this parameter can be specified as array.
     */
    public $logEmail = '';

    /**
     * @var CronTab|array cron tab instance or its array configuration.
     * For example:
     *
     * ```php
     * [
     *     'jobs' => [
     *         [
     *             'min' => '0',
     *             'hour' => '0',
     *             'command' => 'php /path/to/project/protected/yii some-cron',
     *         ],
     *         [
     *             'line' => '0 0 * * * php /path/to/project/protected/yii another-cron'
     *         ]
     *     ],
     * ];
     * ```
     *
     * Note: if you wish to use this option, make sure you have 'yii2tech/crontab' installed at your project.
     */
    private $_cronTab = [];


    /**
     * @param CronTab|array $cronTab cron tab instance or its array configuration.
     */
    public function setCronTab($cronTab)
    {
        $this->_cronTab = $cronTab;
    }

    /**
     * @throws InvalidConfigException on invalid configuration.
     * @return CronTab|null cron tab instance, or null if not set.
     */
    public function getCronTab()
    {
        if (empty($this->_cronTab)) {
            return null;
        }

        if ($this->_cronTab instanceof CronTab) {
            return $this->_cronTab;
        }

        if (!is_array($this->_cronTab)) {
            throw new InvalidConfigException('"' . get_class($this) . '::cronTab" should be instance of "' . CronTab::className() . '" or its array configuration.');
        }

        if (empty($this->_cronTab['class'])) {
            $this->_cronTab['class'] = CronTab::className();
        }
        $this->_cronTab = Yii::createObject($this->_cronTab);

        return $this->_cronTab;
    }

    /**
     * Initializes and adjusts the log process.
     */
    public function initLog()
    {
        $targets = [];
        if ($fileLogRoute = $this->createFileLogTarget()) {
            $targets['init-file'] = $fileLogRoute;
        }
        if ($emailLogRoute = $this->createEmailLogTarget()) {
            $targets['init-email'] = $emailLogRoute;
        }

        if (!empty($targets)) {
            Yii::getLogger()->flushInterval = 1;
            $log = Yii::$app->getLog();
            $log->targets = array_merge($log->targets, $targets);
        }
    }

    /**
     * Creates a file log target, if it is required.
     * @return FileTarget|null file log target or null, if it is not required.
     */
    protected function createFileLogTarget()
    {
        if (empty($this->logFile)) {
            return null;
        }

        return Yii::createObject([
            'class' => FileTarget::className(),
            'exportInterval' => 1,
            'categories' => [get_class($this) . '*'],
            'logFile' => $this->logFile,
        ]);
    }

    /**
     * Creates an email log target, if it is required.
     * @return EmailTarget|null email log target or null, if it is not required.
     */
    protected function createEmailLogTarget()
    {
        $logEmail = $this->logEmail;
        if (empty($logEmail)) {
            return null;
        }

        $userName = @exec('whoami');
        if (empty($userName)) {
            $userName = Inflector::slug(Yii::$app->name);
        }
        $hostName = @exec('hostname');
        if (empty($hostName)) {
            $hostName = Inflector::slug(Yii::$app->name) . '.com';
        }
        $sentFrom = $userName . '@' . $hostName;

        return Yii::createObject([
            'class' => EmailTarget::className(),
            'levels' => Logger::LEVEL_ERROR | Logger::LEVEL_WARNING,
            'message' => [
                'to' => $logEmail,
                'subject' => 'Application "' . Yii::$app->name . '" initialization error at ' . $hostName,
                'from' => $sentFrom,
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function beforeAction($action)
    {
        if (!empty($this->config)) {
            $this->populateFromConfigFile($this->config);
        }
        $this->initLog();

        return parent::beforeAction($action);
    }

    /**
     * {@inheritdoc}
     */
    public function options($actionID)
    {
        return array_merge(
            parent::options($actionID),
            [
                'config',
                'outputLog',
                'logFile',
                'logEmail',
            ]
        );
    }

    /**
     * Logs message.
     * @param string $message the text message
     * @param int $level log message level.
     * @return bool success.
     */
    protected function log($message, $level = null)
    {
        if ($level === null) {
            $level = Logger::LEVEL_INFO;
        }
        if ($this->outputLog) {
            if ($level != Logger::LEVEL_INFO) {
                $verboseLevel = $level;
                switch ($level) {
                    case Logger::LEVEL_ERROR:
                        $verboseLevel = 'error';
                        break;
                    case Logger::LEVEL_WARNING:
                        $verboseLevel = 'warning';
                        break;
                    case Logger::LEVEL_TRACE:
                        $verboseLevel = 'trace';
                        break;
                }
                $this->stderr("\n[{$verboseLevel}] {$message}\n");
            } else {
                $this->stdout($message);
            }
        }
        $message = trim($message, "\n");
        if (!empty($message)) {
            Yii::getLogger()->log($message, $level, get_class($this));
        }
        return true;
    }

    /**
     * Returns configuration for the given local file placeholder name.
     * If placeholder has configured empty configuration will be returned.
     * @param string $placeholderName placeholder name
     * @return array placeholder configuration.
     */
    protected function getLocalFilePlaceholderConfig($placeholderName)
    {
        if (array_key_exists($placeholderName, $this->localFilePlaceholders)) {
            return $this->localFilePlaceholders[$placeholderName];
        } else {
            return [];
        }
    }

    /**
     * Performs all application initialize actions.
     * @param bool $overwrite indicates, if existing local file should be overwritten in the process.
     * @return int CLI exit code
     */
    public function actionAll($overwrite = false)
    {
        if ($this->confirm("Initialize project under '" . Yii::$app->basePath . "'?")) {
            $this->log("Project initialization in progress...\n");
            if ($this->actionRequirements(false) !== self::EXIT_CODE_NORMAL) {
                $this->log("Project initialization failed.", Logger::LEVEL_ERROR);
                return self::EXIT_CODE_ERROR;
            }
            $this->actionLocalDir();
            $this->actionClearTmpDir();
            $this->actionExecuteFile();
            $this->actionLocalFile(null, $overwrite);
            $this->actionCommands();
            $this->actionCrontab();
            $this->log("\nProject initialization is complete.\n");
        }
        return self::EXIT_CODE_NORMAL;
    }

    /**
     * Check if current system matches application requirements.
     * @param bool $forceShowResult indicates if verbose check result should be displayed even,
     * if there is no errors or warnings.
     * @return int CLI exit code
     */
    public function actionRequirements($forceShowResult = true)
    {
        $this->log("Checking requirements...\n");

        $requirements = [];

        $requirementsFileName = Yii::getAlias($this->requirementsFileName);
        if (file_exists($requirementsFileName)) {
            ob_start();
            ob_implicit_flush(false);
            $requirements = require $requirementsFileName;
            $output = ob_get_clean();

            if (is_int($requirements) && !empty($output)) {
                if (preg_match('/^Errors: (?P<errors>[0-9]+)[ ]+Warnings: (?P<warnings>[0-9]+)[ ]+Total checks: (?P<total>[0-9]+)$/im', $output, $matches)) {
                    $errors = (int)$matches['errors'];
                    $warnings = (int)$matches['warnings'];
                    if ($errors > 0) {
                        $this->log("Requirements check fails with errors.", Logger::LEVEL_ERROR);
                        $this->stdout($output);
                        return self::EXIT_CODE_ERROR;
                    } elseif ($warnings > 0) {
                        $this->log("Requirements check passed with warnings.", Logger::LEVEL_WARNING);
                        $this->stdout($output);
                        return self::EXIT_CODE_NORMAL;
                    } else {
                        $this->log("Requirements check successful.\n");
                        if ($forceShowResult) {
                            $this->stdout($output);
                        }
                        return self::EXIT_CODE_NORMAL;
                    }
                }
            }
        } else {
            $this->log("Requirements list file '{$requirementsFileName}' does not exist, only default requirements checking is available.", Logger::LEVEL_WARNING);
        }

        $requirementsChecker = $this->createRequirementsChecker();
        $requirementsChecker->checkYii()->check($requirements);

        $requirementsCheckResult = $requirementsChecker->getResult();
        if ($requirementsCheckResult['summary']['errors'] > 0) {
            $this->log("Requirements check fails with errors.", Logger::LEVEL_ERROR);
            $requirementsChecker->render();
            return self::EXIT_CODE_ERROR;
        } elseif ($requirementsCheckResult['summary']['warnings'] > 0) {
            $this->log("Requirements check passed with warnings.", Logger::LEVEL_WARNING);
            $requirementsChecker->render();
            return self::EXIT_CODE_NORMAL;
        } else {
            $this->log("Requirements check successful.\n");
            if ($forceShowResult) {
                $requirementsChecker->render();
            }
            return self::EXIT_CODE_NORMAL;
        }
    }

    /**
     * Creates all local directories and makes sure they are writeable for the web server.
     * @return int CLI exit code
     */
    public function actionLocalDir()
    {
        $this->log("\nEnsuring local directories:\n");
        $filePermissions = 0777;
        foreach ($this->localDirectories as $directory) {
            $directoryPath = Yii::getAlias($directory);
            if (!file_exists($directoryPath)) {
                $this->log("\nCreating directory '{$directoryPath}'...");
                if (FileHelper::createDirectory($directoryPath, $filePermissions)) {
                    $this->log("complete.\n");
                } else {
                    $this->log("Unable to create directory '{$directoryPath}'!", Logger::LEVEL_ERROR);
                }
            }
            $this->log("Setting permissions '" . decoct($filePermissions) . "' for '{$directoryPath}'...");
            if (chmod($directoryPath, $filePermissions)) {
                $this->log("complete.\n");
            } else {
                $this->log("Unable to set permissions '" . decoct($filePermissions) . "' for '{$directoryPath}'!", Logger::LEVEL_ERROR);
            }
        }
        return self::EXIT_CODE_NORMAL;
    }

    /**
     * Clears temporary directories, avoiding special files such as ".htaccess" and VCS files.
     * @param string $dir directory name.
     * @return int CLI exit code
     */
    public function actionClearTmpDir($dir = null)
    {
        if (!empty($dir) || $this->confirm('Clear all temporary directories?')) {
            $this->log("\nClearing temporary directories:\n");
            $temporaryDirectories = $this->tmpDirectories;
            $excludeNames = [
                '.htaccess',
                '.svn',
                '.gitignore',
                '.gitkeep',
                '.hgignore',
                '.hgkeep',
            ];
            foreach ($temporaryDirectories as $temporaryDirectory) {
                $tmpDirFullName = Yii::getAlias($temporaryDirectory);
                if ($dir !== null && (strpos($tmpDirFullName, $dir) === false)) {
                    continue;
                }
                if (!is_dir($tmpDirFullName)) {
                    $this->log("Directory '{$tmpDirFullName}' does not exists!", Logger::LEVEL_WARNING);
                    continue;
                }
                $this->log("\nClearing directory '{$tmpDirFullName}'...");
                $tmpDirHandle = opendir($tmpDirFullName);
                while (($fileSystemObjectName = readdir($tmpDirHandle)) !== false) {
                    if ($fileSystemObjectName === '.' || $fileSystemObjectName === '..') {
                        continue;
                    }
                    if (in_array($fileSystemObjectName, $excludeNames)) {
                        continue;
                    }
                    $fullName = $tmpDirFullName . DIRECTORY_SEPARATOR . $fileSystemObjectName;
                    if (is_dir($fullName)) {
                        FileHelper::removeDirectory($fullName);
                    } else {
                        unlink($fullName);
                    }
                }
                closedir($tmpDirHandle);
                $this->log("complete.\n");
            }
        }
        return self::EXIT_CODE_NORMAL;
    }

    /**
     * Change permissions for the specific files, making them executable.
     * @return int CLI exit code
     */
    public function actionExecuteFile()
    {
        $this->log("\nEnsuring execute able files:\n");
        $filePermissions = 0755;
        foreach ($this->executeFiles as $fileName) {
            $this->log("Setting permissions '" . decoct($filePermissions) . "' for '{$fileName}'...");
            $fileRealName = Yii::getAlias($fileName);
            if (chmod($fileRealName, $filePermissions)) {
                $this->log("complete.\n");
            } else {
                $this->log("Unable to set permissions '" . decoct($filePermissions) . "' for '{$fileRealName}'!", Logger::LEVEL_ERROR);
            }
        }
        return self::EXIT_CODE_NORMAL;
    }

    /**
     * Runs the shell commands defined by [[commands]].
     * @return int CLI exit code
     */
    public function actionCommands()
    {
        if (empty($this->commands)) {
            $this->log("No extra shell commands are defined.\n");
            return self::EXIT_CODE_NORMAL;
        }

        $commandTitles = [];
        foreach ($this->commands as $key => $command) {
            if (is_string($command)) {
                $commandTitles[$key] = $command;
            } else {
                $commandTitles[$key] = 'Unknown (Closure)';
            }
        }
        if ($this->confirm("Following commands will be executed:\n" . implode("\n", $commandTitles) . "\nDo you wish to proceed?")) {
            foreach ($this->commands as $key => $command) {
                $this->log($commandTitles[$key] . "\n");
                if (is_string($command)) {
                    exec($command, $outputRows);
                    $this->log(implode("\n", $outputRows));
                } else {
                    $this->log(call_user_func($command));
                }
                $this->log("\n");
            }
        }
        return self::EXIT_CODE_NORMAL;
    }

    /**
     * Sets up the project cron jobs.
     * @return int CLI exit code
     */
    public function actionCrontab()
    {
        $cronTab = $this->getCronTab();
        if (!is_object($cronTab)) {
            $this->log("There are no cron tab to setup.\n");
            return self::EXIT_CODE_NORMAL;
        }
        $cronJobs = $cronTab->getJobs();
        if (empty($cronJobs)) {
            $this->log("There are no cron jobs to setup.\n");
        } else {
            $userName = @exec('whoami');
            if (empty($userName)) {
                $userName = 'unknown';
            }
            if ($this->confirm("Setup the cron jobs for the user '{$userName}'?")) {
                $this->log("Setting up cron jobs:\n");
                $cronTab->apply();
                $this->log("crontab is set for the user '{$userName}'\n");
            }
        }
        return self::EXIT_CODE_NORMAL;
    }

    /**
     * Creates new local files from example files.
     * @param string $file name of the particular local file, if empty all local files will be processed.
     * @param bool $overwrite indicates, if existing local file should be overwritten in the process.
     * @return int CLI exit code
     */
    public function actionLocalFile($file = null, $overwrite = false)
    {
        $this->log("\nCreating local files:\n");
        foreach ($this->localFiles as $localFileRawName) {
            $localFileRealName = Yii::getAlias($localFileRawName);
            if ($file !== null && (strpos($localFileRealName, $file) === false)) {
                continue;
            }
            $this->log("\nProcessing local file '{$localFileRealName}':\n");

            $exampleFileName = $this->getExampleFileName($localFileRealName);
            if (!file_exists($exampleFileName)) {
                $this->log("Unable to find example for the local file '{$localFileRealName}': file '{$exampleFileName}' does not exists!", Logger::LEVEL_ERROR);
            }
            if (file_exists($localFileRealName)) {
                if (filemtime($exampleFileName) > filemtime($localFileRealName)) {
                    $this->log("Local file '{$localFileRealName}' is out of date and should be regenerated.", Logger::LEVEL_WARNING);
                } else {
                    if (!$overwrite) {
                        $this->log("Local file '{$localFileRealName}' already exists. Use 'overwrite' option, if you wish to regenerate it.\n");
                        continue;
                    }
                }
            }
            $this->createLocalFileByExample($localFileRealName, $exampleFileName);
        }
        return self::EXIT_CODE_NORMAL;
    }

    /**
     * Generates new configuration file, which can be used to run
     * application initialization.
     * @param string $file output config file name.
     * @param bool $overwrite indicates, if existing configuration file should be overwritten in the process.
     * @return int CLI exit code
     */
    public function actionConfig($file = null, $overwrite = false)
    {
        if (empty($file)) {
            if (empty($this->config)) {
                $this->log('Either "config" or "file" option should be provided.');
                return self::EXIT_CODE_ERROR;
            }
            $fileName = Yii::getAlias($this->config);
        } else {
            $fileName = Yii::getAlias($file);
        }
        if (file_exists($fileName)) {
            if (!$overwrite) {
                if (!$this->confirm("Configuration file '{$file}' already exists, do you wish to overwrite it?")) {
                    return self::EXIT_CODE_NORMAL;
                }
            }
        }

        $configPropertyNames = [
            'interactive',
            'logFile',
            'logEmail',
            'tmpDirectories',
            'localDirectories',
            'executeFiles',
            'localFileExampleNamePattern',
            'localFiles',
            'localFilePlaceholders',
        ];
        $config = [];
        foreach ($configPropertyNames as $configPropertyName) {
            $config[$configPropertyName] = $this->$configPropertyName;
        }

        $fileContent = "<?php\nreturn " . VarDumper::export($config) . ";";
        if (file_exists($fileName)) {
            if (unlink($fileName)) {
                $this->log("Old version of the configuration file '{$file}' has been removed.\n");
            } else {
                $this->log("Unable to remove old version of the configuration file '{$file}'!", Logger::LEVEL_ERROR);
            }
        }
        file_put_contents($fileName, $fileContent);
        if (file_exists($fileName)) {
            $this->log("Configuration file '{$file}' has been created.\n");
            return self::EXIT_CODE_NORMAL;
        } else {
            $this->log("Unable to create configuration file '{$file}'!", Logger::LEVEL_ERROR);
            return self::EXIT_CODE_ERROR;
        }
    }

    /**
     * Creates new local file from example file.
     * @param string $localFileName local file full name.
     * @param string $exampleFileName example file full name.
     * @return int CLI exit code
     */
    protected function createLocalFileByExample($localFileName, $exampleFileName)
    {
        $this->log("Creating local file '{$localFileName}':\n");

        $placeholderNames = $this->parseExampleFile($exampleFileName);
        if (!empty($placeholderNames) && $this->interactive) {
            $this->log("Specify local file placeholder values. Enter empty string to apply default value. Enter whitespace to specify empty value.\n");
        }
        $placeholders = [];
        foreach ($placeholderNames as $placeholderName) {
            $placeholderConfig = $this->getLocalFilePlaceholderConfig($placeholderName);
            $model = new LocalFilePlaceholder($placeholderName, $placeholderConfig);
            if ($this->interactive) {
                $placeholderLabel = $model->composeLabel();
                $isValid = false;
                while (!$isValid) {
                    $model->value = $this->prompt("Enter {$placeholderLabel}:");
                    if ($model->validate()) {
                        $isValid = true;
                    } else {
                        $this->stdout("Error: invalid value entered:\n");
                        $this->stdout($model->getErrorSummary() . "\n");
                    }
                }
            }
            try {
                $placeholderActualValue = $model->getActualValue();
                $placeholders[$placeholderName] = $placeholderActualValue;
            } catch (\Exception $exception) {
                $this->log($exception->getMessage(), Logger::LEVEL_ERROR);
            }
        }
        $localFileContent = $this->composeLocalFileContent($exampleFileName, $placeholders);
        if (file_exists($localFileName)) {
            $this->log("Removing old version of file '{$localFileName}'...");
            if (unlink($localFileName)) {
                $this->log("complete.\n");
            } else {
                $this->log("Unable to remove old version of file '{$localFileName}'!", Logger::LEVEL_ERROR);
                return self::EXIT_CODE_ERROR;
            }
        }
        file_put_contents($localFileName, $localFileContent);
        if (file_exists($localFileName)) {
            $this->log("Local file '{$localFileName}' has been created.\n");
            return self::EXIT_CODE_NORMAL;
        } else {
            $this->log("Unable to create local file '{$localFileName}'!", Logger::LEVEL_ERROR);
            return self::EXIT_CODE_ERROR;
        }
    }

    /**
     * Determines the full name of the example file for the given local file.
     * @param string $localFileName local file full name.
     * @return string example file full name.
     */
    protected function getExampleFileName($localFileName)
    {
        $localFileDir = dirname($localFileName);
        $localFileSelfName = basename($localFileName);
        $localFileExampleSelfName = str_replace('{filename}', $localFileSelfName, $this->localFileExampleNamePattern);
        return $localFileDir . DIRECTORY_SEPARATOR . $localFileExampleSelfName;
    }

    /**
     * Finds the placeholders in the example file.
     * @param string $exampleFileName example file name.
     * @return array placeholders list.
     */
    protected function parseExampleFile($exampleFileName)
    {
        $exampleFileContent = file_get_contents($exampleFileName);
        if (preg_match_all('/{{(\w+)}}/is', $exampleFileContent, $matches)) {
            $placeholders = array_unique($matches[1]);
        } else {
            $placeholders = [];
        }
        return $placeholders;
    }

    /**
     * Composes local file content from example file content, using given placeholders.
     * @param string $exampleFileName example file full name.
     * @param array $placeholders set of placeholders.
     * @return string local file content.
     */
    protected function composeLocalFileContent($exampleFileName, array $placeholders)
    {
        $exampleFileContent = file_get_contents($exampleFileName);
        $replacePairs = [];
        foreach ($placeholders as $name => $value) {
            $replacePairs['{{' . $name . '}}'] = $value;
        }
        return strtr($exampleFileContent, $replacePairs);
    }

    /**
     * Populates console command instance from configuration file.
     * @param string $configFileName configuration file name.
     * @return bool success.
     * @throws InvalidParamException on wrong configuration file.
     */
    public function populateFromConfigFile($configFileName)
    {
        $configFileName = realpath(Yii::getAlias($configFileName));
        if (!file_exists($configFileName)) {
            throw new InvalidParamException("Unable to read configuration file '{$configFileName}': file does not exist!");
        }

        $configFileExtension = pathinfo($configFileName, PATHINFO_EXTENSION);
        switch ($configFileExtension) {
            case 'php': {
                $configData = $this->extractConfigFromFilePhp($configFileName);
                break;
            }
            default: {
                throw new InvalidParamException("Configuration file has unknown type: '{$configFileExtension}'!");
            }
        }

        if (!is_array($configData)) {
            throw new InvalidParamException("Unable to read configuration from file '{$configFileName}': wrong file format!");
        }
        foreach ($configData as $name => $value) {
            $originValue = $this->$name;
            if (is_array($originValue) && is_array($value)) {
                $value = array_merge($originValue, $value);
            }
            $this->$name = $value;
        }
        return true;
    }

    /**
     * Extracts configuration array from PHP file.
     * @param string $configFileName configuration file name.
     * @return mixed configuration data.
     */
    protected function extractConfigFromFilePhp($configFileName)
    {
        $configData = require($configFileName);
        return $configData;
    }

    /**
     * Creates requirements checker instance.
     * @return YiiRequirementChecker requirements checker instance.
     */
    protected function createRequirementsChecker()
    {
        if (!class_exists('YiiRequirementChecker', false)) {
            require Yii::getAlias('@vendor/yiisoft/yii2/requirements/YiiRequirementChecker.php');
        }
        return new YiiRequirementChecker();
    }
}
