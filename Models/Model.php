<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 10.05.14 18:10
 */

namespace Solve\Database\Models;
require_once 'ModelStructure.php';

/**
 * Class Model
 * @package Solve\Database\Models
 *
 * @version 1.0
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 */
class Model extends \ArrayIterator {

    /**
     * @var ModelStructure
     */
    private $_structure         = null;

    /**
     * @var null|string instance model name
     */
    private $_name              = null;

    public function __construct($data = array()) {
        $this->_name = get_class($this);
        $this->_structure = new ModelStructure($this->_name);
        $this->configure();
    }

    /**
     * Merge current data with provided
     * @param $data
     */
    public function mergeWithData($data) {

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

    /**
     * Could be used for configuring model after constructor is done
     */
    protected function configure() { }

} 