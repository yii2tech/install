<?php

namespace yii2tech\tests\unit\install;

use Yii;
use yii2tech\install\InitController;

/**
 * Test case for the extension "yii2tech\install\InitController".
 * @see InitController
 */
class InitControllerTest extends TestCase
{
    protected static $_logComponentBackup = null;

    public static function setUpBeforeClass()
    {
        $testFilePath = self::getTestFilePath();
        if (!file_exists($testFilePath)) {
            mkdir($testFilePath, 0777, true);
        }

        if (Yii::app()->hasComponent('log')) {
            self::$_logComponentBackup = clone Yii::app()->getComponent('log');
        }
    }

    public static function tearDownAfterClass()
    {
        $testFilePath = self::getTestFilePath();
        if (file_exists($testFilePath)) {
            exec("rm -rf {$testFilePath}");
        }
        if (is_object(self::$_logComponentBackup)) {
            Yii::app()->setComponent('log', self::$_logComponentBackup);
        }
    }

    /**
     * Creates test console command instance.
     * @return InitController console command instance.
     */
    protected function createConsoleCommand()
    {
        $consoleCommand = new InitController('install', null);
        $consoleCommand->interactive = false;
        $consoleCommand->outputlog = false;
        return $consoleCommand;
    }

    /**
     * Returns the test file path.
     * @return string test file path.
     */
    protected static function getTestFilePath()
    {
        return Yii::getPathOfAlias('application.runtime') . DIRECTORY_SEPARATOR . __CLASS__ . getmypid();
    }

    // Tests:

    public function testSetGet()
    {
        $consoleCommand = $this->createConsoleCommand();

        $testLocalFileExampleNamePattern = 'test_local_file_example_pattern';
        $this->assertTrue($consoleCommand->setLocalFileExampleNamePattern($testLocalFileExampleNamePattern), 'Unable to set local file example name pattern!');
        $this->assertEquals($testLocalFileExampleNamePattern, $consoleCommand->getLocalFileExampleNamePattern(), 'Unable to set local file example name pattern correctly!');

        $testLocalDirectories = array(
            '/test/local/dir1',
            '/test/local/dir2',
        );
        $this->assertTrue($consoleCommand->setLocalDirectories($testLocalDirectories), 'Unable to set local directories!');
        $this->assertEquals($testLocalDirectories, $consoleCommand->getLocalDirectories(), 'Unable to set local directories correctly!');

        $testTemporaryDirectories = array(
            '/test/tmp/dir1',
            '/test/tmp/dir2',
        );
        $this->assertTrue($consoleCommand->setTemporaryDirectories($testTemporaryDirectories), 'Unable to set temporary directories!');
        $this->assertEquals($testTemporaryDirectories, $consoleCommand->getTemporaryDirectories(), 'Unable to set temporary directories correctly!');

        $testLocalFiles = array(
            '/test/local/file1',
            '/test/local/file2',
        );
        $this->assertTrue($consoleCommand->setLocalFiles($testLocalFiles), 'Unable to set local files!');
        $this->assertEquals($testLocalFiles, $consoleCommand->getLocalFiles(), 'Unable to set local files correctly!');

        $localFilePlaceholders = array(
            'test_placeholder_name_1' => array(
                'default' => 'test_default_1'
            ),
            'test_placeholder_name_2' => array(
                'default' => 'test_default_2'
            ),
        );
        $this->assertTrue($consoleCommand->setLocalFilePlaceholders($localFilePlaceholders), 'Unable to set local file placeholders!');
        $this->assertEquals($localFilePlaceholders, $consoleCommand->getLocalFilePlaceholders(), 'Unable to set local file placeholders correctly!');

        $testExecuteFiles = array(
            '/test/execute/file1',
            '/test/execute/file2',
        );
        $this->assertTrue($consoleCommand->setExecuteFiles($testExecuteFiles), 'Unable to set execute files!');
        $this->assertEquals($testExecuteFiles, $consoleCommand->getExecuteFiles(), 'Unable to set execute files correctly!');

        $testRequirementsFileName = '/test/requirements/file/name.php';
        $this->assertTrue($consoleCommand->setRequirementsFileName($testRequirementsFileName), 'Unable to set requirements file name!');
        $this->assertEquals($testRequirementsFileName, $consoleCommand->getRequirementsFileName(), 'Unable to set requirements file name correctly!');
    }

    /**
     * @depends testSetGet
     */
    public function testActionLocalDir()
    {
        $consoleCommand = $this->createConsoleCommand();

        $testLocalDirectory = self::getTestFilePath() . DIRECTORY_SEPARATOR . 'test_directory';
        $consoleCommand->setLocalDirectories(array($testLocalDirectory));

        $consoleCommand->actionLocalDir();

        $this->assertTrue(file_exists($testLocalDirectory), 'Unable to create local directory!');
    }

    /**
     * @depends testSetGet
     */
    public function testActionLocalFile()
    {
        $consoleCommand = $this->createConsoleCommand();

        $testLocalFileSelfName = 'test_file.php';
        $testLocalFileFullName = self::getTestFilePath() . DIRECTORY_SEPARATOR . $testLocalFileSelfName;
        $consoleCommand->setLocalFiles(array($testLocalFileFullName));

        $testExampleFileSelfName = str_replace('{filename}', $testLocalFileSelfName, $consoleCommand->getLocalFileExampleNamePattern());
        $testExampleFileFullName = self::getTestFilePath() . DIRECTORY_SEPARATOR . $testExampleFileSelfName;
        $testExampleFileContent = 'Some test content.';
        file_put_contents($testExampleFileFullName, $testExampleFileContent);

        $consoleCommand->actionLocalFile();

        $this->assertTrue(file_exists($testLocalFileFullName), 'Unable to create local file!');
    }

    /**
     * @depends testActionLocalFile
     */
    public function testActionLocalFileDefaultPlaceholderValues()
    {
        $consoleCommand = $this->createConsoleCommand();

        $testPlaceholderName = 'test_placeholder_name';
        $testPlaceholderValue = 'test_placeholder_value';
        $testLocalFilePlaceholders = array(
            $testPlaceholderName => array(
                'default' => $testPlaceholderValue
            )
        );
        $consoleCommand->setLocalFilePlaceholders($testLocalFilePlaceholders);

        $testLocalFileSelfName = 'test_file_default_values.php';
        $testLocalFileFullName = self::getTestFilePath() . DIRECTORY_SEPARATOR . $testLocalFileSelfName;
        $consoleCommand->setLocalFiles(array($testLocalFileFullName));

        $testExampleFileSelfName = str_replace('{filename}', $testLocalFileSelfName, $consoleCommand->getLocalFileExampleNamePattern());
        $testExampleFileFullName = self::getTestFilePath() . DIRECTORY_SEPARATOR . $testExampleFileSelfName;
        $testExampleFileContent = 'Some {{'.$testPlaceholderName.'}} content.';
        file_put_contents($testExampleFileFullName, $testExampleFileContent);

        $consoleCommand->actionLocalFile();

        $this->assertTrue(file_exists($testLocalFileFullName), 'Unable to create local file!');

        $localFileContent = file_get_contents($testLocalFileFullName);
        $this->assertContains($testPlaceholderValue, $localFileContent, 'Unable to replace placeholder by default value!');
    }

    /**
     * @depends testActionLocalFile
     */
    public function testActionLocalFileOverwrite()
    {
        $consoleCommand = $this->createConsoleCommand();

        $testLocalFileSelfName = 'test_file.php';
        $testLocalFileFullName = self::getTestFilePath() . DIRECTORY_SEPARATOR . $testLocalFileSelfName;
        $consoleCommand->setLocalFiles(array($testLocalFileFullName));

        $testExampleFileSelfName = str_replace('{filename}', $testLocalFileSelfName, $consoleCommand->getLocalFileExampleNamePattern());
        $testExampleFileFullName = self::getTestFilePath() . DIRECTORY_SEPARATOR . $testExampleFileSelfName;

        $testExampleFileContent = 'Some test content.';
        file_put_contents($testExampleFileFullName, $testExampleFileContent);

        $consoleCommand->actionLocalFile();

        $initialLocalFileTimestamp = filemtime($testLocalFileFullName);

        sleep(1);
        $consoleCommand->actionLocalFile(null, true);

        $overwrittenLocalFileTimestamp = filemtime($testLocalFileFullName);
        $this->assertTrue($overwrittenLocalFileTimestamp>$initialLocalFileTimestamp, 'Unable to override local file!');
    }

    /**
     * @depends testActionLocalFile
     */
    public function testActionLocalFileAutoOverwrite()
    {
        $consoleCommand = $this->createConsoleCommand();

        $testLocalFileSelfName = 'test_file.php';
        $testLocalFileFullName = self::getTestFilePath() . DIRECTORY_SEPARATOR . $testLocalFileSelfName;
        $consoleCommand->setLocalFiles(array($testLocalFileFullName));

        $testExampleFileSelfName = str_replace('{filename}', $testLocalFileSelfName, $consoleCommand->getLocalFileExampleNamePattern());
        $testExampleFileFullName = self::getTestFilePath() . DIRECTORY_SEPARATOR . $testExampleFileSelfName;

        $testExampleFileContent = 'Some test content.';
        file_put_contents($testExampleFileFullName, $testExampleFileContent);

        $consoleCommand->actionLocalFile();

        sleep(1);
        $testExampleFileContentOverridden = 'Some test content overridden.';
        file_put_contents($testExampleFileFullName, $testExampleFileContentOverridden);
        $consoleCommand->actionLocalFile();

        $localFileContent = file_get_contents($testLocalFileFullName);
        $this->assertEquals($testExampleFileContentOverridden, $localFileContent, 'Unable to override out of date local file automatically!');
    }

    /**
     * @depends testSetGet
     */
    public function testPopulateFromConfigFile()
    {
        $consoleCommand = $this->createConsoleCommand();

        $testFieldName = 'localFileExampleNamePattern';
        $testFieldValue = 'test_local_file_example_name_pattern';
        $testConfig = array(
            $testFieldName => $testFieldValue
        );

        $testConfigFileName = self::getTestFilePath() . DIRECTORY_SEPARATOR . 'test_config.php';
        $testConfigFileContent = '<?php return ' . var_export($testConfig, true) . ';';
        file_put_contents($testConfigFileName, $testConfigFileContent);

        $this->assertTrue($consoleCommand->populateFromConfigFile($testConfigFileName), 'Unable to populate from file!');

        $this->assertEquals($testFieldValue, $consoleCommand->$testFieldName, 'Unable to setup field, while populating from file!');
    }

    /**
     * @depends testSetGet
     */
    public function testActionExecuteFile()
    {
        $consoleCommand = $this->createConsoleCommand();

        $testFileName = self::getTestFilePath() . DIRECTORY_SEPARATOR . 'test_execute_file.php';
        file_put_contents($testFileName, 'some executable content');

        $testExecuteFiles = array(
            $testFileName
        );
        $consoleCommand->setExecuteFiles($testExecuteFiles);

        $consoleCommand->actionExecuteFile();

        $filePermissions = substr(sprintf('%o', fileperms($testFileName)), -4);
        $this->assertEquals('0755', $filePermissions, 'Wrong execute file permissions!');
    }

    /**
     * @depends testSetGet
     */
    public function testActionGenerateConfig()
    {
        $consoleCommand = $this->createConsoleCommand();

        $testConfigFileName = self::getTestFilePath() . DIRECTORY_SEPARATOR . 'test_config_file.php';

        $consoleCommand->actionGenerateConfig($testConfigFileName);

        $this->assertTrue(file_exists($testConfigFileName), 'Unable to generate configuration file!');

        @$configData = require($testConfigFileName);
        $this->assertTrue(is_array($configData), 'Unable to read data from config!');

        foreach ($configData as $name => $value) {
            $this->assertEquals($value, $consoleCommand->$name, 'Config parameter does not match the console command instance!');
        }
    }

    /**
     * @depends testActionGenerateConfig
     */
    public function testLogFile()
    {
        $consoleCommand = $this->createConsoleCommand();

        $testLogFileName = self::getTestFilePath() . DIRECTORY_SEPARATOR . 'test_log_file.log';
        $consoleCommand->logfile = $testLogFileName;
        $consoleCommand->initLog();

        $testConfigFileName = self::getTestFilePath() . DIRECTORY_SEPARATOR . 'test_config_file.php';
        $consoleCommand->actionGenerateConfig($testConfigFileName);

        $this->assertTrue(file_exists($testLogFileName), 'Unable to generate log file!');
    }

    /**
     * @depends testSetGet
     */
    public function testActionClearTmpDir()
    {
        $consoleCommand = $this->createConsoleCommand();

        $testTemporaryDirectory = self::getTestFilePath() . DIRECTORY_SEPARATOR . 'test_tmp_directory';
        mkdir($testTemporaryDirectory, 0777, true);
        $consoleCommand->setTemporaryDirectories(array($testTemporaryDirectory));

        $testTmpFileFullName = $testTemporaryDirectory . DIRECTORY_SEPARATOR . 'test_tmp_file.tmp';
        file_put_contents($testTmpFileFullName, 'Test temporary content.');

        $testTemporarySubDirectory = $testTemporaryDirectory . DIRECTORY_SEPARATOR . 'test_tmp_sub_directory';
        mkdir($testTemporarySubDirectory, 0777, true);

        $consoleCommand->actionClearTmpDir();

        $this->assertFalse(file_exists($testTmpFileFullName), 'Unable to remove files from temporary directory!');
        $this->assertFalse(file_exists($testTemporarySubDirectory), 'Unable to remove directory from temporary directory!');
    }

    /**
     * @depends testActionClearTmpDir
     */
    public function testActionClearTmpDirKeepSpecialFiles()
    {
        $consoleCommand = $this->createConsoleCommand();

        $testTemporaryDirectory = self::getTestFilePath() . DIRECTORY_SEPARATOR . 'test_tmp_directory_special_file';
        mkdir($testTemporaryDirectory, 0777, true);
        $consoleCommand->setTemporaryDirectories(array($testTemporaryDirectory));

        $testSpecialFileName = '.htaccess';
        $testSpecialFileFullName = $testTemporaryDirectory . DIRECTORY_SEPARATOR . $testSpecialFileName;
        file_put_contents($testSpecialFileFullName, 'special file content');

        $consoleCommand->actionClearTmpDir();

        $this->assertTrue(file_exists($testSpecialFileFullName), 'Unable to keep special file, while clearing temporary directory!');
    }

    /**
     * @depends testSetGet
     */
    public function testActionRequirements()
    {
        $consoleCommand = $this->createConsoleCommand();

        // Success:
        $requirementsErrorFileName = self::getTestFilePath() . DIRECTORY_SEPARATOR . 'test_requirements_success.php';
        $errorRequirements = array(
            array(
                'condition' => true,
                'mandatory' => true,
            ),
        );
        file_put_contents($requirementsErrorFileName, '<?php return ' . var_export($errorRequirements, true) . ';');
        $consoleCommand->setRequirementsFileName($requirementsErrorFileName);

        // Suppress output
        ob_start();
        ob_implicit_flush(false);
        $runResult = $consoleCommand->actionRequirements();
        ob_get_clean();
        $this->assertTrue($runResult, 'Requirements check failed for no error requirements!');

        // Error:
        $requirementsErrorFileName = self::getTestFilePath() . DIRECTORY_SEPARATOR . 'test_requirements_error.php';
        $errorRequirements = array(
            array(
                'condition' => false,
                'mandatory' => true,
            ),
        );
        file_put_contents($requirementsErrorFileName, '<?php return ' . var_export($errorRequirements, true) . ';');
        $consoleCommand->setRequirementsFileName($requirementsErrorFileName);

        // Suppress output
        ob_start();
        ob_implicit_flush(false);
        $runResult = $consoleCommand->actionRequirements();
        ob_get_clean();
        $this->assertFalse($runResult, 'Requirements check not failed for error requirements!');

        // Warning:
        $requirementsErrorFileName = self::getTestFilePath() . DIRECTORY_SEPARATOR . 'test_requirements_warning.php';
        $errorRequirements = array(
            array(
                'condition' => false,
                'mandatory' => false,
            ),
        );
        file_put_contents($requirementsErrorFileName, '<?php return ' . var_export($errorRequirements, true) . ';');
        $consoleCommand->setRequirementsFileName($requirementsErrorFileName);

        // Suppress output
        ob_start();
        ob_implicit_flush(false);
        $runResult = $consoleCommand->actionRequirements();
        ob_get_clean();
        $this->assertFalse($runResult, 'Requirements check not failed for warning requirements!');
    }
} 