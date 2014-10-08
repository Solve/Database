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
use Solve\Database\Models\ModelCollection;
use Solve\Database\Models\ModelStructure;
use Solve\Database\QC;
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

    protected      $_config                 = array();
    protected      $_primaryKey;
    protected      $_tableName;
    protected      $_publishedMethods       = array();
    protected      $_publishedStaticMethods = array();
    protected      $_modelName;
    protected      $_modelStructure;
    private static $_modelInstances         = array();

    private function __construct($modelName) {
        $this->_modelName      = $modelName;
        $this->_modelStructure = ModelStructure::getInstanceForModel($modelName);
        $this->_primaryKey     = $this->_modelStructure->getPrimaryKey();
        $this->_tableName      = $this->_modelStructure->getTableName();
        $this->reloadConfig();
    }

    protected function reloadConfig() {
        $abilityName   = get_class($this);
        $abilityName   = Inflector::underscore(substr($abilityName, strrpos($abilityName, '\\') + 1, -7));
        $this->_config = $this->_modelStructure->getAbilityInfo($abilityName);
    }

    public static function getInstanceForModel($modelName) {
        $abilityClass = get_called_class();
        if (empty(self::$_modelInstances[$modelName])) {
            self::$_modelInstances[$modelName] = new $abilityClass($modelName);
        }
        return self::$_modelInstances[$modelName];
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

    public function cleanup() {
    }

    /**
     * Use for dynamic publishing actions
     * @param string $methodName
     * @param bool $isStatic
     */
    protected function publishMethod($methodName, $isStatic = false) {
        if ($isStatic) {
            $this->_publishedStaticMethods[$methodName] = array();
        } else {
            $this->_publishedMethods[$methodName] = array();
        }
    }

    /**
     * published actions
     * @return array
     */
    public function getPublishedMethods() {
        return $this->_publishedMethods;
    }

    public function getPublishedStaticMethods() {
        return $this->_publishedStaticMethods;
    }

    /**
     * @param Model|ModelCollection $caller
     * @param QC $qc
     */
    public function preLoad($caller, $qc) {
    }

    /**
     * @param Model|ModelCollection $caller
     */
    public function postLoad($caller) {
    }

    /**
     * @param Model|ModelCollection $caller
     */
    public function preSave($caller) {
    }

    /**
     * @param Model|ModelCollection $caller
     */
    public function postSave($caller) {
    }

    static public function getInitialStructure() {
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