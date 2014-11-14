<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 05.10.14 09:13
 */

namespace Solve\Database\Models;

use Solve\Database\QC;
use Solve\Utils\Inflector;

/**
 * Class ModelCollection
 * @package Solve\Database\Models
 *
 * Class ModelCollection is used to operate with collection of models
 *
 * @method _setRawFieldValue($field, $value)
 * @method _setRawFieldData($data)
 *
 * @version 1.0
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 */
class ModelCollection implements \ArrayAccess, \IteratorAggregate, \Countable {

    private $_data   = array();
    private $_pk_map = array();
    /**
     * @var Model
     */
    private $_model;
    /**
     * @var ModelStructure
     */
    private $_structure;
    private $_modelClass;
    private $_tableName;
    private $_primaryKey;
    private $_modelsMethods = array(
        '_setRawFieldValue', '_setRawFieldData'
    );

    public function __construct() {
        $this->configure();
    }

    /**
     * @param Model $modelObject
     */
    public function _setModel($modelObject) {
        if (!is_object($modelObject)) $modelObject = new $modelObject();
        $this->_model      = $modelObject;
        $this->_modelClass = get_class($modelObject);
        $this->_structure  = $modelObject->_getStructure();
        $this->_tableName  = $this->_structure->getTableName();
        $this->_primaryKey = $this->_structure->getPrimaryKey();
    }

    /**
     * @param QC|mixed $criteria
     * @param Model $modelObject
     * @return ModelCollection
     */
    public static function loadList($criteria, $modelObject) {
        $className = get_called_class();
        /**
         * @var ModelCollection $instance
         */
        $instance = new $className();
        $instance->_setModel($modelObject);
        return $instance->_loadList($criteria);
    }

    /**
     * @param QC|null $criteria
     * @return ModelCollection
     */
    protected function _loadList($criteria) {
        /**
         * @var QC $criteria
         * @var QC $qc
         */
        if (is_object($criteria) && $criteria->getModifier('rawSelect')) {
            $qc = $criteria;
        } else {
            $qc = QC::create($this->_tableName);
            if (!empty($criteria)) {
                $criteria = $this->_model->_processCriteria($criteria);
                $qc->importQC($criteria);
                $qc->and($criteria);
            }
        }
        $this->_preLoad($qc);
        $data = $qc->execute();
        $this->_postLoad();
        if (empty($data)) $data = array();

        $this->_data = array();
        $index       = 0;
        foreach ($data as $item) {
            /**
             * @var Model $object
             */
            $object = new $this->_modelClass();
            $object->setOriginalData($item);
            $object->_setCollectionReference($this);
            $this->_data[]                              = $object;
            $this->_pk_map[$object[$this->_primaryKey]] = $index++;
        }
        return $this;
    }

    /**
     * @param string $relations
     */
    public function loadRelated($relations) {
        $relations = explode(',', $relations);
        $mr        = ModelRelation::getInstanceForModel($this->_model);
        foreach ($relations as $name) {
            $mr->_loadRelated($this, $name);
        }
    }

    /**
     * @param QC $qc
     */
    public function preLoad($qc) {
    }

    public function postLoad() {
    }

    /**
     * @param QC $qc
     */
    public function _preLoad($qc) {
        $abilities = $this->_structure->getAbilities();
        if (empty($abilities)) $abilities = array();

        foreach ($abilities as $abilityName => $abilityInfo) {
            ModelOperator::getAbilityInstanceForModel($this->_modelClass, $abilityName)->preLoad($this, $qc);
        }
        $this->preLoad($qc);
    }

    public function _postLoad() {
        $abilities = $this->_structure->getAbilities();
        if (empty($abilities)) $abilities = array();

        foreach ($abilities as $abilityName => $abilityInfo) {
            ModelOperator::getAbilityInstanceForModel($this->_modelClass, $abilityName)->postLoad($this);
        }
        $this->postLoad();
    }

    public function getArray() {
        $result = array();
        /**
         * @var Model $item
         */
        foreach ($this->_data as $item) {
            $result[] = $item->getArray();
        }
        return $result;
    }

    public function getArrayIndexedBy($fieldName) {
        $result = array();
        /**
         * @var Model $item
         */
        foreach ($this->_data as $item) {
            $result[$fieldName] = $item->getArray();
        }
        return $result;
    }

    public function getFieldArray($fieldName) {
        $res = array();
        foreach ($this->_data as $item) {
            $res[] = $item[$fieldName];
        }
        return $res;
    }

    /**
     * @param $id
     * @return Model
     */
    public function getOneByPK($id) {
        if (array_key_exists($id, $this->_pk_map)) {
            return $this->_data[$this->_pk_map[$id]];
        } else {
            return null;
        }
    }

    /**
     * @param array $ids
     * @return ModelCollection
     */
    public function getSubCollectionByPKs($ids) {
        $className  = get_class($this);
        $collection = new $className();
        foreach($ids as $idObject) {
            if (!empty($this->_data[$this->_pk_map[$idObject]])) {
                $collection[] = $this->_data[$this->_pk_map[$idObject]];
            }
        }
        return $collection;
    }

    /**
     * @return Model
     */
    public function getFirst() {
        $keys = array_keys($this->_data);
        if (!count($keys)) return null;
        return $this->_data[$keys[0]];
    }

    /**
     * @return Model
     */
    public function getLast() {
        $keys = array_keys($this->_data);
        if (!count($keys)) return null;
        return $this->_data[$keys[count($keys) - 1]];
    }

    public function getIDs() {
        return array_keys($this->_pk_map);
    }

    public function delete() {
        QC::create($this->_tableName)->delete(array($this->_primaryKey => $this->getIDs()))->execute();
    }

    protected function configure() {
    }

    public function _getName() {
        return $this->_model->_getName();
    }

    public function _getStructure() {
        return $this->_structure;
    }

    public function __call($method, $params) {
        if (substr($method, -10) == 'RelatedIDs') {
            ModelRelation::getInstanceForModel($this)->$method($this, $params[0], $params[1]);
        } elseif (substr($method, 0, 10) == 'setRelated') {
            ModelRelation::getInstanceForModel($this)->setRelatedIDs($this, substr($method, 10), $params[0]);
        } elseif (substr($method, 0, 12) == 'clearRelated') {
            ModelRelation::getInstanceForModel($this)->clearRelatedIDs($this, substr($method, 12), $params[0]);
        } elseif (in_array($method, $this->_modelsMethods)) {
            foreach($this->_data as $object) {
                /**
                 * @var Model $object
                 */
                call_user_func_array(array($object, $method), $params);
            }
        } elseif ($methodInfo = ModelOperator::getInstanceAbilityMethod($this->_modelClass, $method)) {
            array_unshift($params, $this);
            return call_user_func_array(array($methodInfo['ability'], $method), $params);
        }
        return $this;
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

    /**
     * @param mixed $offset
     * @param Model $value
     * @throws \Exception
     */
    public function offsetSet($offset, $value) {
        if (method_exists($this, 'set' . Inflector::camelize($offset))) {
            $this->{'set' . Inflector::camelize($offset)}($value);
        }
        if (is_null($offset)) {
            if (!is_object($value) || $value->isNew()) throw new \Exception('Trying to add empty or non-object item to collection');

            $offset = count($this->_data);
            if (array_key_exists($value->getID(), $this->_pk_map)) {
                throw new \Exception('Object with id ' . $value[$this->_primaryKey] . ' already exists in collection');
            }
            $this->_pk_map[$value->getID()] = $offset;
        }
        $this->_data[$offset] = $value;
    }

    public function offsetUnset($offset) {
        unset($this->_data[$offset]);
    }

} 