<?php

namespace yii2tech\tests\unit\install;

use Yii;
use yii\helpers\FileHelper;
use yii2tech\install\InitController;

/**
 * Test case for the extension "yii2tech\install\InitController".
 * @see InitController
 */
class InitControllerTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $testFilePath = $this->getTestFilePath();
        FileHelper::createDirectory($testFilePath);
    }

    protected function tearDown()
    {
        $testFilePath = $this->getTestFilePath();
        FileHelper::removeDirectory($testFilePath);

        parent::tearDown();
    }

    /**
     * Creates test console command instance.
     * @return InitController console command instance.
     */
    protected function createController()
    {
        $consoleCommand = $this->getMock(InitController::className(), ['stdout'], ['install', Yii::$app]);
        //$consoleCommand = new InitController('install', Yii::$app);
        $consoleCommand->interactive = false;
        $consoleCommand->outputLog = false;
        return $consoleCommand;
    }

    /**
     * Returns the test file path.
     * @return string test file path.
     */
    protected function getTestFilePath()
    {
        return Yii::getAlias('@yii2tech/tests/unit/install/runtime') . DIRECTORY_SEPARATOR . getmypid();
    }

    // Tests:

    public function testActionLocalDir()
    {
        $consoleCommand = $this->createController();

        $testLocalDirectory = $this->getTestFilePath() . DIRECTORY_SEPARATOR . 'test_directory';
        $consoleCommand->localDirectories = [$testLocalDirectory];

        $consoleCommand->actionLocalDir();

        $this->assertTrue(file_exists($testLocalDirectory), 'Unable to create local directory!');
    }

    public function testActionLocalFile()
    {
        $consoleCommand = $this->createController();

        $testLocalFileSelfName = 'test_file.php';
        $testLocalFileFullName = $this->getTestFilePath() . DIRECTORY_SEPARATOR . $testLocalFileSelfName;
        $consoleCommand->localFiles = [$testLocalFileFullName];

        $testExampleFileSelfName = str_replace('{filename}', $testLocalFileSelfName, $consoleCommand->localFileExampleNamePattern);
        $testExampleFileFullName = $this->getTestFilePath() . DIRECTORY_SEPARATOR . $testExampleFileSelfName;
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
        $consoleCommand = $this->createController();

        $testPlaceholderName = 'test_placeholder_name';
        $testPlaceholderValue = 'test_placeholder_value';
        $testLocalFilePlaceholders = [
            $testPlaceholderName => [
                'default' => $testPlaceholderValue
            ]
        ];
        $consoleCommand->localFilePlaceholders = $testLocalFilePlaceholders;

        $testLocalFileSelfName = 'test_file_default_values.php';
        $testLocalFileFullName = $this->getTestFilePath() . DIRECTORY_SEPARATOR . $testLocalFileSelfName;
        $consoleCommand->localFiles = [$testLocalFileFullName];

        $testExampleFileSelfName = str_replace('{filename}', $testLocalFileSelfName, $consoleCommand->localFileExampleNamePattern);
        $testExampleFileFullName = $this->getTestFilePath() . DIRECTORY_SEPARATOR . $testExampleFileSelfName;
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
        $consoleCommand = $this->createController();

        $testLocalFileSelfName = 'test_file.php';
        $testLocalFileFullName = $this->getTestFilePath() . DIRECTORY_SEPARATOR . $testLocalFileSelfName;
        $consoleCommand->localFiles = [$testLocalFileFullName];

        $testExampleFileSelfName = str_replace('{filename}', $testLocalFileSelfName, $consoleCommand->localFileExampleNamePattern);
        $testExampleFileFullName = $this->getTestFilePath() . DIRECTORY_SEPARATOR . $testExampleFileSelfName;

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
        $consoleCommand = $this->createController();

        $testLocalFileSelfName = 'test_file.php';
        $testLocalFileFullName = $this->getTestFilePath() . DIRECTORY_SEPARATOR . $testLocalFileSelfName;
        $consoleCommand->localFiles = [$testLocalFileFullName];

        $testExampleFileSelfName = str_replace('{filename}', $testLocalFileSelfName, $consoleCommand->localFileExampleNamePattern);
        $testExampleFileFullName = $this->getTestFilePath() . DIRECTORY_SEPARATOR . $testExampleFileSelfName;

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

    public function testPopulateFromConfigFile()
    {
        $consoleCommand = $this->createController();

        $testFieldName = 'localFileExampleNamePattern';
        $testFieldValue = 'test_local_file_example_name_pattern';
        $testConfig = [
            $testFieldName => $testFieldValue
        ];

        $testConfigFileName = $this->getTestFilePath() . DIRECTORY_SEPARATOR . 'test_config.php';
        $testConfigFileContent = '<?php return ' . var_export($testConfig, true) . ';';
        file_put_contents($testConfigFileName, $testConfigFileContent);

        $this->assertTrue($consoleCommand->populateFromConfigFile($testConfigFileName), 'Unable to populate from file!');

        $this->assertEquals($testFieldValue, $consoleCommand->$testFieldName, 'Unable to setup field, while populating from file!');
    }

    public function testActionExecuteFile()
    {
        $consoleCommand = $this->createController();

        $testFileName = $this->getTestFilePath() . DIRECTORY_SEPARATOR . 'test_execute_file.php';
        file_put_contents($testFileName, 'some executable content');

        $testExecuteFiles = [
            $testFileName
        ];
        $consoleCommand->executeFiles = $testExecuteFiles;

        $consoleCommand->actionExecuteFile();

        $filePermissions = substr(sprintf('%o', fileperms($testFileName)), -4);
        $this->assertEquals('0755', $filePermissions, 'Wrong execute file permissions!');
    }

    public function testActionConfig()
    {
        $consoleCommand = $this->createController();

        $testConfigFileName = $this->getTestFilePath() . DIRECTORY_SEPARATOR . 'test_config_file.php';

        $consoleCommand->actionConfig($testConfigFileName);

        $this->assertTrue(file_exists($testConfigFileName), 'Unable to generate configuration file!');

        @$configData = require($testConfigFileName);
        $this->assertTrue(is_array($configData), 'Unable to read data from config!');

        foreach ($configData as $name => $value) {
            $this->assertEquals($value, $consoleCommand->$name, 'Config parameter does not match the console command instance!');
        }
    }

    /**
     * @depends testActionConfig
     */
    public function testLogFile()
    {
        $consoleCommand = $this->createController();

        $testLogFileName = $this->getTestFilePath() . DIRECTORY_SEPARATOR . 'test_log_file.log';
        $consoleCommand->logFile = $testLogFileName;
        $consoleCommand->initLog();

        $testConfigFileName = $this->getTestFilePath() . DIRECTORY_SEPARATOR . 'test_config_file.php';
        $consoleCommand->actionConfig($testConfigFileName);

        $this->assertTrue(file_exists($testLogFileName), 'Unable to generate log file!');
    }

    public function testActionClearTmpDir()
    {
        $consoleCommand = $this->createController();

        $testTemporaryDirectory = $this->getTestFilePath() . DIRECTORY_SEPARATOR . 'test_tmp_directory';
        FileHelper::createDirectory($testTemporaryDirectory);
        $consoleCommand->tmpDirectories = [$testTemporaryDirectory];

        $testTmpFileFullName = $testTemporaryDirectory . DIRECTORY_SEPARATOR . 'test_tmp_file.tmp';
        file_put_contents($testTmpFileFullName, 'Test temporary content.');

        $testTemporarySubDirectory = $testTemporaryDirectory . DIRECTORY_SEPARATOR . 'test_tmp_sub_directory';
        FileHelper::createDirectory($testTemporarySubDirectory);

        $consoleCommand->actionClearTmpDir();

        $this->assertFalse(file_exists($testTmpFileFullName), 'Unable to remove files from temporary directory!');
        $this->assertFalse(file_exists($testTemporarySubDirectory), 'Unable to remove directory from temporary directory!');
    }

    /**
     * @depends testActionClearTmpDir
     */
    public function testActionClearTmpDirKeepSpecialFiles()
    {
        $consoleCommand = $this->createController();

        $testTemporaryDirectory = $this->getTestFilePath() . DIRECTORY_SEPARATOR . 'test_tmp_directory_special_file';
        FileHelper::createDirectory($testTemporaryDirectory);
        $consoleCommand->tmpDirectories = [$testTemporaryDirectory];

        $testSpecialFileName = '.htaccess';
        $testSpecialFileFullName = $testTemporaryDirectory . DIRECTORY_SEPARATOR . $testSpecialFileName;
        file_put_contents($testSpecialFileFullName, 'special file content');

        $consoleCommand->actionClearTmpDir();

        $this->assertTrue(file_exists($testSpecialFileFullName), 'Unable to keep special file, while clearing temporary directory!');
    }

    public function testActionRequirements()
    {
        if (!version_compare(INTL_ICU_VERSION, '49', '>=')) {
            // Yii2 core requirements check will fail if ICU version is too low
            $this->markTestSkipped('ICU 49.0 or higher is required');
        }

        $consoleCommand = $this->createController();

        // Success:
        $requirementsErrorFileName = $this->getTestFilePath() . DIRECTORY_SEPARATOR . 'test_requirements_success.php';
        $errorRequirements = [
            [
                'condition' => true,
                'mandatory' => true,
            ],
        ];
        file_put_contents($requirementsErrorFileName, '<?php return ' . var_export($errorRequirements, true) . ';');
        $consoleCommand->requirementsFileName = $requirementsErrorFileName;

        // Suppress output
        ob_start();
        ob_implicit_flush(false);
        $runResult = $consoleCommand->actionRequirements();
        $output = ob_get_clean();
        $this->assertEquals(0, $runResult, 'Requirements check failed for no error requirements!' . "\n" . $output);

        // Error:
        $requirementsErrorFileName = $this->getTestFilePath() . DIRECTORY_SEPARATOR . 'test_requirements_error.php';
        $errorRequirements = [
            [
                'condition' => false,
                'mandatory' => true,
            ],
        ];
        file_put_contents($requirementsErrorFileName, '<?php return ' . var_export($errorRequirements, true) . ';');
        $consoleCommand->requirementsFileName = $requirementsErrorFileName;

        // Suppress output
        ob_start();
        ob_implicit_flush(false);
        $runResult = $consoleCommand->actionRequirements();
        $output = ob_get_clean();
        $this->assertNotEquals(0, $runResult, 'Requirements check not failed for error requirements!' . "\n" . $output);

        // Warning:
        $requirementsErrorFileName = $this->getTestFilePath() . DIRECTORY_SEPARATOR . 'test_requirements_warning.php';
        $errorRequirements = [
            [
                'condition' => false,
                'mandatory' => false,
            ],
        ];
        file_put_contents($requirementsErrorFileName, '<?php return ' . var_export($errorRequirements, true) . ';');
        $consoleCommand->requirementsFileName = $requirementsErrorFileName;

        // Suppress output
        ob_start();
        ob_implicit_flush(false);
        $runResult = $consoleCommand->actionRequirements();
        $output = ob_end_clean();
        $this->assertEquals(0, $runResult, 'Requirements check not failed for warning requirements!' . "\n" . $output);
    }

    /**
     * @depends testActionRequirements
     */
    public function testActionRequirementsFromOutput()
    {
        if (!class_exists('YiiRequirementChecker', false)) {
            require Yii::getAlias('@vendor/yiisoft/yii2/requirements/YiiRequirementChecker.php');
        }

        $consoleCommand = $this->createController();

        // Success :
        $requirementsErrorFileName = $this->getTestFilePath() . DIRECTORY_SEPARATOR . 'test_requirements_output_success.php';
        $errorRequirements = [
            [
                'condition' => true,
                'mandatory' => true,
            ],
        ];
        file_put_contents($requirementsErrorFileName, '<?php (new YiiRequirementChecker())->check(' . var_export($errorRequirements, true) . ')->render();');
        $consoleCommand->requirementsFileName = $requirementsErrorFileName;

        $runResult = $consoleCommand->actionRequirements(false);
        $this->assertEquals(0, $runResult, 'Requirements check failed for no error requirements!');

        // Error :
        $requirementsErrorFileName = $this->getTestFilePath() . DIRECTORY_SEPARATOR . 'test_requirements_output_success.php';
        $errorRequirements = [
            [
                'condition' => false,
                'mandatory' => true,
            ],
        ];
        file_put_contents($requirementsErrorFileName, '<?php (new YiiRequirementChecker())->check(' . var_export($errorRequirements, true) . ')->render();');
        $consoleCommand->requirementsFileName = $requirementsErrorFileName;

        $runResult = $consoleCommand->actionRequirements(false);
        $this->assertNotEquals(0, $runResult, 'Requirements check failed for error requirements!');

        // Warning :
        $requirementsErrorFileName = $this->getTestFilePath() . DIRECTORY_SEPARATOR . 'test_requirements_output_success.php';
        $errorRequirements = [
            [
                'condition' => false,
                'mandatory' => false,
            ],
        ];
        file_put_contents($requirementsErrorFileName, '<?php (new YiiRequirementChecker())->check(' . var_export($errorRequirements, true) . ')->render();');
        $consoleCommand->requirementsFileName = $requirementsErrorFileName;

        $runResult = $consoleCommand->actionRequirements(false);
        $this->assertEquals(0, $runResult, 'Requirements check failed for warning requirements!');
    }
} 