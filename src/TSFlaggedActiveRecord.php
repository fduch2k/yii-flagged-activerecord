<?php

/**
 * Extends CActiveRecord class to add bitflag fields operations
 *
 * @link https://github.com/fduch2k/yii-flagged-activerecord
 * @author Alexander Hramov <alexander.hramov@gmail.com>
 * @copyright Copyright (c) 2012-2014, TagShake Ltd.
 * @license http://opensource.org/licenses/MIT
 */

abstract class TSFlaggedActiveRecord extends CActiveRecord
{
    private $_flags = null;
    private $_flagLabels = null;

    public $flagsField = 'flags';
    public $namePrefix = 'is';
    public $textDelimiter = ', ';

    public function setFlag($flag, $value)
    {
        if (is_string($flag)) {
            $flag = $this->flagsFromText($flag);
        }

        if ($value) {
            $this->{ $this->flagsField} |= $flag;
        } else {
            $this->{ $this->flagsField} &= ~$flag;
        }
    }

    public function getFlag($flag)
    {
        if (is_string($flag)) {
            $flag = $this->flagsFromText($flag);
        }

        return (($this->{ $this->flagsField}&$flag) == $flag);
    }

    public function flags()
    {
        return array(
        );
    }

    public function flagLabels()
    {
        return array(
        );
    }

    private function isPowerOfTwo($value)
    {
        return ($value != 0) && ($value&($value - 1)) == 0;
    }

    protected function cachedFlags()
    {
        if ($this->_flags === null) {
            $this->_flags = array();
            $temp = $this->flags();
            foreach ($temp as $flag => $val) {
                if (is_string($flag)) {
                    if ($this->isPowerOfTwo($val)) {
                        $this->_flags[trim(strtolower($flag))] = $val;
                    } else {

                        throw new Exception(Yii::t('ts.ext', 'Flag {value} is not power of 2', array('{value}' => $val)));
                    }
                }
            }

            $index = 0;
            foreach ($temp as $flag => $val) {
                if (is_numeric($flag)) {
                    $power = pow(2, $index++);
                    while (is_string(array_search($power, $this->_flags))) {
                        $power = pow(2, $index++);
                    }
                    $this->_flags[trim(strtolower($val))] = $power;
                }
            }

        }
        return $this->_flags;
    }

    public function flagsToText()
    {
        $flags = $this->cachedFlags();
        if ($this->_flagLabels === null) {
            $this->_flagLabels = $this->flagLabels();
        }

        foreach ($flags as $value) {
            if ($this->getFlag($value)) {
                $flagLabels[] = $this->_flagLabels[$value];
            }
        }

        return implode($this->textDelimiter, $flagLabels);
    }

    public function flagsFromText($flags)
    {
        $flagsValues = $this->cachedFlags();

        if (is_string($flags)) {
            $flags = preg_split("/[,?\s]+/", trim(strtolower($flags)));
        } else {

            $flags = CPropertyValue::ensureArray($flags);
        }

        $result = 0;
        foreach ($flags as $value) {
            $flagValue = $flagsValues[$value];
            if ($flagValue) {
                $result |= $flagValue;
            } else {

                throw new Exception(Yii::t('ts.ext', 'Flag {value} is not known string flag value', array('{value}' => $value)));
            }
        }

        return $result;
    }

    public function __get($name)
    {
        $flags = $this->cachedFlags();

        if (strncasecmp($name, $this->namePrefix, 2) === 0) {
            $flagName = substr(strtolower($name), strlen($this->namePrefix));
            if (isset($flags[$flagName])) {
                return $this->getFlag($flags[$flagName]);
            }
        }

        return parent::__get($name);
    }

    public function __set($name, $value)
    {
        $flags = $this->cachedFlags();

        if (strncasecmp($name, $this->namePrefix, 2) === 0) {
            $name = substr(strtolower($name), strlen($this->namePrefix));
            if (isset($flags[$name])) {
                $this->setFlag($flags[$name], $value);
            }
        } else {

            parent::__set($name, $value);
        }
    }

    public function __call($name, $arguments)
    {
        $flags = $this->cachedFlags();

        if (isset($flags[strtolower($name)])) {
            return $this->withFlag($name);
        }

        return parent::__call($name, $arguments);
    }

    /**
     * Scope for select records with given flag
     * @param mixed $flag the flag value or flag name
     * @return instancetype
     */

    public function withFlag($flag)
    {
        $flags = $this->cachedFlags();
        $flagValue = (is_string($flag)) ? $flags[trim(strtolower($flag))] : $flag;

        if ($flagValue) {
            $this->getDbCriteria()->mergeWith(array(
                'condition' => $this->getTableAlias() . '.' . $this->flagsField . '&' . $flagValue . '<>0', //:flag<>0',
            ));
        }
        return $this;
    }

    /**
     * Scope for select records without given flag
     * @param mixed $flag the flag value or flag name
     * @return instancetype
     */

    public function withoutFlag($flag)
    {
        $flags = $this->cachedFlags();
        $flagValue = (is_string($flag)) ? $flags[trim(strtolower($flag))] : $flag;

        if ($flagValue) {
            $this->getDbCriteria()->mergeWith(array(
                'condition' => $this->getTableAlias() . '.' . $this->flagsField . '&' . $flagValue . '=0', //.'&:flag=0',
            ));
        }
        return $this;
    }

    /**
     * Apply flags conditions to given criteria
     * @param CDbCriteria $criteria the criteria to apply flags condition
     * @param array $flags the array with flags (as flag name or as flag value). Flag name started with '!' is mean 'not flag'
     * @param string $operator the operator that connect flags conditions
     * @return CDbCriteria the CDbCriteria object with applied flags conditions
     */

    public function applyFlags($criteria, $flags, $operator = 'AND')
    {
        $flagList = $this->cachedFlags();

        $flags = CPropertyValue::ensureArray($flags);

        $newCriteria = new CDbCriteria();
        foreach ($flags as $flag) {
            if (is_string($flag)) {
                if ($invert = ($flag[0] == '!')) {
                    $flag = substr($flag, 1);
                }
                $flagValue = $flagList[trim(strtolower($flag))];
            } else {
                $flagValue = $flag;
            }
            if (empty($flagValue) === false) {
                $equality = $invert ? '=' : '<>';
                $newCriteria->addCondition($this->flagsField . '&' . $flagValue . $equality . '0', $operator);
            }
        }
        $criteria->mergeWith($newCriteria);
        return $criteria;
    }
}
