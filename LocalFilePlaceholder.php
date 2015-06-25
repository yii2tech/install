<?php
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2tech\install;

use yii\base\Model;

/**
 * LocalFilePlaceholderModel is the local file placeholder model.
 * It serves the validation purposes and value processing.
 *
 * @property array rules public alias of {@link _rules}.
 *
 * @author Paul Klimov <pklimov@quartsoft.com>
 * @package qs.console.commands
 */
class LocalFilePlaceholder extends Model
{
    /**
     * @var string placeholder name.
     */
    public $name = 'value';
    /**
     * @var mixed placeholder value.
     */
    public $value;
    /**
     * @var mixed placeholder default value.
     */
    public $default;
    /**
     * @var string brief placeholder description.
     */
    public $hint = '';
    /**
     * @var string placeholder type.
     */
    public $type = 'string';
    /**
     * @var array validation rules.
     * Unlike the configuration for the common model, each rule should not contain attribute name
     * as it already determined as {@link value}.
     */
    private $_rules = array();

    /**
     * Constructor
     * @param string $name placeholder name.
     * @param array $config placeholder configuration.
     */
    public function __construct($name, array $config = [])
    {
        $config['name'] = $name;
        parent::__construct($config);
    }

    /**
     * @param array $rules
     */
    public function setRules(array $rules)
    {
        $this->_rules = $rules;
    }

    /**
     * @return array
     */
    public function getRules()
    {
        return $this->_rules;
    }

    /**
     * Returns the list of attribute names of the model.
     * @return array list of attribute names.
     */
    public function attributeNames()
    {
        return array('value');
    }

    /**
     * Returns the attribute labels.
     * @return array attribute labels.
     */
    public function attributeLabels()
    {
        return array(
            'value' => $this->name
        );
    }

    /**
     * Creates validator objects based on the specification in {@link rules}.
     * This method is mainly used internally.
     * @throws CException on invalid configuration.
     * @return \CList validators built based on {@link rules()}.
     */
    public function createValidators()
    {
        $validatorList = parent::createValidators();
        $rules = $this->getRules();
        if ($this->default === null) {
            array_unshift($rules, array('required'));
        }
        foreach ($rules as $rule) {
            if (isset($rule[0])) { // validator name
                $validatorList->add(CValidator::createValidator($rule[0], $this, 'value', array_slice($rule, 2)));
            } else {
                throw new CException('Invalid validation rule for "' . $this->getAttributeLabel('value') . '". The rule must specify the validator name.');
            }
        }
        return $validatorList;
    }

    /**
     * This method is invoked before validation starts.
     * @return boolean whether validation should be executed.
     */
    protected function beforeValidate()
    {
        $value = $this->value;
        if ($value === null || $value === false || $value === '') {
            if ($this->default !== null) {
                $this->value = $this->default;
            }
        }
        return parent::beforeValidate();
    }

    /**
     * Returns verbose label for placeholder.
     * @return string placeholder verbose label.
     */
    public function composeLabel()
    {
        $labelContent = "'{$this->name}'";
        if (!empty($this->hint)) {
            $labelContent .= ' (' . $this->hint . ')';
        }
        if ($this->default !== null) {
            $labelContent .= ' [default: ' . $this->default . ']';
        }
        return $labelContent;
    }

    /**
     * Returns actual placeholder value according to placeholder type.
     * @return float|int|string actual value.
     * @throws CException on invalid type.
     */
    public function getActualValue()
    {
        $rawValue = $this->value;
        if ($rawValue === null || $rawValue === false || $rawValue === '') {
            if ($this->default !== null) {
                $rawValue = $this->default;
            } else {
                throw new CException("Unable to determine default value for the placeholder '{$this->name}'!");
            }
        }
        switch ($this->type) {
            case 'bool':
            case 'boolean':
                if (strcasecmp($rawValue, 'true') === 0) {
                    $rawValue = true;
                } elseif (strcasecmp($rawValue, 'false') === 0) {
                    $rawValue = false;
                } else {
                    $rawValue = (boolean)$rawValue;
                }
                return $rawValue ? 'true' : 'false';
            case 'string':
                return $rawValue;
            case 'int':
            case 'integer':
                return (integer)$rawValue;
            case 'decimal':
            case 'double':
            case 'float':
                return (float)$rawValue;
            default:
                throw new CException("Unknown type '{$this->type}' for placeholder '{$this->name}'!");
        }
    }

    /**
     * Composes errors single string summary.
     * @param string $delimiter errors delimiter.
     * @return string error summary
     */
    public function getErrorSummary($delimiter = "\n")
    {
        $errorSummaryParts = array();
        foreach ($this->getErrors() as $attributeErrors) {
            foreach ($attributeErrors as $attributeError) {
                $errorSummaryParts[] = $attributeError;
            }
        }
        return implode($delimiter, $errorSummaryParts);
    }
}