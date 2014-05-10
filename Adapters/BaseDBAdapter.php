<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 07.05.14 16:46
 */

namespace Solve\Database\Adapters;
use Solve\Database\QC;

/**
 * Class BaseAdapter
 * @package Solve\Database\Adapters
 *
 * Class BaseAdapter is used to ...
 *
 * @version 1.0
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 */
abstract class BaseDBAdapter {

    private $_handler   = null;

    public function __construct($options) {
        if (!$options) throw new \Exception('Options for connection doesn\'t specified!');

        if ($this->connect($options) !== true) {
            throw new \Exception('Cannot connect to Database via Adapter');
        }
    }

    /**
     * Should be realized connect to database
     * @param mixed $options for connection
     */
    abstract public function connect($options);

    /**
     * Execute query
     * @abstract
     * @param QC $query
     */
    abstract public function executeQuery(QC $query);

    /**
     * Process query to SQL
     * @abstract
     * @param QC $qc
     */
    abstract public function buildQuery(QC $qc);

    /**
     * Parse database config and prepare DSN
     *
     * @static
     * @param @params part of database config
     * @return array
     */
    static public function compileDSN(&$params) {
        $dsn = '';
        if (!isset($params['user'])) $params['user'] = 'root';
        if (!isset($params['pass'])) $params['pass'] = null;
        if (!isset($params['type'])) $params['type'] = 'mysql';
        if (!isset($params['host'])) $params['host'] = '127.0.0.1';

        if (!empty($params['dsn'])) {
            $dsn = $params['dsn'];
        } else {
            $dsn = $params['type'] . ':host='.$params['host'];
        }
        return $dsn;
    }

    /**
     * Escape on value using database driver specific
     * @abstract
     * @param mixed $value to be escaped
     * @return mixed escaped
     */
    abstract protected function escapeOne($value);

    /**
     * Escape values for using in queries
     *
     * @param mixed $value to be escaped
     * @return mixed $value  Escaped
     */
    public function escape($value) {
        if (is_array($value)) {
            $res = '';
            if (count($value) == 0) return 'null';
            foreach($value as $val) {
                $res .= $this->escapeOne($val).',';
            }
            $res = substr($res, 0, -1);
            return $res;
        } else {
            return $this->escapeOne($value);
        }
    }

    /**
     * Escape identifiers
     * @param $value
     * @return string
     */
    protected function escapeSqlName($value) {
        if (is_array($value)) {
            $res = '';
            foreach($value as $val) $res .= $this->escapeSqlName($val).',';
            return substr($res, 0, -1);
        }
        $chk_chars = array('.', '(', ' ');
        foreach($chk_chars as $chr) {
            if (strpos($value, $chr) !== false) {
                return $value;
            }
        }
        return '`'.$value.'`';
    }

    public function isValidSQLIdentifier($identifier) {
        if (is_string($identifier) && strpos($identifier, ' ') === false) return true;
    }

    /**
     * By default, throw exception. You need to clear connection to DB in destructor of adapter
     */
    public function __destruct() {
        throw new \Exception('Connection need to be cleared in desctructor of slDBAdapter!');
    }
}