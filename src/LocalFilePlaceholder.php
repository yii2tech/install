<?php
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2tech\install;

use yii\base\Exception;
use yii\base\InvalidCallException;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\validators\Validator;

/**
 * LocalFilePlaceholderModel is the local file placeholder model.
 * It serves the validation purposes and value processing.
 *
 * @property array $rules validation rules.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
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
    private $_rules = [];

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
     * @param array $rules validation rules.
     */
    public function setRules(array $rules)
    {
        $this->_rules = $rules;
    }

    /**
     * @return array validation rules.
     */
    public function getRules()
    {
        return $this->_rules;
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return ['value'];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'value' => $this->name
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeHints()
    {
        return [
            'value' => $this->hint
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function createValidators()
    {
        $validators = parent::createValidators();

        $rules = $this->getRules();
        if ($this->default === null) {
            array_unshift($rules, ['required']);
        }

        foreach ($rules as $rule) {
            if ($rule instanceof Validator) {
                $validators->append($rule);
            } elseif (is_array($rule) && isset($rule[0])) { // attributes, validator type
                $validator = Validator::createValidator($rule[0], $this, ['value'], array_slice($rule, 1));
                $validators->append($validator);
            } else {
                throw new InvalidConfigException('Invalid validation rule: a rule must specify validator type.');
            }
        }
        return $validators;
    }

    /**
     * {@inheritdoc}
     */
    public function beforeValidate()
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
     * @throws \yii\base\InvalidCallException on invalid type.
     * @throws \yii\base\Exception on failure.
     * @return float|int|string actual value.
     */
    public function getActualValue()
    {
        $rawValue = $this->value;
        if ($rawValue === null || $rawValue === false || $rawValue === '') {
            if ($this->default !== null) {
                $rawValue = $this->default;
            } else {
                throw new Exception("Unable to determine default value for the placeholder '{$this->name}'!");
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
                    $rawValue = (bool)$rawValue;
                }
                return $rawValue ? 'true' : 'false';
            case 'string':
                return $rawValue;
            case 'int':
            case 'integer':
                return (int)$rawValue;
            case 'decimal':
            case 'double':
            case 'float':
                return (float)$rawValue;
            default:
                throw new InvalidCallException("Unknown type '{$this->type}' for placeholder '{$this->name}'!");
        }
    }

    /**
     * Composes errors single string summary.
     * @param string $delimiter errors delimiter.
     * @return string error summary
     */
    public function getErrorSummary($delimiter = "\n")
    {
        $errorSummaryLines = [];
        foreach ($this->getErrors() as $attributeErrors) {
            $errorSummaryLines = array_merge($errorSummaryLines, $attributeErrors);
        }
        return implode($delimiter, $errorSummaryLines);
    }
}