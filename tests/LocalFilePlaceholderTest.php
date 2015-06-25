<?php

namespace yii2tech\tests\unit\install;

use yii2tech\install\LocalFilePlaceholder;

/**
 * Test case for the extension "yii2tech\install\LocalFilePlaceholder".
 * @see LocalFilePlaceholder
 */
class LocalFilePlaceholderTest extends TestCase
{
    /**
     * Creates test model.
     * @return LocalFilePlaceholder model instance.
     */
    protected function createTestModel()
    {
        $model = new LocalFilePlaceholder('', array());
        return $model;
    }

    // Tests :

    public function testLabel()
    {
        $model = $this->createTestModel();

        $name = 'TestPlaceholderName';
        $model->name = $name;

        $this->assertEquals($name, $model->getAttributeLabel('value'), 'Wrong value label!');
    }

    public function testSetupRules()
    {
        $model = $this->createTestModel();
        $model->default = 'test default';

        $validationRules = array(
            array('required'),
        );
        $model->setRules($validationRules);
        $validatorList = $model->getValidatorList();

        $this->assertEquals(count($validationRules), $validatorList->getCount(), 'Unable to set validation rules!');

        $validator = $validatorList->itemAt(0);
        $this->assertTrue($validator instanceof CRequiredValidator, 'Wrong validator created!');
    }

    /**
     * @depends testSetupRules
     */
    public function testAutoRequiredRule()
    {
        $model = $this->createTestModel();
        $model->default = null;

        $validatorList = $model->getValidatorList();

        $this->assertEquals(1, $validatorList->getCount(), 'Unable to automatically add validator!');

        $validator = $validatorList->itemAt(0);
        $this->assertTrue($validator instanceof CRequiredValidator, 'Wrong validator created!');
    }

    /**
     * Data provider for {@link testGetActualValue}
     * @return array test data
     */
    public function dataProviderGetActualValue()
    {
        return array(
            array(
                'string',
                'test_value',
                'test_value',
            ),
            array(
                'boolean',
                '1',
                'true',
            ),
            array(
                'boolean',
                '0',
                'false',
            ),
            array(
                'boolean',
                'true',
                'true',
            ),
            array(
                'boolean',
                'false',
                'false',
            ),
        );
    }

    /**
     * @dataProvider dataProviderGetActualValue
     *
     * @param string $type
     * @param mixed $value
     * @param mixed $expectedActualValue
     */
    public function testGetActualValue($type, $value, $expectedActualValue)
    {
        $model = $this->createTestModel();

        $model->type = $type;
        $model->value = $value;

        $this->assertEquals($expectedActualValue, $model->getActualValue());
    }

    /**
     * @depends testGetActualValue
     */
    public function testGetDefaultValue()
    {
        $model = $this->createTestModel();

        $defaultValue = 'test_default_value';
        $model->default = $defaultValue;

        $this->assertEquals($defaultValue, $model->getActualValue(), 'Unable to get default value!');

        $model->default = null;
        $this->setExpectedException('CException');
        $model->getActualValue();
    }
}