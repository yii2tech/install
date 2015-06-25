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
use yii\helpers\VarDumper;
use yii\log\EmailTarget;
use yii\log\FileTarget;
use yii\log\Logger;
use YiiRequirementChecker;
use yii2tech\crontab\CronTab;

/**
 * AppInit is a console command, which performs basic application initialization.
 * This command allows to create local folders and files, to apply necessary file permissions and to perform the
 * basic application initialization.
 * This command should be run, for example, after the application has been check out from the version control system,
 * in order to prepare it to work.
 *
 * Any local file can have an example (which can be stored under the version control system).
 * The example file name should correspond a pattern {@link localFileExampleNamePattern}.
 * Local file example can contain the placeholders marked in format: {{placeholderName}}.
 * While creating the local file from example the value for placeholder will be asked from user dialog.
 *
 * Use action "all" (method {@link actionAll()}) to perform all initializations.
 * You can strap external command configuration file using {@link config} property.
 *
 * This console command can maintain logs on its own. You can setup {@link logfile} or/and {@link logemail},
 * to enable logging.
 *
 * Note: native Yii file path aliases will not work with this class, because they do not allow to refer a
 * specific file (not directory). However Yii alias with leading "@" will be recognized properly.
 * For example: '@application/runtime/some.file' will refer to './protected/runtime/some.file'.
 *
 * Note: the console application, which will run this command should be absolutely stripped from the local
 * configuration files and database.
 *
 * Note: {@link YiiRequirementsChecker} extension is integrated as a part of this command and required for it
 * correct execution.
 *
 * @see YiiRequirementsChecker
 *
 * @property array localDirectories public alias of {@link _localDirectories}.
 * @property array temporaryDirectories public alias of {@link _temporaryDirectories}.
 * @property array localFiles public alias of {@link _localFiles}.
 * @property array executeFiles public alias of {@link _executeFiles}.
 * @property array localFilePlaceholders public alias of {@link _localFilePlaceholders}.
 * @property string localFileExampleNamePattern public alias of {@link _localFileExampleNamePattern}.
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
     */
    public $localDirectories = [
        '@app/web/assets',
        '@runtime',
    ];
    /**
     * @var array list of temporary directories, which should be cleared during application initialization/update.
     */
    public $temporaryDirectories = [
        '@app/web/assets',
        '@runtime',
    ];
    /**
     * @var array list of local files, which should be created from the example files.
     */
    public $localFiles = [
        '@app/web/.htaccess',
        '@app/config/local.php',
        '@app/tests/phpunit.xml',
    ];
    /**
     * @var string pattern, which is used to determine example file name for the local file.
     * This pattern should contain "{filename}" placeholder, which will be replaced by local file self name.
     */
    public $localFileExampleNamePattern = '{filename}.sample';
    /**
     * @var array list of local file placeholders in format: 'placeholderName' => array(config).
     * Each placeholder value should be a valid configuration for {@link QsLocalFilePlaceholderModel}.
     */
    protected $_localFilePlaceholders = [];
    /**
     * @var array list of files, which should be executable.
     */
    public $executeFiles = [
        '@app/yii',
        '@app/install.php',
    ];
    /**
     * @var string requirements list file name.
     * @see YiiRequirementsChecker
     */
    public $requirementsFileName = '@app/requirements.php';
    /**
     * @var CronTab|array cron tab instance or its array configuration.
     * For example:
     * <code>
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
     * </code>
     */
    private $_cronTab = [];
    /**
     * @var boolean whether to output log messages via "stdout". Defaults to true.
     * Set this to false to false to cease console output.
     */
    public $outputlog = true;
    /**
     * @var string configuration file name. Settings from this file will be merged with the default ones.
     * Such configuration file can be created, using action 'generateConfig'.
     */
    public $config = '';
    /**
     * @var string name of the file, which collect the process logs.
     */
    public $logfile = '';
    /**
     * @var string email address, which should receive the process error logs,
     * it can be comma-separated email addresses.
     * Inside the config file this parameter can be specified as array.
     */
    public $logemail = '';


    /**
     * @param array $localFilePlaceholders
     * @return boolean success.
     */
    public function setLocalFilePlaceholders(array $localFilePlaceholders)
    {
        $this->_localFilePlaceholders = $localFilePlaceholders;
        return true;
    }

    /**
     * @return array
     */
    public function getLocalFilePlaceholders()
    {
        return $this->_localFilePlaceholders;
    }

    /**
     * @param CronTab|array $cronTab
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
        if (empty($this->logfile)) {
            return null;
        }

        return Yii::createObject([
            'class' => FileTarget::className(),
            'exportInterval' => 1,
            'categories' => [get_class($this) . '*'],
            'logFile' => $this->logfile,
        ]);
    }

    /**
     * Creates an email log target, if it is required.
     * @return EmailTarget|null email log target or null, if it is not required.
     */
    protected function createEmailLogTarget()
    {
        $logEmail = $this->logemail;
        if (empty($logEmail)) {
            return null;
        }

        @$hostName = exec('hostname');
        $sentFrom = Yii::$app->name . '@' . (empty($hostName) ? Yii::$app->name . '.com' : $hostName);

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
     * @inheritdoc
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
     * Logs message.
     * @param string $message the text message
     * @param integer $level log message level.
     * @return boolean success.
     */
    protected function log($message, $level = null)
    {
        if ($level === null) {
            $level = Logger::LEVEL_INFO;
        }
        if ($this->outputlog) {
            if ($level != Logger::LEVEL_INFO) {
                echo("\n[{$level}] {$message}\n");
            } else {
                echo($message);
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
        if (array_key_exists($placeholderName, $this->_localFilePlaceholders)) {
            return $this->_localFilePlaceholders[$placeholderName];
        } else {
            return [];
        }
    }

    /**
     * Performs all application initialize actions.
     * @param boolean $overwrite indicates, if existing local file should be overwritten in the process.
     */
    public function actionAll($overwrite = false)
    {
        $path = dirname(Yii::$app->basePath);
        if ($this->confirm("Initialize project under '{$path}'?")) {
            $this->log("Project initialization in progress...\n");
            if (!$this->actionRequirements(false)) {
                $this->log("Project initialization failed.", Logger::LEVEL_ERROR);
                return;
            }
            $this->actionLocalDir();
            $this->actionClearTmpDir();
            $this->actionExecuteFile();
            $this->actionLocalFile(null, $overwrite);
            $this->actionMigrate();
            $this->actionCrontab();
            $this->log("\nProject initialization is complete.\n");
        }
    }

    /**
     * Check if current system matches application requirements.
     * @param boolean $forceshowresult indicates if verbose check result should be displayed even,
     * if there is no errors or warnings.
     * @return boolean success.
     */
    public function actionRequirements($forceshowresult = true)
    {
        $this->log("Checking requirements...\n");
        $requirementsChecker = $this->createRequirementsChecker();
        //$requirementsChecker->checkYii();

        $requirementsFileName = Yii::getAlias($this->requirementsFileName);
        if (file_exists($requirementsFileName)) {
            $requirementsChecker->check($requirementsFileName);
        } else {
            $this->log("Requirements list file '{$requirementsFileName}' does not exist, only default requirements checking is available.", Logger::LEVEL_WARNING);
        }

        $requirementsCheckResult = $requirementsChecker->getResult();
        if ($requirementsCheckResult['summary']['errors'] > 0) {
            $this->log("Requirements check fails with errors.", Logger::LEVEL_ERROR);
            $requirementsChecker->render();
            return false;
        } elseif ($requirementsCheckResult['summary']['warnings'] > 0) {
            $this->log("Requirements check passed with warnings.", Logger::LEVEL_WARNING);
            $requirementsChecker->render();
            return false;
        } else {
            $this->log("Requirements check successful.\n");
            if ($forceshowresult) {
                $requirementsChecker->render();
            }
            return true;
        }
    }

    /**
     * Creates all local directories and makes sure they are writeable for the web server.
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
    }

    /**
     * Clears temporary directories, avoiding special files such as ".htaccess" and VCS files.
     * @param string $dir directory name.
     */
    public function actionClearTmpDir($dir = null)
    {
        if (!empty($dir) || $this->confirm('Clear all temporary directories?')) {
            $this->log("\nClearing temporary directories:\n");
            $temporaryDirectories = $this->temporaryDirectories;
            $excludeNames = array(
                '.htaccess',
                '.svn',
                '.gitignore',
                '.gitkeep',
                '.hgignore',
                '.hgkeep',
            );
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
    }

    /**
     * Change permissions for the specific files, making them executable.
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
    }

    /**
     * Runs the database migration command.
     */
    public function actionMigrate()
    {
        if ($this->confirm('Run database migration command from here?')) {
            $this->log("Running database migration:\n");
            $scriptFileName = Yii::getAlias('@app/yii');
            $command = "php -f {$scriptFileName} migrate up --interactive=0";
            exec($command, $outputRows);
            $this->log(implode("\n", $outputRows));
            $this->log("\n");
        }
    }

    /**
     * Sets up the project cron jobs.
     */
    public function actionCrontab()
    {
        $cronTab = $this->getCronTab();
        if (!is_object($cronTab)) {
            $this->log("There are no cron jobs to setup.\n");
            return;
        }
        $cronJobs = $cronTab->getJobs();
        if (empty($cronJobs)) {
            $this->log("There are no cron jobs to setup.\n");
        } else {
            @$userName = exec('whoami');
            if (empty($userName)) {
                $userName = 'unknown';
            }
            if ($this->confirm("Setup the cron jobs for the user '{$userName}'?")) {
                $this->log("Setting up cron jobs:\n");
                $cronTab->apply();
                $this->log("crontab is set for the user '{$userName}'\n");
            }
        }
    }

    /**
     * Creates new local files from example files.
     * @param string $file name of the particular local file, if empty all local files will be processed.
     * @param boolean $overwrite indicates, if existing local file should be overwritten in the process.
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
    }

    /**
     * Generates new configuration file, which can be used to run
     * application initialization.
     * @param string $file output config file name.
     * @param boolean $overwrite indicates, if existing configuration file should be overwritten in the process.
     */
    public function actionGenerateConfig($file = 'init.cfg.php', $overwrite = false)
    {
        $fileName = $file;
        if (file_exists($fileName)) {
            if (!$overwrite) {
                if (!$this->confirm("Configuration file '{$file}' already exists, do you wish to overwrite it?")) {
                    return;
                }
            }
        }

        $configPropertyNames = array(
            'interactive',
            'logfile',
            'logemail',
            'localDirectories',
            'executeFiles',
            'localFileExampleNamePattern',
            'localFiles',
            'localFilePlaceholders',
        );
        $config = array();
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
        } else {
            $this->log("Unable to create configuration file '{$file}'!", Logger::LEVEL_ERROR);
        }
    }

    /**
     * Creates new local file from example file.
     * @param string $localFileName local file full name.
     * @param string $exampleFileName example file full name.
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
                        echo "Error: invalid value entered:\n";
                        echo $model->getErrorSummary() . "\n";
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
                return;
            }
        }
        file_put_contents($localFileName, $localFileContent);
        if (file_exists($localFileName)) {
            $this->log("Local file '{$localFileName}' has been created.\n");
        } else {
            $this->log("Unable to create local file '{$localFileName}'!", Logger::LEVEL_ERROR);
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
     * @return boolean success.
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
