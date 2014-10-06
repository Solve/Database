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
     * @param $caller
     * @param $relationName
     * @return $this
     * @throws \Exception
     */
    public function loadRelative($caller, $relationName) {
        /**
         * @var $localKey
         * @var $localField
         * @var $foreignKey
         * @var $foreignField
         * @var $localTable
         * @var $foreignTable
         * @var $manyTable
         * @var $relationType
         * @var $relatedModelName
         * @var $hydration
         * @var $fieldsToRetrieve
         * @var $relationToMany
         */
        $varsToExport = ModelOperator::calculateRelationVariables($caller, $relationName);
//        var_dump($varsToExport);die();
        extract($varsToExport);

        $foreignIds     = array();
        $idsMap         = array();
        $originalCaller = null;

        if (($caller instanceof Model) && $caller->hasCollectionReference()) {
            $originalCaller = $caller;
            $caller = $caller->getCollectionReference();
        }
        if ($relationType == 'many_to_one') {
            $foreignIds = ModelOperator::getFieldArray($caller, $localField);
        } elseif ($relationToMany) {
            $localIds = ModelOperator::getIDs($caller);

            $tableToSelect = $manyTable;
            if ($relationType == 'one_to_many') {
                $tableToSelect = $foreignTable;
            }
            $idsMap = QC::create($tableToSelect)->where(array($tableToSelect . '.' . $foreignField => $localIds))
                        ->select($foreignKey . ', ' . $foreignField)
                        ->foldBy($foreignField)
                        ->use($foreignKey)
                        ->execute();
            foreach ($idsMap as $value) {
                $foreignIds = array_merge($foreignIds, $value);
            }
        }
        $foreignIds = array_unique($foreignIds);
        if (empty($foreignIds)) {
            return false;
        } elseif ($foreignKey == 'id') {
            $ids = array();
            foreach ($foreignIds as $key => $value) {
                $ids[] = intval($value);
            }
            $foreignIds = $ids;
        }

        /**
         * @var ModelCollection $relatedCollection
         */
        $relatedCollection = call_user_func(array($relatedModelName, 'loadList'), $foreignIds);
        $callerObjects     = ($caller instanceof ModelCollection) ? $caller : array($caller);

        /**
         * @var Model $object
         */
        foreach ($callerObjects as $object) {
            if (substr($relationType, '-6') == 'to_one') {
                $relationValue = $relatedCollection->getOneByPK($object[$localField]);
            } else {
                $relationValue = $relatedCollection->getSubCollectionByPKs($idsMap[$object->getID()]);
            }
            $object->_setRawFieldValue($relationName, $relationValue);
        }
        return $this;
    }

} 