<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 03.10.14 08:17
 */

namespace Solve\Database\Models;

use Solve\Database\QC;
use Solve\Utils\FSService;
use Solve\Utils\Inflector;
use Symfony\Component\Yaml\Yaml;

/**
 * Class ModelOperator
 * @package Solve\Database\Models
 *
 * Class ModelOperator is used to operate with models files and structures
 *
 * @version 1.0
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 */
class ModelOperator {

    /**
     * @var string path for files folder
     */
    private $_storagePath                   = null;

    /**
     * @var string path for models
     */
    private $_modelsPath                    = null;

    /**
     * @var string path for YML structures
     */
    private $_structuresPath                = null;

    /**
     * @var array all loaded structures
     */
    private $_structure                     = array();

    private $_abilities                     = array();

    private $_isSeparateStorage             = true;

    /**
     * @var array used for check syntax in model structure files
     */
    static private $_allowedStructureKeys   = array(
        'table',
        'columns',
        'indexes',
        'constraints',
        'character_set',
        'collation',
        'relations',
        'abilities',
        'custom',
        'secured',
    );

    /**
     * @var ModelOperator $_instance
     */
    static private $_instance               = null;

    /**
     * Fills local variables with correct values
     * @param string $storagePath to operate with structures and models
     */
    public function __construct($storagePath) {
        $this->setStoragePath($storagePath);
    }

    /**
     * Return current instance
     * @static
     * @param mixed $storagePath to operate with structures and models
     * @return ModelOperator
     * @throws \Exception
     */
    static public function getInstance($storagePath = null) {
        if (!self::$_instance) {
            if (empty($storagePath)) {
                throw new \Exception("Storage path need to be specified on the first instance call for DB Adapter");
            }
            self::$_instance = new ModelOperator($storagePath);
            self::$_instance->loadStructureFiles();
        }
        return self::$_instance;
    }


    public function setStructurePath($path) {
        $this->_structuresPath = $path;
        $this->loadStructureFiles($this->_structuresPath);
    }

    /**
     * @return string
     */
    public function getStructuresPath() {
        return $this->_structuresPath;
    }


    /**
     * Change store path
     * @param $path
     */
    public function setStoragePath($path) {
        FSService::makeWritable($path);
        $this->_storagePath = $path;
        $this->_structuresPath = $this->_storagePath . 'structure/';
        $this->_modelsPath     = $this->_storagePath . 'classes/';
        FSService::makeWritable($this->_structuresPath);
        FSService::makeWritable($this->_modelsPath);
        $this->loadStructureFiles($this->_structuresPath);

    }

    /**
     * Loading structure from YAML files
     * First loading "structure.yml" if it exists
     *
     * @param mixed $path Path where yaml are stored
     * @return void
     */
    public function loadStructureFiles($path = null) {
        if (empty($path)) $path = $this->_structuresPath;

        $files = FSService::getInstance()->in($path)->find('*.yml', FSService::TYPE_FILE,FSService::HYDRATE_NAMES_PATH);
        $data = array();

        if (isset($files['structure.yml'])) {
            $data = Yaml::parse(file_get_contents($files['structure.yml']));
            if (is_null($data)) $data = array();
            unset($files['structure.yml']);
        }
        foreach($files as $file) {
            $model_name = substr($file, strrpos($file, '/')+1, -4);
            $data = array_merge($data, array(ucfirst($model_name) => $data = Yaml::parse(file_get_contents($file))));
        }
        $data = self::fixYamlStructures($data);
        $this->_structure = $data;
    }


    /**
     * Return array with structure for specified model
     *
     * @param string $modelName
     * @return mixed
     */
    public function getYamlStructure($modelName) {
        if (empty($this->_structure)) {
            $this->loadStructureFiles($this->_structuresPath);
        }

        $modelName = ucfirst($modelName);
        if (empty($this->_structure[$modelName])) {
            return array();
        }
        return $this->_structure[$modelName];
    }


    /**
     * Save structure to relative YML file
     *
     * @param string $model_name
     * @param array $structure
     * @return bool
     */
    public function saveYamlStructure($model_name, $structure) {

        $file_name  = strtolower($model_name);
        $model_name = ucfirst($model_name);
        if (is_file($this->_structuresPath. $file_name . '.yml' )) {
            unlink($this->_structuresPath. $file_name . '.yml');
        }

        $full_structure = array();
        if (is_file($this->_structuresPath. 'structure.yml')) {
            $full_structure = Yaml::parse(file_get_contents($this->_structuresPath. 'structure.yml'));
            if (is_null($full_structure)) {
                $full_structure = array();
            }
            foreach($full_structure as $key=>$item) {
                unset($full_structure[$key]);
                $full_structure[ucfirst($key)] = $item;
            }
        }
        $structure = self::fixYamlStructures($structure, true);
        if ($this->_isSeparateStorage) {
            if (isset($full_structure[$model_name])) {
                unset($full_structure[$model_name]);
            }
            if (!empty($full_structure)) {
                file_put_contents($this->_structuresPath. 'structure.yml', Yaml::dump($full_structure, 6, 2));
            } else {
                if (is_file($this->_structuresPath. 'structure.yml')) @unlink($this->_structuresPath. 'structure.yml');
            }
            file_put_contents($this->_structuresPath. $model_name .'.yml', Yaml::dump($structure, 6, 2));

        } else {
            $full_structure[$model_name] = $structure;
            file_put_contents($this->_structuresPath. 'structure.yml', Yaml::dump($full_structure, 6, 2));
        }
        $this->_structure[$model_name] = $structure;
        return true;
    }


    /**
     * Generate file with BaseClass and Child Class if it's not extists
     * @param string|array $classes_names
     * @param bool $rebuild_abilities
     * @param bool $updateStructure
     * @return void
     * @throws \Exception if no structure defined
     */
    public function generateModelClass($classes_names = null, $rebuild_abilities = true, $updateStructure = true) {
        if (is_null($classes_names)) {
            $classes_names = array_keys($this->_structure);
        } elseif (is_string($classes_names)) {
            $classes_names = array($classes_names);
        }

        FSService::makeWritable($this->_storagePath . 'bases/');
        foreach($classes_names as $class_name) {

            $class_name = ucfirst($class_name);
            $base_class_name = 'Base'.$class_name;

            if (empty($this->_structure[$class_name])) $this->loadStructureFiles($this->_structuresPath);
            if (empty($this->_structure[$class_name])) {
                throw new \Exception('There is no structure defined for '.$class_name);
            }
            $base_class_path = $this->_storagePath . 'bases/' . $base_class_name . '.php';
            $template = file_get_contents(dirname(__FILE__). '/_baseTemplate.php');

            $structure_array = '$_structure = ' . Inflector::dumpAsString($this->_structure[$class_name], 1).';';


            $properties = '';
            foreach($this->_structure[$class_name]['columns'] as $key=>$props) {
                $properties .= ' * @property mixed '.$key. "\n";
            }
            if (!empty($this->_structure[$class_name]['relations'])) {
                foreach($this->_structure[$class_name]['relations'] as $key=>$props) {
                    $properties .= ' * @property ' . (isset($props['model']) ? $props['model'] : 'slModel') . ' ' . $key. "\n";
                }
            }
            $methods_text = '';
            $methods = array();
            $abilities = array();
//            if (!empty($this->_structure[$class_name]['abilities'])) {
//                foreach($this->_structure[$class_name]['abilities'] as $key=>$props) {
//                    $ability = Inflector::camelize($key) . 'Ability';
//                    if (!isset($abilities[$ability])) {
//                        $abilityClass = 'Solve\Database\Models\Abilities\\' . $ability;
//                        $abilities[$ability] = new $abilityClass(null);
//                        $ability_methods = $abilities[$ability]->getPublishedActions();
//                        foreach(array_keys($ability_methods) as $method) {
//                            if (!in_array($method, $methods)) $methods[] = $method;
//                        }
//                    }
//                }
//                foreach($methods as $method) {
//                    $methods_text .= ' * @method ' . $method . '() ' . $method. "()\n";
//                }
//            }

            //@todo event dispatcher for project name needed
            $replace = array(
                '__BASENAME__'              => $base_class_name,
                '__NAME__'                  => $class_name,
                '__PROPERTIES__'            => $properties . "\n" . $methods_text,
                '__DATE__'                  => date('d.m.Y H:i:s'),
                '__PROJECT__'               => '',
                '$_structure = array();'    => $structure_array
            );
            $template = str_replace(array_keys($replace), array_values($replace), $template);

            if (is_file($base_class_path)) @unlink($base_class_path);
            if (is_file($base_class_path)) {
                throw new \Exception('Cannot overwrite old Base class for '.$base_class_name);
            }

            file_put_contents($base_class_path, $template);
            chmod($base_class_path, 0777);
            if (!is_file($this->_modelsPath . $class_name . '.php')) {
                $template = file_get_contents(dirname(__FILE__). '/_modelTemplate.php');
                $template = str_replace(array_keys($replace), array_values($replace), $template);
                FSService::makeWritable($this->_modelsPath);
                file_put_contents($this->_modelsPath . $class_name . '.php', $template);
            }
//            slAutoloader::getInstance()->addDir($this->_storagePath . 'bases/');
//            slAutoloader::getInstance()->addDir($this->_modelsPath);

            if ($updateStructure) $this->saveYamlStructure($class_name, $this->_structure[$class_name]);

//            if ($rebuild_abilities && !empty($this->_structure[$class_name]['abilities'])) {
//                /**
//                 *  @var Model $modelInstance
//                 */
//                $modelInstance = new $class_name;
//                foreach($this->_structure[$class_name]['abilities'] as $ability_name=>$params) {
//                    $ability_name = ucfirst($ability_name);
//                    $ability_class = $abilityClass = 'Solve\Database\Models\Abilities\\' . $ability_name.'Ability';
//                    if (class_exists($ability_class)) {
//                        $this->_abilities[$ability_name] = new $ability_class($modelInstance);
//                        $this->_abilities[$ability_name]->setUp();
//                    }
//                }
//                $this->_structure[$class_name] = $modelInstance->getStructure()->get();
//                if ($updateStructure) $this->saveYamlStructure($class_name, $this->_structure[$class_name]);
//            }
        }
    }


    /**
     * Dumps current database data to YML files
     * @param string|array $models
     */
    public function dataDump($models = null) {
        if (!empty($models)) {
            if (!is_array($models)) $models = array($models);
        } else {
            $models = array_keys($this->_structure);
        }

        $data_dir = $this->_storagePath . 'data/';
        FSService::makeWritable($data_dir);
        foreach($models as $model) {
            $model      = ucfirst($model);
            $file_name  = $data_dir . strtolower($model).'.yml';
            if (!isset($this->_structure[$model])) continue;

            if (is_file($file_name)) {
                unlink($file_name);
            }
            $data         = QC::create($this->_structure[$model]['table'])->execute();
            file_put_contents($file_name, Yaml::dump($data, 6, 2));
        }
    }

    /**
     * Load data from YML files into database with replacing
     * @param string|array $models
     */
    public function dataLoad($models = null) {

        if (!empty($models)) {
            if (!is_array($models)) $models = array($models);
        } else {
            $models = array_keys($this->_structure);
        }

        $data_dir = $this->_storagePath . 'data/';
        foreach($models as $model) {
            $model      = ucfirst($model);
            $file_name  = $data_dir . strtolower($model).'.yml';
            if (!isset($this->_structure[$model]) || !is_file($file_name)) continue;
            $data       = Yaml::parse(file_get_contents($file_name));
            if (!is_array($data)) continue;

            QC::executeSQL('SET FOREIGN_KEY_CHECKS=0');
            QC::executeSQL('TRUNCATE `'.$this->_structure[$model]['table'].'`');
            QC::create($this->_structure[$model]['table'])->insert($data)->execute();
        }
    }


    /**
     * @param      $data
     * @param bool $one
     * @return array
     * @throws \Exception
     */
    static public function fixYamlStructures($data, $one = false) {
        if ($one) {
            $data = array('structure'=>$data);
        }
        foreach($data as $model_name=>$structure) {
            if (!isset($structure['columns']) || !empty($structure['manual'])) continue;

            foreach(array_keys($structure) as $key) {
                if (!in_array($key, self::$_allowedStructureKeys)) {
                    throw new \Exception('Unexpected key in model '.$model_name.': '.$key);
                }
            }

            $pk_field = false;
            foreach($structure['columns'] as $field=>$info) {
                if (!is_array($info)) {
                    $info = array('type'=>$info);
                }
                if (isset($info['auto_increment'])) {
                    $pk_field = $field;
                }
                $structure['columns'][$field] = $info;
            }
            if (!isset($structure['table'])) $structure['table'] = strtolower(Inflector::pluralize($model_name));
            if (!isset($structure['indexes']['primary'])) {
                if (!$pk_field) {
                    if (!isset($structure['columns']['id'])) {
                        $structure['columns'] = array_merge(array('id' => array(
                            'type'              => 'int(11) unsigned',
                            'auto_increment'    => true,
                            'not_null'          => true
                        )), $structure['columns']);
                    }
                    $pk_field = 'id';
                }
                $structure['indexes']['primary'] = array('columns'=>array($pk_field), 'type'=>'primary');
            }
            if (isset($structure['relations'])) {
                // @todo auto constraints generation from relations
                foreach($structure['relations'] as $key=>$item) {
                }
            }
            if (empty($structure['constraints'])) {
                unset($structure['constraints']);
            }
            if (!empty($structure['character_set']) && ($structure['character_set'] == 'utf8')) unset($structure['character_set']);
            if (!empty($structure['collation']) && ($structure['collation'] == 'utf8_unicode_ci')) unset($structure['collation']);

            $data[$model_name] = $structure;
        }

        return $one ? $data['structure'] : $data;
    }


    static public function calculateRelationVariables(Model $model, $r, $relation_name) {
        //@todo improve type detection
        $type           = isset($r['type']) ? $r['type'] : 'many_to_one';
        $local_table    = $model->_getStructure()->getTable();
        $related_model  = null;

        $local_key      = isset($r['local_key']) ? $r['local_key'] : $model->_getStructure()->getPrimaryField();
        if (isset($r['model'])) {
            $ms = new ModelStructure($r['model']);
            $related_model = $r['model'];
        } else {
            $ms = null;
        }
        $foreign_table  = isset($r['table']) ? $r['table'] : ($ms ? $ms['table'] : $relation_name);

        $auto_local_field = '';
        if (($type == 'one_to_many') || ($type == 'one_to_one')) {
            $auto_local_field = isset($ms['columns']['id_'.Inflector::underscore($model->_getName())]) ? 'id_'.Inflector::underscore($model->_getName()) : $local_key;
        } elseif($type == 'many_to_one') {
            $auto_local_field = $model->_getStructure()->isColumnExists('id_'.Inflector::underscore($related_model)) ? 'id_'.Inflector::underscore($related_model) : $local_key;
        } elseif($type == 'many_to_many') {
            $auto_local_field = 'id_'.Inflector::underscore($model->_getName());
            $auto_foreign_field = 'id_'.Inflector::underscore($ms ? $ms->getModelName() : Inflector::singularize($relation_name));
        }

        $local_field    = isset($r['local_field']) ? $r['local_field'] : $auto_local_field;

        $foreign_field  = isset($r['foreign_field']) ? $r['foreign_field'] : (!empty($auto_foreign_field) ? $auto_foreign_field : $local_field);
        $foreign_key    = isset($r['foreign_key']) ? $r['foreign_key'] : ($ms ? $ms->getPrimaryKey() : $foreign_field);


        $alias          = isset($r['alias']) ? $r['alias'] : $relation_name;
        $many_table     = isset($r['many_table']) ? $r['many_table'] : ($local_table > $foreign_table ? $local_table.'_'.$foreign_table : $foreign_table.'_'.$local_table);

        $res = array(
            'local_key'         => $local_key,
            'local_field'       => $local_field,
            'local_table'       => $local_table,
            'foreign_field'     => $foreign_field,
            'foreign_key'       => $foreign_key,
            'foreign_table'     => $foreign_table,
            'alias'             => $alias,
            'type'              => $type,
            'many_table'        => $many_table,
            'related_model'     => $related_model,
        );
        return $res;
    }


} 