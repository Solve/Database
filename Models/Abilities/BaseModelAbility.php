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
 * Class BaseModelAbility is used to ...
 *
 * @version 1.0
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 */
abstract class BaseModelAbility {

    /**
     * @var array of params specified in slModel Structure
     */
    protected $_params      = array();

    /**
     * @var null|string primary key of current model
     */
    protected $_pk              = null;

    /**
     * @var string
     */
    protected $_table           = null;

    /**
     * @var array actions that available in suggestion
     */
    public $_mixed_methods   = array();


    /**
     * @var Model
     */
    protected $_modelName   = null;

    /**
     * @param Model $model
     */
    public function __construct($model) {
        if (!isset($model)) return true;
        $ability_name   = Inflector::underscore(substr(get_class($this), 0, -7));
        $this->_modelName   = $model;
        $this->_params  = $model->getStructure('abilities/'.$ability_name);
        $this->_pk      = $this->_modelName->getStructure()->getPrimaryField();
        $this->_table   = $this->_modelName->getStructure('table');
    }

    /**
     * execute when ability attaches to the model first time
     */
    public function setUp() {}

    /**
     * Execute when we detach ability from model
     * @param $objects
     */
    public function unSetUp(&$objects) {}

    /**
     * Execute when we delete objects
     * @param $objects
     */
    public function unlink(&$objects) {}


    /**
     * executes every time when model creating
     */
    public function bootstrap() {}

    /**
     * Use for dynamic publishing actions
     * @param string $action_name
     */
    protected function publishAction($action_name) {
        $this->_mixed_methods[$action_name] = array();
    }

    /**
     * published actions
     * @return array
     */
    public function getPublishedActions() {
        return $this->_mixed_methods;
    }

    public function preSave(&$changed, &$all) {}
    public function postSave(&$changed, &$all) {}


    static public function getInitialStructure(ModelStructure $structure) {
        return 'true';
    }

    public function requireParametersCount($count, &$params) {
        if (is_array($params) && count($params) < $count) {
            throw new \Exception('Method require at less '.$count.' parameters');
        }
    }

    public function requireModeSingle(&$objects) {
        if (!is_array($objects) || (count($objects) > 1)) {
            throw new \Exception('Method require single mode but you call it for collection');
        }
    }
} 