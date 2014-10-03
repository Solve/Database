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

    private $_modelName     = null;

    /**
     * @var ArrayStorage
     */
    private $_data          = null;

    public function __construct($modelName, $data = null) {
        $this->_modelName = $modelName;
        if (!empty($data)) {
            $this->_data->setData($data);
        } else {
            $this->_data = new ArrayStorage(ModelOperator::getInstance()->getModelStructure($modelName));
        }
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
    public function isColumnExist($name) {
        $info = $this->_data->getDeepValue('columns/' . $name);
        return !empty($info);
    }

    public function addColumn($name, $info) {
        if (!$this->isColumnExist($name)) {
            $this->_data->setDeepValue('columns/'.$name, $info);
        }
    }

    public function addRelation($name, $info = null) {
        if (empty($info)) {
            $info = array(
                'model' => ucfirst($name),
                'type'  => 'many_to_one'
            );
        }
        $this->_data->setDeepValue('relations/'.$name, $info);
    }

    public function saveStructure() {
        ModelOperator::getInstance()->setStructureForModel($this->_modelName, $this->_data->getArray());
        ModelOperator::getInstance()->saveModelStructure($this->_modelName);
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

    public function getRelations(){
        return $this->_data->get('relations');
    }

    public function get($key = null) {
        return $this->_data->get($key);
    }

}