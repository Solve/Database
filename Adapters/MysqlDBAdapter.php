<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 07.05.14 16:53
 */

namespace Solve\Database\Adapters;
use Solve\Database\QC;
use Solve\Database\Exceptions\MysqlDBAdapterException;

require_once 'BaseDBAdapter.php';
require_once dirname(__FILE__) . '/../Exceptions/MysqlDBAdapterException.php';

/**
 * Class MysqlDBAdapter
 * @package Solve\Database\Adapters
 *
 * Class MysqlDBAdapter is used to ...
 *
 * @version 1.0
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 */
class MysqlDBAdapter extends BaseDBAdapter {

    private $_pregParametricParams      = null;
    private $_pregParametricCounter     = 1;

    /**
     * @var \PDO connection
     */
    private $_dbh                       = null;

    public function connect($options) {
        if (!extension_loaded('pdo')) throw new \Exception('PDO extension is not loaded.');
        try {
            $this->_dbh = new \PDO(($dsn = self::compileDSN($options)), $options['user'], $options['pass']);
            $this->_dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch(\PDOException $e) {
            throw new MysqlDBAdapterException('PDO Connection Error: ' . $e->getMessage());
        }

        try {
            if (!empty($options['name'])) {
                $this->_dbh->query('USE '.$options['name']);
            }
            $this->_dbh->setAttribute(\PDO::ATTR_AUTOCOMMIT , true);
            if (!empty($options['charset'])) {
                $this->_dbh->query('SET NAMES '.$options['charset']);
            }
        } catch (\PDOException $e) {
            throw new MysqlDBAdapterException('PDO Connection Error: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Execute query
     *
     * @param QC $query
     * @throws MysqlDBAdapterException
     */
    public function executeQuery(QC $query) {
        $type   = $query->getType();
        $methodName = 'execute' . ucfirst($type);
        if (method_exists($this, $methodName)) {
            $result = $this->$methodName($query);
        } else {
            throw new MysqlDBAdapterException('Invalid method specified: '.$type);
        }

        return $result;
    }

    /**
     * Process query to SQL
     * @param QC $qc
     * @param mixed $type
     * @return mixed
     * @throws MysqlDBAdapterException
     */
    public function buildQuery(QC $qc, $type = null) {
        if (!$type) $type = $qc->getType();

        $methodName = 'build' . ucfirst($type);
        if (method_exists($this, $methodName)) {
            $result = $this->$methodName($qc);
        } else {
            throw new MysqlDBAdapterException('Invalid method specified: '.$type);
        }
        return $result;
    }

    private function buildSelect(QC $q) {
        if ($q->getModifier('rawSelect')) {
            $sqlArray = $q->getModifier('rawSelect');
            return $sqlArray[0];
        }

        $sql = 'SELECT ';

        $fields = $q->getQueryParams('fields');
        if (empty($fields)) {
            $fields = '*';
        } else {
            $fields = $this->escapeSqlName($fields);
        }
        $sql .= $fields . ' FROM ';
        $sql .= $this->escapeSqlName($this->requireTable($q));

        $joins = $q->getJoins();
        if (!empty($joins)) {
            $sql .= ' ';
            foreach($joins as $type => $list) {
                foreach($list as $join) {
                    $sql .= strtoupper($type) . ' JOIN '. $join[0]
                        .(strpos($join[1], ' ') === false ? ' USING ('.$this->escapeSqlName($join[1]) : ' ON ('.$join[1]).')';
                }
            }
        }

        $c = $q->getCriteria();
        if (!empty($c)) {
            $sql .= ' WHERE ' . $this->processCriteria($c);
        }
        $sql .= $this->processModifiers($q);
        return $sql;
    }

    /**
     * @param \PDOStatement $res
     * @return array
     */
    public function fetchSelectResult($res) {
        return $res->rowCount() ? $res->fetchAll(\PDO::FETCH_ASSOC) : array();
    }

    private function executeSelect(QC $q) {
        $sql = $this->buildSelect($q);

        $res = $this->_dbh->query($sql);
        return $this->fetchSelectResult($res);
    }

    private function buildInsert(QC $q) {
        $sql = 'INSERT INTO' . ' ' . $this->escapeSqlName($q->getTables()) . ' ';

        $keys = array();
        $data = $q->getQueryParams('data');
        if (count($data)) {
            if (!array_key_exists(0, $data)) {
                $data = array($data);
            }
            foreach($data as $i=>$item) {
                if (!is_array($item)) continue;

                if (array_key_exists(0, $item)) {
                    unset($data[$i]);
                    foreach($item as $sub_item) {
                        $keys = array_merge($keys, array_keys($sub_item));
                        $data[] = $sub_item;
                    }
                } else {
                    $keys = array_merge($keys, array_keys($item));
                }
            }
            $keys = array_unique($keys);
            if (count($keys)) {
                $sql .= '(`'.implode('`, `',$keys).'`) VALUES ';
                foreach($data as $item) {
                    $sql .= '(';
                    foreach($keys as $key) {
                        $sql .= (isset($item[$key]) ? $this->escapeOne($item[$key]) : '""').", ";
                    }
                    $sql = substr($sql, 0, -2).'), ';
                }
            } else {
                $sql.= 'SET ';
                foreach($q->getQueryParams('data') as $item) $sql.= $item.',';
            }
            $sql = substr($sql, 0, -2);
        } else {
            $sql .= '() VALUES ()';
        }
        $sql .= $this->processModifiers($q);

        return $sql;
    }

    private function executeInsert(QC $q) {
        $sql = $this->buildInsert($q);
        $res = $this->_dbh->query($sql);
        return $res ? $this->_dbh->lastInsertId() : false;
    }

    private function buildReplace(QC $q) {
        $sql = $this->buildInsert($q);
        $sql = str_replace('INSERT', 'REPLACE', $sql);

        return $sql;
    }

    private function executeReplace(QC $q) {
        $sql = $this->buildReplace($q);
        $res = $this->_dbh->query($sql);
        return $res ? $this->_dbh->lastInsertId() : false;
    }

    private function buildUpdate(QC $q) {
//        var_dump($q->getQueryParams('data'));die();
        $sql = 'UPDATE ' . $this->escapeSqlName($q->getTables())
                .' SET '.$this->processData($q->getQueryParams('data'), ', ');
        $c = $q->getCriteria();
        if (!empty($c)) {
            $sql .= ' WHERE ' . $this->processCriteria($c);
        }
        $sql .= $this->processModifiers($q);
        return $sql;
    }

    private function executeUpdate(QC $q) {
        $sql = $this->buildUpdate($q);
        $res = $this->_dbh->query($sql);
        return $res ? $res->rowCount() : false;
    }

    private function buildDelete(QC $q) {
        $sql = 'DELETE FROM' . ' ' . $this->escapeSqlName($q->getTables());

        $c = $q->getCriteria();
        if (!empty($c)) {
            $sql .= ' WHERE ' . $this->processCriteria($c);
        }
        $sql .= $this->processModifiers($q);

        return $sql;
    }

    private function executeDelete(QC $q) {
        $sql = $this->buildDelete($q);
        $res = $this->_dbh->query($sql);
        return $res ? $res->rowCount() : false;
    }

    private function requireTable(QC $q) {
        if (!($tables = $q->getTables())) {
            throw new MysqlDBAdapterException('This query requires a table to be specified');
        }
        return $tables;
    }

    private function processModifiers(QC $q) {
        $sql = '';
        if (($groupBy = $q->getModifier('groupBy'))) $sql .= ' GROUP BY ' . $this->escapeSqlName($groupBy[0]);
        if (($orderBy = $q->getModifier('orderBy'))) $sql .= ' ORDER BY ' . $this->escapeSqlName($orderBy[0]);
        if ($q->getModifier('one')) {
            $sql .= ' LIMIT 1';
        } elseif(($limit = $q->getModifier('limit'))) {
            if (count($limit) > 1) $limit[0] = implode(', ', $limit);
            $sql .= ' LIMIT '.$limit[0];
        }
        return $sql;
    }

    private function processCriteria($params) {
        $sql = '';
        foreach($params as $p) {
            if (!empty($p['isComplex'])) {
                $sql .= ' '.strtoupper($p['method']) . ' ' . $this->processCriteria($p['params']);
            } else {
                $sql .= ' '.strtoupper($p['method']) . ' (' . $this->processParametricCondition($p['params']) . ')';
            }
        }
        $sql = '(' . substr($sql, strlen($params[0]['method']) + 2) . ')';

        return $sql;
    }

    private function processData($data, $glue = ',') {
        $sql = '';
        if (array_key_exists(0, $data)) {
//            foreach($data as $item) {
//                $sql .= $this->processData($item, $glue);
//            }
            $sql .= $this->processParametricCondition($data);
        } else {
            foreach($data as $field => $value) {
                $sql .=  $glue . ' ' . $this->processParametricCondition(array($field => $value));
            }
            $sql = substr($sql, strlen($glue) + 1);
        }

        return $sql;
    }

    public function executeSQL($sql) {
        return $this->_dbh->query($sql);
    }

    private function processParametricCondition($condition) {
        $sql = '';
        if (count($condition) > 1) {
            if ($this->isValidSQLIdentifier($condition[0])) {
                $sql = $this->escapeSqlName($condition[0]);
                if (is_array($condition[1])) {
                    $sql .= ' IN (' . $this->escape($condition[1]);
                } else {
                    $sql .= ' = ' . $this->escapeOne($condition[1]);
                }
            } else {
                $savedParams = $this->_pregParametricParams;
                $this->_pregParametricParams = $condition;
                $sql = preg_replace_callback('#:[dsbna]#', array($this, 'onPregParameterCallback'), $condition[0]);
                $this->_pregParametricParams = $savedParams;
                $this->_pregParametricCounter = 1;
            }
        } else {
            if (array_key_exists(0, $condition) && is_string($condition[0])) {
                $sql .= $condition[0];
            } else {
                if (array_key_exists(0, $condition)) {
                    $condition = $condition[0];
                }
                foreach($condition as $key=>$value) {
                    if (is_scalar($value)) {
                        $sql .=  $this->escapeSqlName($key) . ' = ' . $this->escapeOne($value);
                    } else {
                        $sql .=  $this->escapeSqlName($key) . ' IN (' . $this->escape($value) . ')';
                    }
                }
            }
        }
        return $sql;
    }

    private function onPregParameterCallback($params) {
        if (array_key_exists($this->_pregParametricCounter, $this->_pregParametricParams)) {
            if ($params[0] == ':d') {
                return intval($this->_pregParametricParams[$this->_pregParametricCounter++]);
            } elseif ($params[0] == ':s') {
                return $this->escapeOne($this->_pregParametricParams[$this->_pregParametricCounter++], 'string');
            }
        } else {
            throw new MysqlDBAdapterException('Invalid parameters count for parametric query');
        }
    }

    /**
     * Escape on value using database driver specific
     *
     * @param mixed $value to be escaped
     * @param mixed $type force type definition
     * @return mixed escaped
     */
    protected function escapeOne($value, $type = false) {
        if ($type === false) {
            $type = gettype($value);
        }
        if (in_array($type, array('integer'))) {
            return $value;
        } elseif ($type == 'boolean') {
            return intval($value);
        } elseif ($type == 'NULL') {
            return 'null';
        } else {
            return $this->_dbh->quote($value);
        }
    }

    public function __destruct() {
    }


}