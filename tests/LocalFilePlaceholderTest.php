<?php

namespace yii2tech\tests\unit\install;

use yii\validators\RequiredValidator;
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
        $model = new LocalFilePlaceholder('', []);
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

        $validationRules = [
            ['required'],
        ];
        $model->setRules($validationRules);
        $validators = $model->getValidators();

        $this->assertEquals(count($validationRules), $validators->count(), 'Unable to set validation rules!');

        $validator = $validators->offsetGet(0);
        $this->assertTrue($validator instanceof RequiredValidator, 'Wrong validator created!');
    }

    /**
     * @depends testSetupRules
     */
    public function testAutoRequiredRule()
    {
        $model = $this->createTestModel();
        $model->default = null;

        $validators = $model->getValidators();

        $this->assertEquals(1, $validators->count(), 'Unable to automatically add validator!');

        $validator = $validators->offsetGet(0);
        $this->assertTrue($validator instanceof RequiredValidator, 'Wrong validator created!');
    }

    /**
     * Data provider for {@link testGetActualValue}
     * @return array test data
     */
    public function dataProviderGetActualValue()
    {
        return [
            [
                'string',
                'test_value',
                'test_value',
            ],
            [
                'boolean',
                '1',
                'true',
            ],
            [
                'boolean',
                '0',
                'false',
            ],
            [
                'boolean',
                'true',
                'true',
            ],
            [
                'boolean',
                'false',
                'false',
            ],
        ];
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
        $this->setExpectedException('\yii\base\Exception');
        $model->getActualValue();
    }
}