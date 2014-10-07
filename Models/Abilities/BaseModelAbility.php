<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 12.05.14 00:24
 */

namespace Solve\Database\Models\Abilities;

use Solve\Database\Models\Model;
use Solve\Database\Models\ModelStructure;
use Solve\Utils\Inflector;

/**
 * Class BaseModelAbility
 * @package Solve\Database\Models\Abilities
 *
 * Class BaseModelAbility is a basic ability class
 *
 * @version 1.0
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 */
abstract class BaseModelAbility {

    protected $_params           = array();
    protected $_primaryKey;
    protected $_tableName;
    protected $_publishedMethods = array();
    /**
     * @var Model
     */
    protected      $_model;
    protected      $_modelStructure;
    private static $_modelInstances = array();

    /**
     * @param Model $model
     */
    private function __construct($model) {
        if (!isset($model)) return true;
        $this->_model          = $model;
        $this->_modelStructure = $model->_getStructure();
        $this->_params         = $model->_getStructure('abilities/' . Inflector::underscore(substr(get_class($this), 0, -7)));
        $this->_primaryKey     = $this->_modelStructure->getPrimaryKey();
        $this->_tableName      = $this->_model->_getStructure('table');
    }


    /**
     * @param Model $modelInstance
     */
    public static function getInstanceForModel($modelInstance) {
        $abilityClass = get_called_class();
        if (empty(self::$_modelInstances[$modelInstance->_getName()])) {
            self::$_modelInstances[$modelInstance->_getName()] = new $abilityClass($modelInstance);
        }
        return self::$_modelInstances[$modelInstance->_getName()];
    }

    /**
     * execute when ability attaches to the model first time
     */
    public function setup() {
    }

    /**
     * Execute when we detach ability from model
     * @param $objects
     */
    public function remove(&$objects) {
    }

    /**
     * executes every time when model creating
     */
    public function initialize() {
    }

    /**
     * Use for dynamic publishing actions
     * @param string $methodName
     */
    protected function publishMethod($methodName) {
        $this->_publishedMethods[$methodName] = array();
    }

    /**
     * published actions
     * @return array
     */
    public function getPublishedMethods() {
        return $this->_publishedMethods;
    }

    public function postLoad($caller) {
    }

    public function preSave($caller) {
    }

    public function postSave($caller) {
    }

    static public function getInitialStructure(ModelStructure $structure) {
        return 'true';
    }

    public function requireParametersCount($count, &$params) {
        if (is_array($params) && count($params) < $count) {
            throw new \Exception('Method require at less ' . $count . ' parameters');
        }
    }

    public function requireModeSingle($caller) {
        if (!$caller instanceof Model) {
            throw new \Exception('Method require single mode but you call it for collection');
        }
    }
} 