<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 13.04.14 17:05
 */

namespace Solve\Database;

use Solve\Database\Adapters\MysqlDBAdapter;


/**
 * Class Query Constructor
 * @package Solve\Database
 *
 * Class QC is used to create criteria for database queries
 *
 * @method QC from($tables) add tables part
 * @method QC leftJoin($table, $joinCriteria) add join
 * @method QC rightJoin($table, $joinCriteria) add join
 * @method QC innerJoin($table, $joinCriteria) add join
 * @method QC outerJoin($table, $joinCriteria) add join
 * @method QC join($table, $joinCriteria) add join
 * @method QC where($params) add criteria
 * @method QC and add criteria
 * @method QC or add criteria
 *
 * @method QC limit($params) limit query
 * @method QC groupBy($param) group by query param
 * @method QC orderBy($param) order by query param
 *
 * @method QC use ($field) use field as a value
 * @method QC indexBy($field) use field as a index
 * @method QC foldBy($field) use field for folding
 *
 * @method QC delete($where) delete rows with specified criteria
 * @method QC rawSelect($sql) select fields with specified SQL
 * @method QC select($fields) select fields with specified criteria
 * @method QC insert($data) insert data with specified criteria
 * @method QC replace($data) replace data with specified criteria
 * @method QC update($data) update rows with specified data
 *
 * @version 1.0
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 */
class QC {

    const TYPE_SELECT  = 'select';
    const TYPE_INSERT  = 'insert';
    const TYPE_DELETE  = 'delete';
    const TYPE_UPDATE  = 'update';
    const TYPE_REPLACE = 'replace';

    private static $_availableMethods = array(

        // criteria group
        'and'       => array(
            'group' => 'criteria'
        ),
        'or'        => array(
            'group' => 'criteria'
        ),
        'where'     => array(
            'group'  => 'criteria',
            'method' => 'and'
        ),


        // tables group
        'from'      => array(
            'group' => 'tables'
        ),

        'join'      => array(
            'group' => 'joins'
        ),
        'leftJoin'  => array(
            'group' => 'joins'
        ),
        'rightJoin' => array(
            'group' => 'joins'
        ),
        'innerJoin' => array(
            'group' => 'joins'
        ),
        'outerJoin' => array(
            'group' => 'joins'
        ),


        // modifiers group
        'limit'     => array(
            'group' => 'modifiers'
        ),
        'orderBy'   => array(
            'group' => 'modifiers'
        ),
        'groupBy'   => array(
            'group' => 'modifiers'
        ),
        'use'       => array(
            'group' => 'modifiers'
        ),
        'indexBy'   => array(
            'group' => 'modifiers'
        ),
        'foldBy'    => array(
            'group' => 'modifiers'
        ),
        'one'       => array(
            'group' => 'modifiers'
        ),
        'rawSelect' => array(
            'group' => 'modifiers'
        ),

        //system group
        'delete'    => array(
            'group'  => 'system',
            'params' => 'where'
        ),
        'select'    => array(
            'group'  => 'system',
            'params' => 'fields'
        ),
        'update'    => array(
            'group'  => 'system',
            'params' => 'data'
        ),
        'insert'    => array(
            'group'  => 'system',
            'params' => 'data'
        ),
        'replace'   => array(
            'group'  => 'system',
            'params' => 'data'
        ),

    );

    private $_params = array(
        'criteria'  => array(),
        'fields'    => array(),
        'data'      => array(),
        'joins'     => array(),
        'modifiers' => array(),
        'tables'    => array(),
    );

    private $_type   = self::TYPE_SELECT;
    private $_fields = array();
    private $_data   = array();

    private $_isBuilded  = false;
    private $_buildedSQL = '';
    /**
     * @var MysqlDBAdapter
     */
    private static $_staticAdapter = null;
    /**
     * @var MysqlDBAdapter
     */
    private $_adapter = null;

    public function __construct($tables = null) {
        if ($tables) {
            $this->from($tables);
        }
        if (empty(self::$_staticAdapter)) {
            self::$_staticAdapter = DatabaseService::getAdapter();
        }
        $this->_adapter = self::$_staticAdapter;
        return $this;
    }

    public static function setStaticAdapter($adapter) {
        self::$_staticAdapter = $adapter;
    }

    public static function getStaticAdapter() {
        return self::$_staticAdapter;
    }

    public function setAdapter($adapter) {
        $this->_adapter = $adapter;
    }

    public function getAdapter() {
        return $this->_adapter;
    }

    public static function create($tables = null) {
        return new QC($tables);
    }

    public function getCriteria() {
        return $this->_params['criteria'];
    }

    public function getQueryParams($paramsType) {
        return $paramsType ? $this->_params[$paramsType] : $this->_params;
    }

    public function getTables() {
        $tables = array();
        foreach ($this->_params['tables'] as $item) {
            $tables = array_merge($tables, is_array($item['params'][0]) ? $item['params'][0] : $item['params']);
        }

        return $tables;
    }

    public function getJoins() {
        $joins = array();
        foreach ($this->_params['joins'] as $join) {
            $type = $join['method'] == 'join' ? 'join' : strtolower(substr($join['method'], 0, -4));
            if (empty($joins[$type])) $joins[$type] = array();
            $joins[$type][] = $join['params'];
        }
        return $joins;
    }

    public function getModifiers() {
        return $this->_params['modifiers'];
    }

    public function getModifier($modifierName) {
        if (array_key_exists($modifierName, $this->_params['modifiers'])) {
            return $this->_params['modifiers'][$modifierName];
        } else {
            return null;
        }
    }

    public function isEmpty() {
        $countParams = 0;
        foreach ($this->_params as $item) $countParams += count($item);
        return $countParams == 0;
    }

    public function build() {
        if ($this->_isBuilded) return true;

        $this->_buildedSQL = $this->_adapter->buildQuery($this);
        return $this;
    }

    public function getSQL() {
        $this->build();

        return $this->_buildedSQL;
    }

    public function execute() {
        $result = $this->_adapter->executeQuery($this);
        if ($this->_type == QC::TYPE_SELECT) {
            $result = $this->processRows($result);
        }
        return $result;
    }

    public function executeOne() {
        $this->limit(1);
        $this->_params['modifiers']['one'] = true;
        return $this->execute();
    }

    public static function executeSQL($sql) {
        if (empty(self::$_staticAdapter)) {
            self::$_staticAdapter = DatabaseService::getAdapter();
        }
        return self::$_staticAdapter->executeSQL($sql);
    }

    public static function executeArrayOfSQL($array) {
        foreach ($array as $sql) {
            self::executeSQL($sql);
        }
    }

    public function processRows($rows) {
        if (empty($rows)) return array();
        if (($indexBy = $this->getModifier('indexBy')) && array_key_exists($indexBy[0], $rows[0])) $indexBy = $indexBy[0];
        if (($foldBy = $this->getModifier('foldBy')) && array_key_exists($foldBy[0], $rows[0])) $foldBy = $foldBy[0];
        $use   = $this->getModifier('use');
        $index = -1;
        $data  = array();

        foreach ($rows as $row) {
            $item = $row;
            if ($use) $item = array_key_exists($use[0], $row) ? $row[$use[0]] : null;
            if ($indexBy) {
                $index = $row[$indexBy];
            } else {
                $index++;
            }
            if ($foldBy) {
                $data[$row[$foldBy]][$index] = $item;
            } else {
                $data[$index] = $item;
            }
        }
        return $this->getModifier('one') ? array_shift($data) : $data;
    }

    public function getType() {
        return $this->_type;
    }

    public function data($data) {
        if (func_num_args() > 1) {
            $data = func_get_args();
        } else {
            if (!is_array($data)) $data = array($data);
        }
        $this->_params['data'] = array_merge($this->_params['data'], $data);
        return $this;
    }

    public function fields($fields) {
        if (!is_array($fields)) $fields = array($fields);
        $this->_params['fields'] = array_merge($this->_params['fields'], $fields);
        return $this;
    }

    public function addPart(QC $q, $method = null) {
        $c = $q->getCriteria();
        if (!empty($c)) {
            if (!$method) $method = 'and';

            $this->_params['criteria'][] = array(
                'method'    => $method,
                'isComplex' => true,
                'params'    => $c
            );
        }
        return $this;
    }

    public function importQC(QC $qc) {
        if ($modifiers = $qc->getModifiers()) {
            foreach ($modifiers as $modifierName => $params) {
                $this->_params['modifiers'][$modifierName] = $params;
            }
        }
        return $this;
    }

    /**
     * All magic goes here.
     * Each method has it's own group
     *
     * @param $method
     * @param $params
     * @return $this
     */
    public function __call($method, $params) {
        if (array_key_exists($method, self::$_availableMethods)) {
            $info = self::$_availableMethods[$method];

            if (array_key_exists(0, $params) && is_object($params[0]) && $params[0] instanceof QC) {
                $this->addPart($params[0], $method);
            } else {
                if ($info['group'] == 'modifiers') {
                    $this->_params['modifiers'][$method] = $params;
                } elseif ($info['group'] == 'system') {
                    $this->_type = $method;

                    if (count($params)) {
                        call_user_func_array(array($this, $info['params']), $params);
                    }
                } else {
                    $this->_params[$info['group']][] = array(
                        'method' => empty($info['method']) ? $method : $info['method'],
                        'params' => $params
                    );
                }
            }
        }
        return $this;
    }


    /**
     * Check parameter for validness as DB identifier
     * @static
     * @param $identifier
     * @return int
     */
    static public function isValidIdentifier($identifier) {
        $reg = '#^[-_a-z\.`]+[-_a-z\.`0-9]*$#isU';
        return preg_match($reg, $identifier);
    }
}