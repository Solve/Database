<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 10.05.14 18:10
 */

namespace Solve\Database\Models;

use Solve\Database\QC;
use Solve\Utils\Inflector;

/**
 * Class Model
 * @package Solve\Database\Models
 *
 * @version 1.0
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 */
class Model implements \ArrayAccess, \IteratorAggregate, \Countable {
    /**
     * @var ModelStructure
     */
    private $_structure      = null;

    private $_tableName;
    private $_primaryKey;
    private $_data           = array();
    private $_originalData   = array();
    private $_changedData    = array();
    private $_isNew          = true;
    private $_invokedGetters = array();

    /**
     * @var null|string instance model name
     */
    private $_name = null;

    public function __construct($data = array()) {
        $this->_name      = get_called_class();
        $this->_structure = ModelStructure::getInstanceForModel($this->_name);
        $this->_tableName = $this->_structure->getTableName();
        $this->_primaryKey = $this->_structure->getPrimaryKey();
        $this->configure();
    }

    public static function getModel($modelName) {
        return new $modelName();
    }

    /**
     * @param mixed|QC $criteria
     * @return Model
     */
    public static function loadOne($criteria) {
        /**
         * @var Model $object
         */
        $object = self::getModel(get_called_class());
        return $object->_loadOne($criteria);
    }

    /**
     * @param mixed|QC $criteria
     * @return Model
     */
    protected function _loadOne($criteria) {
        if (is_object($criteria) && $criteria->getModifier('rawSelect')) {
            $qc = $criteria;
        } else {
            $criteria = $this->_processCriteria($criteria);

            $qc = QC::create($this->_tableName);
            if (!empty($criteria)) {
                $qc->and($criteria);
            }
        }
        $this->setOriginalData($qc->executeOne());
        return $this;
    }

    protected function _processCriteria($criteria) {
        if (is_scalar($criteria)) {
            $criteria = array($this->_primaryKey => $criteria);
        }
        if (is_array($criteria)) {
            $newCriteria = array();
            foreach ($criteria as $key => $value) {
                $newCriteria[$this->_tableName . '.' . $key] = $value;
            }
            $criteria = $newCriteria;
            unset($newCriteria);
        }
        return $criteria;
    }

    public function setOriginalData($data) {
        if (empty($data)) $data = array();

        $this->_originalData = $data;
        $this->_data         = $data;
        $this->_isNew        = false;
        return $this;
    }

    /**
     * Merge current data with provided
     * @param $data
     */
    public function mergeWithData($data) {
        foreach($data as $key=>$value) {
            if ($key != $this->_primaryKey && (
                (!array_key_exists($key, $this->_data) && $this->_structure->hasColumn($key))
                    || (array_key_exists($key, $this->_data) && $value != $this->_data[$key])
                )) {
                $this->offsetSet($key, $value);
            }
        }
    }

    public function save($forceSave = false) {
        if (!$this->isChanged() && !$forceSave) return true;

        $dataToSave = array();
        foreach($this->_structure->getColumns() as $column=>$info) {
            if (array_key_exists($column, $this->_changedData)) {
                $dataToSave[$column] = $this->_changedData[$column];
            } elseif ($forceSave) {
                if ($column == 'id') continue;
                $dataToSave[$column] = array_key_exists($column, $this->_data) ? $this->_data[$column] : null;
            }
        }

        $qc = QC::create($this->_tableName);
        if ($this->_isNew) {
            if (empty($dataToSave)) {
                $dataToSave = array($this->_primaryKey => null);
            }
            $this->{$this->_primaryKey} = $qc->insert($dataToSave)->execute();
        } elseif (count($dataToSave)) {
            $qc->update($dataToSave)->where(array($this->_primaryKey => $this->{$this->_primaryKey}))->execute();
        }

        /**
         * Reloading data from DB for the newly created object
         */
        if ($this->_isNew) {
            $data = QC::create($this->_tableName)->where(array($this->_tableName . '.' . $this->_primaryKey => $this->{$this->_primaryKey}))->executeOne();
            $this->setOriginalData($data);
        }

        $this->_isNew = false;
        $this->_changedData = array();
        return true;
    }

    public function getArray() {
        return $this->_data;
    }
    /**
     * Could be used for configuring model after constructor is done
     */
    protected function configure() {
    }


    public function isNew() {
        return $this->_isNew;
    }

    public function isChanged() {
        return count($this->_changedData) > 0;
    }
    /**
     * @param null $what
     * @return mixed|ModelStructure
     */
    public function _getStructure($what = null) {
        if (!is_null($what)) {
            return $this->_structure->get($what);
        } else {
            return $this->_structure;
        }
    }

    /**
     * Internal use: Get model name
     * @return null|string
     */
    public function _getName() {
        return $this->_name;
    }

    /**
     * Internal use: Set model name
     * @param $name
     */
    public function _setName($name) {
        $this->_name = $name;
    }

    public function __set($key, $value) {
        $this->offsetSet($key, $value);
    }

    public function &__get($key) {
        // if key is exists - return it
        if (array_key_exists($key, $this->_data)) return $this->_data[$key];

        $getterName = Inflector::camelize($key);
        $method     = 'get' . $getterName;
        $default    = null;

        // check for getter
        if (method_exists($this, $method) && !array_key_exists($getterName, $this->_invokedGetters)) {
            $this->_data[$key] = $this->_invokedGetters[$getterName] = $this->$method();
            return $this->_data[$key];
        // check for relation
        } elseif ($this->_structure->hasRelation($key) && !$this->_isNew) {
            $mr = ModelRelation::getInstanceForModel($this);
            $mr->loadRelative($this, $key);
        }
        if (array_key_exists($key, $this->_data)) {
            return $this->_data[$key];
        } else {
            return $default;
        }
    }

    public function __call($method, $params) {
        if (substr($method, 0, 3) == 'get') {
            return $this->offsetGet(strtolower(substr($method, 3)));
        } elseif (substr($method, 0, 3) == 'set') {
            $this->offsetSet(strtolower(substr($method, 3)), $params[0]);
            return $this;
        }

    }

    public function getIterator() {
        return new \ArrayIterator($this->_data);
    }

    public function count() {
        return count($this->_data);
    }

    public function offsetExists($offset) {
        return array_key_exists($offset, $this->_data);
    }

    public function &offsetGet($offset) {
        return $this->_data[$offset];
    }

    public function offsetSet($offset, $value) {
        if (method_exists($this, 'set'.Inflector::camelize($offset))) {
            $this->{'set'.Inflector::camelize($offset)}($value);
        }
        $this->_data[$offset] = $value;
        $this->_changedData[$offset] = $value;
        if (array_key_exists($offset, $this->_invokedGetters)) {
            $this->_invokedGetters[$offset] = $value;
        }
    }

    public function offsetUnset($offset) {
        unset($this->_data[$offset]);
    }

} 