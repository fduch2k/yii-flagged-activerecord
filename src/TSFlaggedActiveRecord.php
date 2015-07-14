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

        if (is_string($value)) {
            $value = strtolower($value) == 'true' ? true : false;
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

        return (($this->{$this->flagsField}&$flag) == $flag);
    }

    public function flags()
    {
        return array(
        );
    }

    public function getFlagNames()
    {
        return array_keys($this->cachedFlags());
    }

    /**
     * Returns the flag labels.
     * Flag labels are mainly used in error messages of validation.
     * By default an flag label is generated using {@link CModel::generateAttributeLabel}.
     * This method allows you to explicitly specify flag labels.
     *
     * Note, in order to inherit labels defined in the parent class, a child class needs to
     * merge the parent labels with child labels using functions like array_merge().
     *
     * @return array flag labels (name=>label)
     * @see CModel::generateAttributeLabel
     */
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

    public function flagsToText($flag = null)
    {
        $flagLabels = array();
        $flag = is_null($flag) ? $this->{$this->flagField} : $flag;
        $flags = $this->cachedFlags();
        if ($this->_flagLabels === null) {
            $this->_flagLabels = $this->flagLabels();
        }

        foreach ($flags as $name => $value) {
            if (($flag & $value) === $value) {
                $flagLabels[] = isset($this->_flagLabels[$value]) ? $this->_flagLabels[$value] : $this->generateAttributeLabel($name);
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
     * Sets the attribute and flags values in a massive way.
     * @param array $values attribute values (name=>value) to be set.
     * @param boolean $safeOnly whether the assignments should only be done to the safe attributes.
     * A safe attribute is one that is associated with a validation rule in the current {@link scenario}.
     * @see getSafeAttributeNames
     * @see attributeNames
     */
    public function setAttributes($values, $safeOnly=true)
    {
        $flags = $this->cachedFlags();
        $notFlags = array();
        foreach ($values as $key => $val) {
            if (array_key_exists($key, $flags)) {
                $this->setFlag($key, $val);
            }
            else {
                $notFlags[$key] = $val;
            }
        }
        parent::setAttributes($notFlags, $safeOnly);
    }

    /**
     * Returns all attribute and flags values.
     * @param array $names list of attributes whose value needs to be returned.
     * Defaults to null, meaning all attributes as listed in {@link attributeNames} and flags as listed in
     * {@link flagNames} will be returned. If it is an array, only the attributes in the array will be returned.
     * @return array attribute values (name=>value).
     */
    public function getAttributes($names = null) {
        $attributes = parent::getAttributes($names);
        if (is_array($names)) {
            $flags = $this->cachedFlags();
            $flagNames = array_intersect(array_keys($flags), $names);
            foreach ($flagNames as $name) {
                $attributes[$name] = $this->getFlag($flags[$name]);
            }
        }
        return $attributes;
    }

    /**
     * Scope for select records with given flag
     * @param mixed $flag the flag value or flag name
     * @return instancetype
     */

    public function withFlag($flag)
    {
        $flagValue = (is_string($flag)) ? $this->flagsFromText($flag) : $flag;

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
        $flagValue = (is_string($flag)) ? $this->flagsFromText($flag) : $flag;

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
