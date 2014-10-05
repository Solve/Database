<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 05.10.14 08:56
 */

namespace Solve\Database\Models;
use Solve\Database\QC;

/**
 * Class ModelRelation
 * @package Solve\Database\Models
 *
 * Class ModelRelation is used to operate with model relations
 *
 * @version 1.0
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 */
class ModelRelation {


    private $_structure;
    private $_modelName;

    private static $_instancesForModels = array();

    /**
     * @param Model $caller
     */
    private function __construct($caller) {
        $this->_structure = $caller->_getStructure();
        $this->_modelName = $caller->_getName();
    }

    /**
     * @param Model|ModelCollection $caller
     * @return ModelRelation
     */
    public static function getInstanceForModel($caller) {
        $name = $caller->_getName();
        if (empty(self::$_instancesForModels[$name])) {
            self::$_instancesForModels[$name] = new self($caller);
        }
        return self::$_instancesForModels[$name];
    }

    /**
     * @param Model|ModelCollection $caller
     * @param string $relationName
     */
    public function loadRelative($caller, $relationName) {
        $config = $this->_structure->getRelationInfo($relationName);
        $ids    = ModelOperator::getIDs($caller);

        /**
         * @var $localKey
         * @var $localField
         * @var $foreignKey
         * @var $foreignField
         * @var $localTable
         * @var $foreignTable
         * @var $manyTable
         * @var $type
         * @var $relatedModel
         * @var $hydration
         * @var $fieldsToRetrieve
         */
        extract(ModelOperator::calculateRelationVariables($caller, $relationName));

        $relatedIds = array();
        if ($type == 'many_to_one') {
            $relatedIds = ModelOperator::getFieldArray($caller, $localField);
        }
        if (empty($relatedIds)) return false;

        if ($hydration == 'simple') {
            $data = QC::create($foreignTable)->select($fieldsToRetrieve)
                ->where(array($foreignField . '.' . $foreignKey => $relatedIds))
                ->indexBy($foreignKey)
                ->execute();
            $caller[$relationName] = $data;
        }
    }

} 