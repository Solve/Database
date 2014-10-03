<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 11.05.14 13:03
 */

namespace Solve\Database\Models;
use Solve\Database\QC;
use Solve\Utils\FSService;
use Solve\Utils\Inflector;
use Symfony\Component\Yaml\Yaml;


/**
 * Class DBOperator
 * @package Solve\Database\Models
 *
 * Class DBOperator is used to ...
 *
 * @version 1.0
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 */
class DBOperator {

    /**
     * @var array info about tables
     */
    private $_tables                        = null;

    /**
     * @var DBOperator $_instance
     */
    static private $_instance               = null;

    /**
     * Return current instance
     * @static
     * @return DBOperator
     */
    static public function getInstance() {
        if (!self::$_instance) {
            self::$_instance = new DBOperator();
        }
        return self::$_instance;
    }

    /**
     * Change models path
     * @param $path
     */
    public function setModelsPath($path) {
        FSService::makeWritable($path);
        $this->_modelsPath = $path;
    }

    /**
     * Using for creating database
     * @param string $DBName
     * @param string $charset
     * @param string $collation
     * @return $this
     */
    public function createDB($DBName, $charset = 'utf8', $collation = 'utf8_unicode_ci') {
        QC::executeSQL($this->generateDBSQL($DBName, $charset, $collation));
        return $this;
    }

    public function createTable($tableName, $structure) {
        $structure['table'] = $tableName;
        QC::executeSQL($this->generateTableSQL($structure));
        return $this;
    }

    public function useDB($DBName) {
        QC::executeSQL('USE `' . $DBName . '`');
        return $this;
    }

    /**
     * Drop specified Database
     * @param null $DBName
     * @return mixed
     */
    public function dropDB($DBName = null) {
        return QC::executeSQL('DROP DATABASE '.$DBName);
    }

    /**
     * Return list of tables from connected database
     * @param bool $force_fetch reload tables list
     * @return array|null
     */
    public function getDBTables($force_fetch = false) {
        if ($this->_tables && !$force_fetch) return $this->_tables;

        $res = QC::executeSQL('SHOW TABLES');
        $this->_tables = array();
        if ($res->rowCount()) {
            $res = $res->fetchAll(\PDO::FETCH_NUM);
            foreach($res as $info) {
                $this->_tables[$info[0]] = array('name'=>$info[0]);
            }
        }
        return $this->_tables;
    }

    /**
     * Get Full information about table structure and return it as array with keys
     * @param string $table_name
     * @return array structure
     */
    public function getTableStructure($table_name) {

        if (empty($this->_tables[$table_name])) {
            try {
                $res = QC::executeSQL('SHOW CREATE TABLE `'.$table_name.'`');
            } catch (\Exception $e) {
                return null;
            }

        } else {
            return $this->_tables[$table_name];
        }

        $this->_tables[$table_name] = array();
        if ($res->rowCount()) {
            $res = $res->fetchAll(\PDO::FETCH_NUM);
            foreach($res as $row) {

                $this->_tables[$table_name] = array(
                    'table'			=>$table_name,
                    'columns'		=>array(),
                    'indexes'		=>array(),
                    'constraints'	=>array(),
                    'character_set'	=>'utf8',
                    'collation'		=>'utf8_unicode_ci'
                );

                $structure = $row[1];
                $info = explode("\n", $structure);
                array_shift($info);

                while($row = trim(array_shift($info))) {
                    if ($row[strlen($row)-1] == ',') $row = substr($row, 0, -1);

                    if ($row[0] == '`') {
                        $column = array();
                        $pos = strpos($row, '`', 1);
                        $column['name'] = substr($row, 1, $pos-1);
                        $row = trim(substr($row, $pos+1));

                        $pos = strpos($row, ' ');
                        if ($pos === false) $pos = strlen($row);
                        $column['type'] = substr($row, 0, $pos);
                        if (strpos($row, 'unsigned') !== false) {
                            $column['type'] .= ' unsigned';
                        }
                        $row = trim(substr($row, $pos + (isset($column['unsigned']) ? 9 : 0)));

                        $column['not_null'] = false;

                        if (strpos(strtolower($row), 'auto_increment')) {
                            $column['auto_increment'] = true;
                            $column['not_null'] = true;
                        } else {
                            if (($pos = strpos(strtolower($row), 'zerofill')) !== false) {
                                $column['zerofill'] = true;
                                $row = trim(substr($row, $pos + 8));
                            }

                            if (($pos = strpos($row, 'NULL')) !== false) {
                                $null = substr($row, 0, $pos + 4);
                                $row = trim(substr($row, $pos + 4));
                                if (strpos(strtolower($null), 'not') !== false) {
                                    $column['not_null'] = true;
                                }
                            }
                            if (strpos(strtolower($row), 'default') !== false) {
                                $row = trim(substr($row, 8));
                                $row = str_replace(array('"', '\''), '', $row);
                                $pos = strpos($row, ' ');
                                if ($pos === false) $pos = strlen($row);
                                $column['default'] = substr($row, $pos+1);
                                if ($column['default'] === false) $column['default'] = '';
                            }
                        }
                        $this->_tables[$table_name]['columns'][$column['name']] = $column;
                    } else if (($pos = strpos($row, 'KEY')) !== false) {
                        $key_start = strpos($row, '`')+1;

                        if (strpos($row, 'CONSTRAINT') !== false) {
                            $name = substr($row, $key_start, strpos($row, '`', $key_start+1)-$key_start);
                            $con = array('name' => $name);

                            $pos = strpos($row, 'FOREIGN KEY')+14;
                            $con['foreign_key'] = substr($row, $pos, strpos($row, '`', $pos) - $pos);

                            $pos = strpos($row, 'REFERENCES')+12;
                            $con['references'] = substr($row, $pos, strpos($row, '`', $pos) - $pos);

                            $pos = strpos($row, '(', $pos) + 2;
                            $con['local_key'] = substr($row, $pos, strpos($row, '`', $pos) - $pos);

                            if (($pos = strpos($row, 'ON DELETE'))) {
                                $pos += 10;
                                $con['on_delete'] = strtolower(substr($row, $pos, strpos($row, ' ON', $pos) - $pos));
                            } else {
                                $con['on_delete'] = 'restrict';
                            }

                            if (($pos = strpos($row, 'ON UPDATE'))) {
                                $pos += 10;
                                $con['on_update'] = strtolower(substr($row, $pos));
                            } else {
                                $con['on_update'] = 'restrict';
                            }

//							vd('constraint', $row, '!@#');

                            $this->_tables[$table_name]['constraints'][$name] = $con;
                        } elseif (strpos($row, 'PRIMARY') !== false) {
                            $key = substr($row, $key_start, strpos($row, '`', $key_start+1)-$key_start);
                            $this->_tables[$table_name]['indexes']['primary'] = array('columns'=>explode(',', $key), 'type'=>'primary');
                        } else {
                            $name = substr($row, $key_start, strpos($row, '`', $key_start+1)-$key_start);
                            $pos = strpos($row, '(')+1;
                            $key = str_replace('`', '', substr($row, $pos, strpos($row, ')') - $pos));
                            $key = explode(',', $key);
                            $item = array('type' => (strpos($row, 'UNIQUE') !== false) ? 'unique' : 'simple', 'columns'=>$key);
                            $this->_tables[$table_name]['indexes'][$name] = $item;
                        }
                    }
                }
                return $this->_tables[$table_name];
            }
        }
        return array();
    }


    /**
     * Find differences between table structure in DB and $var_info structure
     *
     * @param mixed $varStructure array with structure for diff
     * @param string $tableName specified table if not specified in structure
     * @return array table_exists true|false and sql array for altering
     */
    public function getDifferenceSQL($varStructure, $tableName = null) {
        if (!$tableName) $tableName = $varStructure['table'];

        $current_structure = $this->getTableStructure($tableName);
        $res = array('result'=>false, 'sql'=>array());
        if (!$current_structure) {
            $sql = $this->generateTableSQL($varStructure);
            $res['result'] = true;
            $res['sql']['ADD'][] = $sql;
            return $res;
        }
        if (!isset($varStructure['columns'])) $varStructure['columns'] = array();
        if (!isset($varStructure['table'])) $varStructure['table'] = $tableName;

        // synchronizing columns
        foreach($varStructure['columns'] as $name=>$info) {
            if (!empty($varStructure['abilities']['mlt']['columns']) && in_array($name, $varStructure['abilities']['mlt']['columns'])) continue;
            if (!is_array($info)) $info = array('type'=>$info);
            if (!isset($info['name'])) $info['name'] = $name;

            if (!isset($current_structure['columns'][$name]) && !(isset($info['old_name']) && isset($current_structure['columns'][$info['old_name']]))) {
                if ((isset($info['auto_increment']))) {
                    unset($info['auto_increment']);
                    $res['sql']['ADD'][] = 'ALTER TABLE' . ' ' . $varStructure['table'].' ADD COLUMN '.$this->generateColumnSQL($info);
                    if (!isset($current_structure['indexes']['primary'])) {
                        $varStructure['indexes']['primary'] = array('columns'=>$name);
                        $current_structure['indexes']['primary'] = array('columns'=>$name);
                        $res['sql']['ADD'][] = 'ALTER TABLE' . ' ' . $varStructure['table'].' ADD PRIMARY KEY (`'.$name.'`)';
                    }
                    $info['auto_increment'] = true;
                    $res['sql']['CHANGE'][] = 'ALTER TABLE' . ' ' . $varStructure['table'].' MODIFY COLUMN '.$this->generateColumnSQL($info);
                } else {
                    $res['sql']['ADD'][]  = 'ALTER TABLE' . ' ' . $varStructure['table'].' ADD COLUMN '.$this->generateColumnSQL($info);
                }

            } else {
                if (isset($info['old_name'])) {
                    $current_structure['columns'][$name] = $current_structure['columns'][$info['old_name']];
                    unset($current_structure['columns'][$info['old_name']]);
                }
                $db_info = $current_structure['columns'][$name];
                if (
                    (isset($info['default']) && (strpos($info['default'], '#') === false) && (!isset($db_info['default']) || ($info['default'] != $db_info['default'])))
                    || (!isset($info['default']) && !empty($db_info['default']))
                    || ($info['type'] != $db_info['type'])
                    || isset($info['old_name'])
                    || (isset($info['auto_increment']) && !(isset($db_info['auto_increment'])))
                    || (!isset($info['auto_increment']) && isset($info['not_null']) && ($db_info['not_null'] != $info['not_null']))
                    || (!isset($info['auto_increment']) && !isset($info['not_null']) && $db_info['not_null'])
                ) {
                    if ((isset($info['auto_increment']) && !(isset($db_info['auto_increment'])))) {
                        if (!isset($current_structure['indexes']['primary'])) {
                            $varStructure['indexes']['primary'] = array('columns'=>$name);
                            $current_structure['indexes']['primary'] = array('columns'=>$name);
                            $res['sql']['ADD'][] = 'ALTER TABLE' . ' ' . $varStructure['table'].' ADD PRIMARY KEY (`'.$name.'`)';
                        }
                    }
                    $res['sql']['CHANGE'][] = 'ALTER TABLE' . ' ' . $varStructure['table'].' CHANGE COLUMN '.(isset($info['old_name']) ? '' : '`'.$name.'` ').$this->generateColumnSQL($info);
                }
            }
        }
        // Dropping unused columns from DB
        foreach($current_structure['columns'] as $name=>$info) {
            if (!isset($varStructure['columns'][$name])) {
                $sql_diff = 'ALTER TABLE' . ' ' . $varStructure['table'].' DROP COLUMN `'.$name.'`';
                $res['sql']['DROP'][] = $sql_diff;
            }
        }

        if (isset($varStructure['indexes'])) {
            // synchronizing indexes
            foreach($varStructure['indexes'] as $name=>$info) {
                $sql_diff = 'ALTER TABLE' . ' ' . $varStructure['table'];

                if (!isset($current_structure['indexes'][$name])) {
                    if (!isset($info['name'])) $info['name'] = $name;
                    $sql_diff .= ' ADD '.$this->generateIndexSQL($info);
                    $res['sql']['ADD'][] = $sql_diff;;
                } else {
                    //@todo Altering indexes
                }
            }
        }

        // Dropping unused indexes from DB
        foreach($current_structure['indexes'] as $name=>$info) {
            if (!isset($varStructure['indexes'][$name])) {
                $sql_diff = 'ALTER TABLE' . ' ' . $varStructure['table'].($name == 'primary' ? ' DROP PRIMARY KEY' : ' DROP KEY `'.$name.'`');
                $res['sql']['DROP'][] = $sql_diff;
            }
        }
        if (isset($varStructure['constraints'])) {
            // synchronizing constraints
            foreach($varStructure['constraints'] as $name=>$info) {
                $sql_diff = 'ALTER TABLE' . ' ' . $varStructure['table'];
                if (!isset($current_structure['constraints'][$name])) {
                    $sql_diff .= ' ADD '.$this->generateConstraintSQL($info);
                    $res['sql']['ADD'][] = $sql_diff;;
                } else {
                    //@todo Altering indexes
                }
            }
        }


        if (count($res['sql'])) {
            $res['result'] = true;
        }
        return $res;
    }

    /**
     * Update database from array
     * @param mixed $structure
     * @param bool $safeUpdate if true - method will delete not specified fields
     * @throws \Exception
     */
    public function updateDBFromStructure($structure, $safeUpdate = true) {
        $diffs = $this->getDifferenceSQL($structure);
        if ($diffs['result'] === true) {
            QC::executeSQL('SET FOREIGN_KEY_CHECKS = 0');
            if (!empty($diffs['sql']['ADD'])) QC::executeSQL($diffs['sql']['ADD']);
            if (!empty($diffs['sql']['CHANGE'])) {
                QC::executeSQL($diffs['sql']['CHANGE']);
            }

            if (!empty($diffs['sql']['DROP']) && !$safeUpdate) QC::executeSQL($diffs['sql']['DROP']);
        }
    }
    //@todo update model from db

    /**
     * Updates relations in database
     * @param string $modelName
     * @param mixed $structure
     * @throws \Exception
     */
    public function updateDBRelations($modelName, $structure) {
        if (empty($structure['relations'])) return true;

        foreach($structure['relations'] as $r_name=>$r) {
            $model_object = new $modelName;
            $info = ModelOperator::calculateRelationVariables($model_object, $r, $r_name);
            unset($model_object);
            switch($info['type']) {
                case 'many_to_many':
                    $this->updateManyTable($info);
                    break;
                case 'many_to_one':
                    break;
                case 'one_to_many':
                    break;
            }
        }
    }

    /**
     * Updates many to many tables
     * @param $info
     * @return mixed
     */
    public function updateManyTable($info) {
        $structure = array(
            'table'     => $info['many_table'],
            'columns'   => array(),
            'indexes'   => array(),
            'constraints'   => array(),
        );


        $local_name = Inflector::singularize($info['local_table']);
        $foreign_name = Inflector::singularize($info['foreign_table']);

        $structure['columns']['id'] = array('type' => 'int(11) unsigned', 'auto_increment'=>true);
        $structure['columns']['id_'. $local_name] = 'int(11) unsigned';
        $structure['columns']['id_'. $foreign_name] = 'int(11) unsigned';
        $structure['indexes']['primary'] = array('columns'=>array('id'), 'type'=>'primary');
        $structure['indexes']['unique_id_'.$local_name.'_id_'.$foreign_name] = array(
            'columns'   => array(
                'id_'.$local_name,
                'id_'.$foreign_name
            ),
            'type'      => 'unique'
        );
        $fk_info = array(
            'local_table'   => $info['many_table'],
            'foreign_table' => $info['local_table'],
            'local_field'   => 'id_'.$local_name,
            'foreign_field' => $info['local_key']
        );
        $structure['constraints'][$this->generateForeignKeyName($fk_info)] = $fk_info;
        $fk_info = array(
            'local_table'   => $info['many_table'],
            'foreign_table' => $info['foreign_table'],
            'local_field'   => 'id_'.$foreign_name,
            'foreign_field' => $info['foreign_key']
        );
        $structure['constraints'][$this->generateForeignKeyName($fk_info)] = $fk_info;

        $diffs = $this->getDifferenceSQL($structure, $info['many_table']);
        if ($diffs['result'] === true) {
            QC::executeSQL('SET FOREIGN_KEY_CHECKS = 0');
            if (!empty($diffs['sql']['ADD'])) QC::executeSQL($diffs['sql']['ADD']);
        }
        return $diffs['result'];

    }

    public function generateDBSQL($DBName, $charset = 'utf8', $collation = 'utf8_unicode_ci') {
        return 'CREATE DATABASE IF NOT EXISTS `'.$DBName.'` CHARACTER SET '.$charset.' COLLATE '.$collation;
    }

    /**
     * Generate whole SQL definition for table
     *
     * @param array $structure
     * @return string
     */
    public function generateTableSQL($structure) {
        $sql = 'CREATE TABLE' . ' IF NOT EXISTS `' . $structure['table'].'` (' . "\n";

        foreach($structure['columns'] as $column=>$info) {
            if (!is_array($info)) $info = array('type'=>$info);
            if (!isset($info['name'])) $info['name'] = $column;
            $sql .= $this->generateColumnSQL($info).','."\n";
        }
        if (!empty($structure['indexes'])) {
            foreach($structure['indexes'] as $name=>$info) {
                if (!isset($info['name'])) $info['name'] = $name;
                $sql .= $this->generateIndexSQL($info).','."\n";
            }
        }
        if (!empty($structure['constraints'])) {
            foreach($structure['constraints'] as $info) {
                $info['local_table'] = $structure['table'];
                $sql .= $this->generateConstraintSQL($info).','."\n";
            }
        }
        $sql = substr($sql, 0, -2).')';
        $sql .= ' ENGINE = '.(empty($info['engine']) ? 'INNODB' : $info['engine']).'  CHARACTER SET='.(empty($info['charset']) ? 'utf8' : $info['charset']).' COLLATE='.(empty($info['collate']) ? 'utf8_unicode_ci' : $info['collate']);

        return $sql;
    }

    /**
     * Generate part of SQL definition for column
     *
     * @access private
     * @param array $info
     * @return string SQL
     */
    private function generateColumnSQL($info) {
        $sql = '';
        if (isset($info['old_name'])) {
            $sql = '`'.$info['old_name'].'` ';
        }

        $sql .= '`'.$info['name'].'` '.$info['type'];
        if (isset($info['unsigned'])) $sql .= ' unsigned';
        if (isset($info['zerofill'])) $sql .= ' zerofill';
        if (isset($info['auto_increment'])) {
            $sql .= ' NOT NULL auto_increment';
        } elseif (isset($info['default']) && (strpos($info['default'], '#sql#') === false)) {
            $sql .= ' DEFAULT \''.$info['default']."'";
        } elseif (isset($info['not_null'])) {
            $sql .= ' NOT NULL';
        }
        return $sql;
    }

    /**
     * Generate part of SQL definition for Indexes
     *
     * @access private
     * @param array $info
     * @return string
     */
    private function generateIndexSQL($info) {
        $keys = $info['columns'];
        $sql = '';
        if (!isset($info['type'])) $info['type'] = ($info['name'] == 'primary' ? 'primary' : 'simple');
        switch ($info['type']) {
            case 'primary':
                $sql .= 'PRIMARY KEY (`'.(is_array($keys) ? implode('`, `', $keys) : $keys).'`)';
                break;
            case 'unique':
            case 'simple':
                $sql .= ($info['type'] == 'unique' ? 'UNIQUE ' : '').'KEY '.'`'.$info['name'].'` (`'.(is_array($keys) ? implode('`, `', $keys) : $keys).'`)';
                break;
        }
        return $sql;
    }

    /**
     * Generate part of SQL definition for Constraints
     *
     * @param array $info
     * @return string
     */
    private function generateConstraintSQL($info) {
        $name = isset($info['name']) ? $info['name'] : $this->generateForeignKeyName($info);
        $sql = 'CONSTRAINT `'.$name.'` FOREIGN KEY (`'.$info['local_field'].'`) REFERENCES `'
            .$info['foreign_table'].'` (`'.$info['foreign_field'].'`) '.
            'ON DELETE '.(empty($info['on_delete']) ? 'SET NULL' : $info['on_delete']).' ON UPDATE '.(empty($info['on_update']) ? 'CASCADE' : $info['on_update']);
        return $sql;
    }

    private function generateForeignKeyName($info) {
        return $info['local_table'].'__'.$info['local_field'].'__'.$info['foreign_field'].'_fk';
    }
}