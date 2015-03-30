<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 10.05.14 18:20
 */

namespace Solve\Database\Models;

use Solve\Storage\ArrayStorage;

/**
 * Class ModelStructure
 * @package Solve\Database\Models
 *
 * Class ModelStructure represents model structure
 *
 * @version 1.0
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 */
class ModelStructure {

    private $_modelName = null;

    /**
     * @var ArrayStorage
     */
    private $_data           = null;
    private $_addedAbilities = array();

    private static $_modelInstances = array();

    public function __construct($modelName, $data = null) {
        $this->_modelName = $modelName;
        if (!empty($data)) {
            $this->_data->setData($data);
        } else {
            $this->_data = new ArrayStorage(ModelOperator::getInstance()->getModelStructure($modelName));
        }
    }

    /**
     * @param $modelName
     * @return ModelStructure
     */
    public static function getInstanceForModel($modelName) {
        if (empty(self::$_modelInstances[$modelName])) {
            self::$_modelInstances[$modelName] = new self($modelName);
        }
        return self::$_modelInstances[$modelName];
    }

    /**
     * Return field specified as primary key or null
     * @return string|null
     */
    public function getPrimaryKey() {
        return $this->_data->getDeepValue('indexes/primary/columns/0', null);
    }

    /**
     * Check if columns is exists
     * @param string $name
     * @return bool
     */
    public function hasColumn($name) {
        $info = $this->_data->getDeepValue('columns/' . $name);
        return !empty($info);
    }

    public function hasRelation($name) {
        $info = $this->_data->getDeepValue('relations/' . $name, null);
        return !is_null($info);
    }

    public function addColumn($name, $info) {
        if (!$this->hasColumn($name)) {
            $this->_data->setDeepValue('columns/' . $name, $info);
        }
        return $this;
    }

    public function updateColumnInfo($name, $info) {
        $currentInfo = $this->getColumnInfo($name);
        foreach($info as $key=>$value) {
            $currentInfo[$key] = $value;
        }
        $this->_data->setDeepValue('columns/' . $name, $currentInfo);
        return $this;
    }

    public function dropColumn($name) {
        if ($this->hasColumn($name)) {
            $this->_data->unsetDeepValue('columns/' . $name);
        }
        return $this;
    }


    public function addRelation($name, $info = array()) {
        $this->_data->setDeepValue('relations/' . $name, $info);
        return $this;
    }

    public function addAbility($name, $info = array()) {
        $this->_data->setDeepValue('abilities/' . $name, $info);
        $this->_addedAbilities[] = $name;
        return $this;
    }

    public function updateAbilityInfo($name, $info) {
        $currentInfo = $this->getAbilityInfo($name);
        foreach($info as $key=>$value) {
            $currentInfo[$key] = $value;
        }
        $this->_data->setDeepValue('abilities/' . $name, $currentInfo);
        return $this;
    }

    public function saveStructure() {
        ModelOperator::getInstance()->setStructureForModel($this->_modelName, $this->_data->getArray());
        ModelOperator::getInstance()->saveModelStructure($this->_modelName);
        $this->setupAddedAbilities();
        ModelOperator::getInstance()->setStructureForModel($this->_modelName, $this->_data->getArray());
        ModelOperator::getInstance()->saveModelStructure($this->_modelName);
        return $this;
    }

    public function updateDatabaseStructure($safeUpdate = true) {
        DBOperator::getInstance()->updateDBFromStructure($this->_data->getArray(), $safeUpdate);
        return $this;
    }

    public function setupAddedAbilities() {
        if (empty($this->_addedAbilities)) return $this;

        foreach($this->_addedAbilities as $abilityName) {
            $instance = ModelOperator::getAbilityInstanceForModel($this->_modelName, $abilityName);
            $instance->setup();
        }
        $this->_addedAbilities = array();

        return $this;
    }

    public function getModelName() {
        return $this->_modelName;
    }

    public function getTableName() {
        return $this->_data->get('table');
    }

    public function getColumns() {
        return $this->_data->get('columns');
    }

    public function getRelations() {
        return $this->_data->get('relations');
    }

    public function getAbilities() {
        return $this->_data->get('abilities');
    }

    public function getColumnInfo($name) {
        return $this->_data->getDeepValue('columns/' . $name);
    }

    public function getRelationInfo($name) {
        return $this->_data->getDeepValue('relations/' . $name);
    }

    public function getAbilityInfo($name) {
        return $this->_data->getDeepValue('abilities/' . $name);
    }

    public function get($key = null) {
        return $this->_data->get($key);
    }

}