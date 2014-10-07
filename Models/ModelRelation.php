<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 05.10.14 08:56
 */

namespace Solve\Database\Models;

use SebastianBergmann\Exporter\Exception;
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
     * @param Model|ModelCollection|array $foreignIDs
     * @throws \Exception
     */
    public function setRelatedIDs($caller, $relationName, $foreignIDs) {
        if (is_object($foreignIDs)) {
            if ($foreignIDs instanceof Model) {
                $foreignIDs = array($foreignIDs->getID());
            } elseif ($foreignIDs instanceof ModelCollection) {
                $foreignIDs = $foreignIDs->getIDs();
            }
        }

        $info = ModelOperator::calculateRelationVariables($caller, $relationName);
        if (!is_array($foreignIDs)) $foreignIDs = array($foreignIDs);

        $localIDs = ModelOperator::getIDs($caller);

        if (substr($info['relationType'], -6) == 'to_one') {
            if (count($foreignIDs) > 1) throw new \Exception('You can\'t set array of destination ids ManyToOne');

            $idRelationObject = is_array($foreignIDs) ? $foreignIDs[0] : $foreignIDs;
            QC::create($info['localTable'])
                ->update(array($info['localField']=>$idRelationObject))
                ->where(array($info['localKey']=>$localIDs))
                ->execute();
            $caller->_setRawFieldValue($info['localField'], $idRelationObject);
        } elseif ($info['relationType'] == 'one_to_many') {
            if (count($localIDs) > 1) throw new \Exception('You can\'t set array of source ids OneToMany');
            QC::create($info['foreignTable'])
                ->update(array($info['foreignField']=>$localIDs[0]))
                ->where(array($info['foreignKey']=>$foreignIDs))
                ->execute();
        } else {
            $data = array();
            foreach($localIDs as $fid) {
                foreach($foreignIDs as $lid) {
                    $data[] = array($info['localField'] => $lid, $info['foreignField'] => $fid);
                }
            }
            QC::create($info['manyTable'])
              ->replace($data)
              ->execute();
        }
        $caller->loadRelated($relationName);
    }

    /**
     * @param Model|ModelCollection $caller
     * @param string $relationName
     * @param Model|ModelCollection|array $foreignIDs
     * @throws \Exception
     */
    public function clearRelatedIDs($caller, $relationName, $foreignIDs = array()) {
        if (is_object($foreignIDs)) {
            if ($foreignIDs instanceof Model) {
                $foreignIDs = array($foreignIDs->getID());
            } elseif ($foreignIDs instanceof ModelCollection) {
                $foreignIDs = $foreignIDs->getIDs();
            }
        }

        $info = ModelOperator::calculateRelationVariables($caller, $relationName);
        if (!is_array($foreignIDs)) $foreignIDs = array($foreignIDs);

        $localIDs = ModelOperator::getIDs($caller);
        $qc = QC::create();
        if (substr($info['relationType'], -6) == 'to_one') {
            $qc->from($info['localTable'])
              ->update(array($info['localField']=>null))
              ->where(array($info['localKey']=>$localIDs));

            $caller->_setRawFieldValue($info['localField'], null);

        } elseif ($info['relationType'] == 'one_to_many') {
            $qc->from($info['foreignTable'])
              ->update(array($info['foreignField']=>null))
              ->where(array($info['foreignField']=>$localIDs));

            if (!empty($foreignIDs)) $qc->where(array($info['foreignKey']=>$foreignIDs));
        } else {
            $qc->from($info['manyTable'])->delete(array($info['foreignField']=>$localIDs));
            if (!empty($foreignIDs)) $qc->where(array($info['localField']=>$foreignIDs));
        }
        $qc->execute();
        $caller->loadRelated($relationName);
    }

    /**
     * @param $caller
     * @param $relationName
     * @return $this
     * @throws \Exception
     */
    public function _loadRelated($caller, $relationName) {
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
        extract($varsToExport);

        $foreignIds     = array();
        $idsMap         = array();
        $originalCaller = null;

        if (($caller instanceof Model) && $caller->_hasCollectionReference()) {
            $caller = $caller->_getCollectionReference();
        }
        if ($relationType == 'many_to_one') {
            $foreignIds = ModelOperator::getFieldArray($caller, $localField);
        } elseif ($relationToMany) {
            $localIds = ModelOperator::getIDs($caller);

            $tableToSelect = $foreignTable;
            $fieldToSelect = $foreignKey;
            if ($relationType == 'many_to_many') {
                $tableToSelect = $manyTable;
                $fieldToSelect = $localField;
            }
            $idsMap = QC::create($tableToSelect)->where(array($tableToSelect . '.' . $foreignField => $localIds))
                        ->select($fieldToSelect . ', ' . $foreignField)
                        ->foldBy($foreignField)
                        ->use($fieldToSelect)
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