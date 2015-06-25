<?php
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2tech\install;

use Yii;
use yii\console\Controller;
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
     * @var array list of Yii application paths of alias, used in auto load process.
     * This field is for internal usage only.
     */
    protected $_aliasPaths = array();
    /**
     * @var array list of local directories, which should be created and available to write by web server.
     */
    protected $_localDirectories = array(
        '@webroot/assets',
        '@application/runtime',
    );
    /**
     * @var array list of temporary directories, which should be cleared during application initialization/update.
     */
    protected $_temporaryDirectories = array(
        '@webroot/assets',
        '@application/runtime',
    );
    /**
     * @var array list of local files, which should be created from the example files.
     */
    protected $_localFiles = array(
        '@webroot/.htaccess',
        '@application/config/local.php',
        '@application/tests/phpunit.xml',
    );
    /**
     * @var string pattern, which is used to determine example file name for the local file.
     * This pattern should contain "{filename}" placeholder, which will be replaced by local file self name.
     */
    protected $_localFileExampleNamePattern = '{filename}.sample';
    /**
     * @var array list of local file placeholders in format: 'placeholderName' => array(config).
     * Each placeholder value should be a valid configuration for {@link QsLocalFilePlaceholderModel}.
     */
    protected $_localFilePlaceholders = array();
    /**
     * @var array list of files, which should be executable.
     */
    protected $_executeFiles = array(
        '@application/yiic',
        '@application/install.php',
    );
    /**
     * @var string requirements list file name.
     * @see YiiRequirementsChecker
     */
    protected $_requirementsFileName = '@application/requirements.php';
    /**
     * @var QsCronTab|array cron tab instance or its array configuration.
     * For example:
     * <code>
     * array(
     *     'jobs' => array(
     *         array(
     *             'min' => '0',
     *             'hour' => '0',
     *             'command' => 'php /path/to/project/protected/yiic somecron',
     *         ),
     *         array(
     *             'line' => '0 0 * * * php /path/to/project/protected/yiic anothercron'
     *         )
     *     ),
     * );
     * </code>
     */
    protected $_cronTab = array();
    /**
     * @var boolean whether to output log messages via "stdout". Defaults to true.
     * Set this to false to false to cease console output.
     */
    public $outputlog = true;
    /**
     * @var boolean whether to execute command in an interactive mode. Defaults to true.
     * Set this to false to run the process in background or by another script.
     */
    public $interactive = true;
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

    public function setLocalDirectories(array $localDirectories)
    {
        $this->_localDirectories = $localDirectories;
        return true;
    }

    public function getLocalDirectories()
    {
        return $this->_localDirectories;
    }

    /**
     * @param array $temporaryDirectories
     * @return boolean success.
     */
    public function setTemporaryDirectories($temporaryDirectories)
    {
        $this->_temporaryDirectories = $temporaryDirectories;
        return true;
    }

    /**
     * @return array
     */
    public function getTemporaryDirectories()
    {
        return $this->_temporaryDirectories;
    }

    public function setLocalFiles(array $localFiles)
    {
        $this->_localFiles = $localFiles;
        return true;
    }

    public function getLocalFiles()
    {
        return $this->_localFiles;
    }

    public function setLocalFileExampleNamePattern($localFileExampleNamePattern)
    {
        if (!is_string($localFileExampleNamePattern)) {
            throw new CException('"' . get_class($this) . '::localFileExampleNamePattern" should be a string.');
        }
        $this->_localFileExampleNamePattern = $localFileExampleNamePattern;
        return true;
    }

    public function getLocalFileExampleNamePattern()
    {
        return $this->_localFileExampleNamePattern;
    }

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

    public function setExecuteFiles($executeFiles)
    {
        $this->_executeFiles = $executeFiles;
        return true;
    }

    public function getExecuteFiles()
    {
        return $this->_executeFiles;
    }

    public function setRequirementsFileName($requirementsFileName)
    {
        $this->_requirementsFileName = $requirementsFileName;
        return true;
    }

    public function getRequirementsFileName()
    {
        return $this->_requirementsFileName;
    }

    /**
     * @param CronTab|array $cronTab
     */
    public function setCronTab($cronTab)
    {
        $this->_cronTab = $cronTab;
    }

    /**
     * @throws CException
     * @return CronTab
     */
    public function getCronTab()
    {
        if (!is_object($this->_cronTab)) {
            if (!is_array($this->_cronTab)) {
                throw new CException('"' . get_class($this) . '::cronTab" should be instance of "zfort\cron\CronTab" or its array configuration.');
            } else {
                if (empty($this->_cronTab['class'])) {
                    $this->_cronTab['class'] = 'zfort\cron\CronTab';
                }
                $this->_cronTab = Yii::createComponent($this->_cronTab);
            }
        }
        return $this->_cronTab;
    }

    /**
     * Initializes the command object.
     * This method is invoked after a command object is created and initialized with configurations.
     */
    public function init()
    {
        parent::init();
        Yii::setPathOfAlias('webroot', dirname(Yii::getPathOfAlias('application')));
    }

    /**
     * Initializes and adjusts the log process.
     * @return boolean success.
     */
    public function initLog()
    {
        Yii::getLogger()->autoFlush = 1;
        Yii::getLogger()->autoDump = true;

        $logRoutes = array();
        if ($fileLogRoute = $this->createFileLogRoute()) {
            $logRoutes[] = $fileLogRoute;
        }
        if ($emailLogRoute = $this->createEmailLogRoute()) {
            $logRoutes[] = $emailLogRoute;
        }

        if (!empty($logRoutes)) {
            if (!Yii::app()->hasComponent('log')) {
                $logComponentConfig = array(
                    'class' => 'CLogRouter',
                );
                $logComponent = Yii::createComponent($logComponentConfig);
                $logComponent->init();
                Yii::app()->setComponent('log', $logComponent);
            }
            $logComponent = Yii::app()->getComponent('log');
            $logComponent->setRoutes($logRoutes);
        }

        return true;
    }

    /**
     * Creates a file log route, if it is required.
     * @return \CFileLogRoute|null file log route or null, if it is not required.
     */
    protected function createFileLogRoute()
    {
        $logFullFileName = $this->logfile;
        if (empty($logFullFileName)) {
            return null;
        }
        $logPath = dirname($logFullFileName);
        $logFile = basename($logFullFileName);

        if (!empty($logPath)) {
            if (!file_exists($logPath)) {
                mkdir($logPath, null, true);
            }
        }

        $logRouteConfig = array(
            'class' => 'CFileLogRoute',
            'categories' => 'qs.console.*',
            'logPath' => $logPath,
            'logFile' => $logFile,
        );
        $logRoute = Yii::createComponent($logRouteConfig);
        $logRoute->init();
        return $logRoute;
    }

    /**
     * Creates an email log route, if it is required.
     * @return \CEmailLogRoute|null email log route or null, if it is not required.
     */
    protected function createEmailLogRoute()
    {
        $logEmail = $this->logemail;
        if (empty($logEmail)) {
            return null;
        }

        @$hostName = exec('hostname');
        $sentFrom = Yii::app()->name . '@' . (empty($hostName) ? Yii::app()->name.'.com' : $hostName);

        $logRouteConfig = array(
            'class' => 'CEmailLogRoute',
            'levels' => CLogger::LEVEL_ERROR . ',' . CLogger::LEVEL_WARNING,
            'subject' => 'Application "' . Yii::app()->name . '" initialization error at ' . $hostName,
            'sentFrom' => $sentFrom,
            'emails' => $logEmail,
        );
        $logRoute = Yii::createComponent($logRouteConfig);
        $logRoute->init();
        return $logRoute;
    }

    /**
     * This method is invoked right before an action is to be executed.
     * @param string $action the action name
     * @param array $params the parameters to be passed to the action method.
     * @return boolean whether the action should be executed.
     */
    protected function beforeAction($action, $params)
    {
        if (!empty($this->config)) {
            $this->populateFromConfigFile($this->config);
        }
        $this->initLog();
        return parent::beforeAction($action, $params);
    }

    /**
     * Asks user to confirm by typing y or n.
     * @param string $message to echo out before waiting for user input.
     * @param boolean $default this value is returned if no selection is made.
     * @return boolean if user confirmed.
     */
    public function confirm($message, $default = false)
    {
        if (!$this->interactive) {
            return true;
        }
        return parent::confirm($message, $default);
    }

    /**
     * Reads input via the readline PHP extension if that's available, or fgets() if readline is not installed.
     * @param string $message to echo out before waiting for user input.
     * @param string $default the default string to be returned when user does not write anything.
     * @return mixed line read as a string, or false if input has been closed.
     */
    public function prompt($message, $default = null)
    {
        if (!$this->interactive) {
            return $default;
        }
        return parent::prompt($message, $default);
    }

    /**
     * Logs message.
     * @param string $message the text message
     * @param integer $level log message level.
     * @return boolean success.
     */
    protected function log($message, $level = null)
    {
        if ($level===null) {
            $level = CLogger::LEVEL_INFO;
        }
        if ($this->outputlog) {
            if ($level != CLogger::LEVEL_INFO) {
                echo("\n[{$level}] {$message}\n");
            } else {
                echo($message);
            }
        }
        $message = trim($message,"\n");
        if (!empty($message)) {
            Yii::log($message, $level, 'qs.console.commands.' . get_class($this));
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
            return array();
        }
    }

    /**
     * Provides the daemon command description.
     * This method may be overridden to return the actual daemon command description.
     * @return string the daemon command description. Defaults to 'Usage: php entry-script.php command-name'.
     */
    public function getHelp()
    {
        $help = "Usage: ";

        $classReflection = new \ReflectionClass(get_class($this));

        $commandName = $this->getName();
        $commandShellString = $this->getCommandRunner()->getScriptName() . ' ' . $commandName;

        $options = $this->getOptionHelp();
        if (empty($options)) {
            $options = array(
                $this->defaultAction
            );
        }

        $usageOptions = array();
        $usageDescriptions = array();

        foreach ($options as $option) {
            $usageOptionString = $commandShellString;
            $usageDescriptionString = 'Description';

            if (strcmp($option, $this->defaultAction) != 0) {
                $usageOptionString .= ' '.$option;
                $usageDescriptionString .= " <{$commandName} {$option}>";
            } else {
                $usageDescriptionString .= " <{$commandName}>";
            }
            $usageOptions[] = $usageOptionString;

            list($actionMethodName) = explode(' ', $option);
            $actionMethodReflection = $classReflection->getMethod('action' . $actionMethodName);
            $searches = array(
                '*',
                '/',
                '@param ',
                "\n",
                "\r",
                "\t",
                '@return boolean success.',
                '@return boolean success',
            );
            $actionMethodDescription = str_replace($searches, '', $actionMethodReflection->getDocComment());
            $actionMethodDescription = str_replace('$', '--', $actionMethodDescription);

            $usageDescriptionString .= ":\n" . $actionMethodDescription;

            $usageDescriptions[] = $usageDescriptionString;
        }

        // Options:
        $help .= implode("\n   or: ", $usageOptions);
        $help .= "\n\n";

        // Global Parameters:
        $help .= "Possible arguments: \n";

        $publicProperties = $classReflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        if (is_array($publicProperties)) {
            $skippedPublicProperties = array(
                'defaultAction'
            );
            foreach ($publicProperties as $publicProperty) {
                if (in_array($publicProperty->getName(), $skippedPublicProperties, true)) {
                    continue;
                }
                $propertyHelpString = '  --' . $publicProperty->getName();

                $searches = array(
                    '*',
                    '/',
                    '@var ',
                    "\n",
                    "\r",
                    "\t",
                );
                $propertyDescription = str_replace($searches, '', $publicProperty->getDocComment());

                $propertyDescriptionOffset = 18;
                $propertyHelpString .= str_pad('', $propertyDescriptionOffset-strlen($propertyHelpString), ' ');
                $propertyHelpString .= $propertyDescription . "\n";

                $help .= $propertyHelpString;
            }
        }

        $help .= "\n";

        // Description:
        $help .= implode("\n\n", $usageDescriptions);
        $help .= "\n\n";

        return $help;
    }

    /**
     * Returns the real path for the given aliased and relative path.
     * Incoming file path can contain references for the Yii path aliases
     * in format: "alias:", for example: 'webroot:/.htaccess'.
     * @param string $rawFilePath raw file path.
     * @return string real file path.
     */
    protected function getRealFilePath($rawFilePath)
    {
        if (empty($this->_aliasPaths)) {
            $aliases = array(
                'webroot',
                'application',
                'ext',
            );
            foreach ($aliases as $alias) {
                $this->_aliasPaths['@' . $alias] = Yii::getPathOfAlias($alias);
            }
        }
        $realFilePath = strtr($rawFilePath, $this->_aliasPaths);
        return $realFilePath;
    }

    /**
     * Performs all application initialize actions.
     * @param boolean $overwrite indicates, if existing local file should be overwritten in the process.
     */
    public function actionAll($overwrite = false)
    {
        $path = dirname(Yii::app()->basePath);
        if ($this->confirm("Initialize application under '{$path}'?")) {
            $this->log("Application initialization in progress...\n");
            if (!$this->actionRequirements(false)) {
                $this->log("Application initialization failed.", CLogger::LEVEL_ERROR);
                return;
            }
            $this->actionLocalDir();
            $this->actionClearTmpDir();
            $this->actionExecuteFile();
            $this->actionLocalFile(null, $overwrite);
            $this->actionMigrate();
            $this->actionCrontab();
            $this->log("\nApplication initialization is complete.\n");
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

        $requirementsFileName = $this->getRealFilePath($this->getRequirementsFileName());
        if (file_exists($requirementsFileName)) {
            $requirementsChecker->check($requirementsFileName);
        } else {
            $this->log("Requirements list file '{$requirementsFileName}' does not exist, only default requirements checking is available.", CLogger::LEVEL_WARNING);
        }

        $requirementsCheckResult = $requirementsChecker->getResult();
        if ($requirementsCheckResult['summary']['errors'] > 0) {
            $this->log("Requirements check fails with errors.", CLogger::LEVEL_ERROR);
            $requirementsChecker->render();
            return false;
        } elseif ($requirementsCheckResult['summary']['warnings'] > 0) {
            $this->log("Requirements check passed with warnings.", CLogger::LEVEL_WARNING);
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
        foreach ($this->getLocalDirectories() as $directory) {
            $directoryPath = $this->getRealFilePath($directory);
            if (!file_exists($directoryPath)) {
                $this->log("\nCreating directory '{$directoryPath}'...");
                if (mkdir($directoryPath, $filePermissions, true)) {
                    $this->log("complete.\n");
                } else {
                    $this->log("Unable to create directory '{$directoryPath}'!", CLogger::LEVEL_ERROR);
                }
            }
            $this->log("Setting permissions '" . decoct($filePermissions) . "' for '{$directoryPath}'...");
            if (chmod($directoryPath, $filePermissions)) {
                $this->log("complete.\n");
            } else {
                $this->log("Unable to set permissions '" . decoct($filePermissions) . "' for '{$directoryPath}'!", CLogger::LEVEL_ERROR);
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
            $temporaryDirectories = $this->getTemporaryDirectories();
            $excludeNames = array(
                '.htaccess',
                '.svn',
                '.gitignore',
                '.gitkeep',
                '.hgignore',
                '.hgkeep',
            );
            foreach ($temporaryDirectories as $temporaryDirectory) {
                $tmpDirFullName = $this->getRealFilePath($temporaryDirectory);
                if ($dir!==null && (strpos($tmpDirFullName,$dir)===false)) {
                    continue;
                }
                if (!is_dir($tmpDirFullName)) {
                    $this->log("Directory '{$tmpDirFullName}' does not exists!", CLogger::LEVEL_WARNING);
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
                    $this->deleteFileSystemObject($tmpDirFullName . DIRECTORY_SEPARATOR . $fileSystemObjectName);
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
        foreach ($this->getExecuteFiles() as $fileName) {
            $this->log("Setting permissions '" . decoct($filePermissions) . "' for '{$fileName}'...");
            $fileRealName = $this->getRealFilePath($fileName);
            if (chmod($fileRealName, $filePermissions)) {
                $this->log("complete.\n");
            } else {
                $this->log("Unable to set permissions '" . decoct($filePermissions) . "' for '{$fileRealName}'!", CLogger::LEVEL_ERROR);
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
            $scriptFileName = Yii::getPathOfAlias('application') . DIRECTORY_SEPARATOR . 'yiic';
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
        foreach ($this->getLocalFiles() as $localFileRawName) {
            $localFileRealName = $this->getRealFilePath($localFileRawName);
            if ($file !== null && (strpos($localFileRealName, $file) === false)) {
                continue;
            }
            $this->log("\nProcessing local file '{$localFileRealName}':\n");

            $exampleFileName = $this->getExampleFileName($localFileRealName);
            if (!file_exists($exampleFileName)) {
                $this->log("Unable to find example for the local file '{$localFileRealName}': file '{$exampleFileName}' does not exists!", CLogger::LEVEL_ERROR);
            }
            if (file_exists($localFileRealName)) {
                if (filemtime($exampleFileName) > filemtime($localFileRealName)) {
                    $this->log("Local file '{$localFileRealName}' is out of date and should be regenerated.", CLogger::LEVEL_WARNING);
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

        $fileContent = "<?php\nreturn " . var_export($config, true) . ";";
        if (file_exists($fileName)) {
            if (unlink($fileName)) {
                $this->log("Old version of the configuration file '{$file}' has been removed.\n");
            } else {
                $this->log("Unable to remove old version of the configuration file '{$file}'!", CLogger::LEVEL_ERROR);
            }
        }
        file_put_contents($fileName, $fileContent);
        if (file_exists($fileName)) {
            $this->log("Configuration file '{$file}' has been created.\n");
        } else {
            $this->log("Unable to create configuration file '{$file}'!", CLogger::LEVEL_ERROR);
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
        $placeholders = array();
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
            } catch (CException $exception) {
                $this->log($exception->getMessage(), CLogger::LEVEL_ERROR);
            }
        }
        $localFileContent = $this->composeLocalFileContent($exampleFileName, $placeholders);
        if (file_exists($localFileName)) {
            $this->log("Removing old version of file '{$localFileName}'...");
            if (unlink($localFileName)) {
                $this->log("complete.\n");
            } else {
                $this->log("Unable to remove old version of file '{$localFileName}'!", CLogger::LEVEL_ERROR);
                return;
            }
        }
        file_put_contents($localFileName, $localFileContent);
        if (file_exists($localFileName)) {
            $this->log("Local file '{$localFileName}' has been created.\n");
        } else {
            $this->log("Unable to create local file '{$localFileName}'!", CLogger::LEVEL_ERROR);
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
        $localFileExampleNamePattern = $this->getLocalFileExampleNamePattern();
        $localFileExampleSelfName = str_replace('{filename}', $localFileSelfName, $localFileExampleNamePattern);
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
            $placeholders = array();
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
        $replacePairs = array();
        foreach ($placeholders as $name => $value) {
            $replacePairs['{{' . $name . '}}'] = $value;
        }
        return strtr($exampleFileContent, $replacePairs);
    }

    /**
     * Populates console command instance from configuration file.
     * @param string $configFileName configuration file name.
     * @return boolean success.
     * @throws CException on wrong configuration file.
     */
    public function populateFromConfigFile($configFileName)
    {
        $configFileName = realpath($this->getRealFilePath($configFileName));
        if (!file_exists($configFileName)) {
            throw new CException("Unable to read configuration file '{$configFileName}': file does not exist!");
        }

        $configFileExtension = CFileHelper::getExtension($configFileName);
        switch ($configFileExtension) {
            case 'php': {
                $configData = $this->extractConfigFromFilePhp($configFileName);
                break;
            }
            default: {
            throw new CException("Configuration file has unknown type: '{$configFileExtension}'!");
            }
        }

        if (!is_array($configData)) {
            throw new CException("Unable to read configuration from file '{$configFileName}': wrong file format!");
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
     * Deletes file system object (file or directory).
     * @param string $fileSystemObjectFullName file system object full name.
     */
    protected function deleteFileSystemObject($fileSystemObjectFullName)
    {
        if (!is_dir($fileSystemObjectFullName)) {
            if (!@unlink($fileSystemObjectFullName)) {
                $this->log("Unable to delete file '{$fileSystemObjectFullName}'!", CLogger::LEVEL_WARNING);
            }
        } else {
            $dirHandle = opendir($fileSystemObjectFullName);
            while (($fileSystemObjectName = readdir($dirHandle)) !== false) {
                if ($fileSystemObjectName === '.' || $fileSystemObjectName === '..') {
                    continue;
                }
                $this->deleteFileSystemObject($fileSystemObjectFullName . DIRECTORY_SEPARATOR . $fileSystemObjectName);
            }
            closedir($dirHandle);
            if (!@rmdir($fileSystemObjectFullName)) {
                $this->log("Unable to delete directory '{$fileSystemObjectFullName}'!", CLogger::LEVEL_WARNING);
            }
        }
    }

    /**
     * Creates requirements checker instance.
     * @return YiiRequirementChecker requirements checker instance.
     */
    protected function createRequirementsChecker()
    {
        Yii::import('application.vendor.yiisoft.yii2.requirements.YiiRequirementChecker', true);
        return new YiiRequirementChecker();
    }
}
