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
use Solve\Storage\YamlStorage;

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

    /**
     * @var YamlStorage
     */
    private $_yaml          = null;

    public function __construct($modelName, $data = null) {
        $this->_modelName = $modelName;
        if (!empty($data)) {
            $this->_data->setData($data);
        } else {
//            $this->_yaml = new YamlStorage('');
        }
    }

    /**
     * Return field specified as primary key or null
     * @return string|null
     */
    public function getPrimaryKey() {
        return $this->_data->get('indexes/primary/columns/0', null);
    }

    /**
     * Check if columns is exists
     * @param string $name
     * @return bool
     */
    public function isColumnExists($name) {
        return $this->_data->has('columns/' . $name);
    }

    public function getModelName() {
        return $this->_modelName;
    }

    public function getTable() {
        return $this->_data->get('table');
    }

    public function get($key = null) {
        return $this->_data->get($key);
    }
} 