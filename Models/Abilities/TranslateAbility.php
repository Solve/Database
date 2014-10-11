<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 08.10.14 23:45
 */

namespace Solve\Database\Models\Abilities;

use Solve\Database\Models\DBOperator;
use Solve\Database\Models\Model;
use Solve\Database\Models\ModelCollection;
use Solve\Database\Models\ModelOperator;
use Solve\Database\QC;
use Solve\Utils\Inflector;

/**
 * Class TranslateAbility
 * @package Solve\Database\Models\Abilities
 *
 * Class TranslateAbility is a model ability for multi languages models
 *
 * @publish loadTranslation($id_language)
 * @publish getTranslationForLanguage($id_language)
 * @publish getAllTranslations()
 *
 * @version 1.0
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 */
class TranslateAbility extends BaseModelAbility {

    static private $_activeLanguageId;
    private        $_dataToSave = array();

    public function setup() {
        $this->reloadConfig();
        $columnsToTranslate = $this->detectTranslatableColumns();
        if (empty($this->_config['columns'])) {
            $this->_config['columns'] = array_keys($columnsToTranslate);
        }
        if (empty($columnsToTranslate)) throw new \Exception('You have to specify at least 1 column for translate in ' . $this->_modelName->_getName());

        $this->_tableName                         = $this->_modelStructure->getTableName() . '_translate';
        $tableStructure                           = array(
            'columns' => array(),
            'indexes' => array(
                $this->_tableName . '_translate' => array(
                    'columns' => array('id_language', 'id_object'),
                    'type'    => 'unique'
                )
            ),
        );
        $keyType                                  = $this->_modelStructure->getColumnInfo($this->_primaryKey);
        $tableStructure['columns']['id_object']   = array('type' => $keyType['type']);
        $tableStructure['columns']['id_language'] = array('type' => 'char(2)');
        $tableStructure['columns']                = array_merge($tableStructure['columns'], $columnsToTranslate);

        DBOperator::getInstance()->createTable($this->_tableName, $tableStructure);
        return $this;
    }

    public function cleanup() {
        $this->reloadConfig();
        if (empty($this->_config['columns'])) return $this;

        foreach ($this->_config['columns'] as $columnName) {
            $this->_modelStructure->updateColumnInfo($columnName, array('virtual' => 'translate'));
        }
        $this->_modelStructure->saveStructure();
        $this->_modelStructure->updateDatabaseStructure(false);
        return $this;
    }

    /**
     * @param Model|ModelCollection $caller
     * @param $languageId
     */
    public function loadTranslation($caller, $languageId) {
        $data = $this->getTranslationForLanguage($caller, $languageId);
        if ($caller instanceof Model) {
            $caller->_setRawFieldData($data);
        } else {
            foreach ($data as $objectId => $row) {
                $caller->getOneByPK($objectId)->_setRawFieldData($row);
            }
        }
    }

    public function getAllTranslations($caller) {
        return $this->getDataForCaller($caller);
    }

    /**
     * @param Model|ModelCollection $caller
     * @param $languageId
     * @return mixed
     */
    public function getTranslationForLanguage($caller, $languageId) {
        $ids  = ModelOperator::getIDs($caller);
        $data = QC::create($this->_modelStructure->getTableName())
                  ->where(array($this->_modelStructure->getTableName() . '.' . $this->_modelStructure->getPrimaryKey() => $ids))
                  ->indexBy($this->_modelStructure->getPrimaryKey())
                  ->leftJoin($this->_tableName, $this->_tableName . '.id_object = ' . $this->_modelStructure->getTableName() . '.' . $this->_primaryKey . ' AND ' . $this->_tableName . '.id_language = ' . $languageId)
                  ->execute();
        return $caller instanceof Model ? $data[$caller->getID()] : $data;
    }

    /**
     * @param Model|ModelCollection $caller
     * @return array|mixed
     */
    private function getDataForCaller($caller) {
        $ids          = ModelOperator::getIDs($caller);

        $qc           = QC::create($this->_modelStructure->getTableName())
                          ->where(array($this->_modelStructure->getTableName() . '.' . $this->_modelStructure->getPrimaryKey() => $ids))
                          ->foldBy($this->_modelStructure->getPrimaryKey())
                          ->leftJoin($this->_tableName, $this->_tableName . '.id_object = ' . $this->_modelStructure->getTableName() . '.' . $this->_primaryKey);
        $qc->indexBy('id_language');
        $data = $qc->execute();
        return $caller instanceof Model ? $data[$caller->getId()] : $data;
    }

    public function initialize() {
        $this->_tableName = $this->_modelStructure->getTableName() . '_translate';
        $this->publishMethod('loadTranslation');
        $this->publishMethod('getAllTranslations');
        $this->publishMethod('getTranslationForLanguage');
    }

    public function preLoad($caller, $qc) {
        $qc->leftJoin($this->_tableName, $this->_tableName . '.id_object = ' . $this->_modelStructure->getTableName() . '.' . $this->_primaryKey)
           ->where($this->_tableName . '.id_language = :d', self::getLanguageId());
    }

    public function preSave($caller) {
        $changedData    = $caller->getChangedData();
        $translatedData = array();
        foreach ($this->_config['columns'] as $columnName) {
            if (in_array($columnName, $this->_config['columns'])) {
                $translatedData[$columnName] = $changedData[$columnName];
                $caller->clearChangedData($columnName);
            }
            if (!empty($translatedData)) {
                $translatedData['id_language'] = self::$_activeLanguageId;
            }
        }
        $this->_dataToSave[$caller->getInternalObjectHash()] = $translatedData;
    }

    public function postSave($caller) {
        if (empty($this->_dataToSave[$caller->getInternalObjectHash()])) return true;

        $data              = $this->_dataToSave[$caller->getInternalObjectHash()];
        $data['id_object'] = $caller->getID();
        QC::create($this->_tableName)->replace($data)->execute();
        $caller->_setRawFieldData($data);
        unset($this->_dataToSave[$caller->getInternalObjectHash()]);
        return true;
    }

    public function preDelete($caller) {
        $ids = ModelOperator::getIDs($caller);
        QC::create($this->_tableName)->delete(array('id_object'=>$ids))->execute();
    }


    private function detectTranslatableColumns() {
        $columns = array();
        foreach ($this->_modelStructure->getColumns() as $columnName => $columnInfo) {
            if ((strpos($columnInfo['type'], 'varchar') === 0)
                || (strpos($columnInfo['type'], 'text') !== false)
                || (strpos($columnInfo['type'], 'char') === 0)
                || (strpos($columnInfo['type'], 'blob') === 0)
            ) {

                $columns[$columnName] = $columnInfo;
            }
        }
        return $columns;
    }

    public static function setLanguageId($languageId) {
        self::$_activeLanguageId = $languageId;
    }

    public static function getLanguageId() {
        return self::$_activeLanguageId;
    }

}