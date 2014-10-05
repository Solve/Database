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
    private $_storagePath = null;

    /**
     * @var string path for models
     */
    private $_modelsPath = null;

    /**
     * @var string path for YML structures
     */
    private $_structuresPath = null;

    /**
     * @var array all loaded structures
     */
    private $_structures = array();

    private $_abilities = array();

    private $_isSeparateStorage = true;

    /**
     * @var array used for check syntax in model structure files
     */
    static private $_allowedStructureKeys = array(
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
    static private $_instance = null;

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
                throw new \Exception("Storage path need to be specified on the first instance call for ModelOperator");
            }
            self::$_instance = new ModelOperator($storagePath);
            self::$_instance->loadStructureFiles();
        }
        return self::$_instance;
    }

    public static function configureStaticInstance($storagePath) {
        self::$_instance = new ModelOperator($storagePath);
        self::$_instance->loadStructureFiles();
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
     * Change models path
     * @param $path
     */
    public function setModelsPath($path) {
        FSService::makeWritable($path);
        $this->_modelsPath = $path;
    }

    /**
     * Change store path
     * @param $path
     */
    public function setStoragePath($path) {
        FSService::makeWritable($path);
        $this->_storagePath    = $path;
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

        $files = FSService::getInstance()->in($path)->find('*.yml', FSService::TYPE_FILE, FSService::HYDRATE_NAMES_PATH);
        $data  = array();

        if (isset($files['structure.yml'])) {
            $data = Yaml::parse(file_get_contents($files['structure.yml']));
            if (is_null($data)) $data = array();
            unset($files['structure.yml']);
        }
        foreach ($files as $file) {
            $model_name = substr($file, strrpos($file, '/') + 1, -4);
            $data       = array_merge($data, array(ucfirst($model_name) => $data = Yaml::parse(file_get_contents($file))));
        }
        $data              = self::fixYamlStructures($data);
        $this->_structures = $data;
    }


    /**
     * Return array with structure for specified model
     *
     * @param string $modelName
     * @return mixed
     */
    public function getModelStructure($modelName) {
        if (empty($this->_structures)) {
            $this->loadStructureFiles($this->_structuresPath);
        }
        $modelName = ucfirst($modelName);
        if (empty($this->_structures[$modelName])) {
            return array();
        }
        return $this->_structures[$modelName];
    }

    public function setStructureForModel($modelName, $structure) {
        $modelName                     = ucfirst($modelName);
        $this->_structures[$modelName] = $structure;
    }

    /**
     * @param string $modelName
     * @param bool $setToStructure set the structure to internal storage
     * @return array
     */
    public function generateBasicStructure($modelName, $setToStructure = true) {

        $basicStructure = array(
            'table'   => Inflector::pluralize(Inflector::underscore($modelName)),
            'columns' => array(
                'id'    => array(
                    'type'           => 'int(11) unsigned',
                    'auto_increment' => true
                ),
                'title' => array(
                    'type' => 'varchar(255)'
                ),
            ),
            'indexes' => array(
                'primary' => array('columns' => array('id'))
            )
        );
        if ($setToStructure) {
            $this->setStructureForModel($modelName, $basicStructure);
        }
        return $basicStructure;
    }

    /**
     * Save structure to relative YML file
     *
     * @param string $modelName
     * @return bool
     */
    public function saveModelStructure($modelName) {
        $file_name = strtolower($modelName);
        $modelName = ucfirst($modelName);
        if (is_file($this->_structuresPath . $file_name . '.yml')) {
            unlink($this->_structuresPath . $file_name . '.yml');
        }

        $full_structure = array();
        if (is_file($this->_structuresPath . 'structure.yml')) {
            $full_structure = Yaml::parse(file_get_contents($this->_structuresPath . 'structure.yml'));
            if (is_null($full_structure)) {
                $full_structure = array();
            }
            foreach ($full_structure as $key => $item) {
                unset($full_structure[$key]);
                $full_structure[ucfirst($key)] = $item;
            }
        }
        $structure = self::fixYamlStructures($this->_structures[$modelName], true);
        if ($this->_isSeparateStorage) {
            if (isset($full_structure[$modelName])) {
                unset($full_structure[$modelName]);
            }
            if (!empty($full_structure)) {
                file_put_contents($this->_structuresPath . 'structure.yml', Yaml::dump($full_structure, 6, 2));
            } else {
                if (is_file($this->_structuresPath . 'structure.yml')) @unlink($this->_structuresPath . 'structure.yml');
            }
            file_put_contents($this->_structuresPath . $modelName . '.yml', Yaml::dump($structure, 6, 2));

        } else {
            $full_structure[$modelName] = $structure;
            file_put_contents($this->_structuresPath . 'structure.yml', Yaml::dump($full_structure, 6, 2));
        }
        $this->_structures[$modelName] = $structure;
        return true;
    }


    /**
     * Generate Model file if it's not exist
     * @param string $className
     * @return void
     * @throws \Exception if no structure defined
     */
    public function generateModelClass($className) {

        $className = ucfirst($className);

        if (empty($this->_structures[$className])) $this->loadStructureFiles($this->_structuresPath);
        if (empty($this->_structures[$className])) {
            throw new \Exception('There is no structure defined for ' . $className);
        }

        $baseClassName = 'Base' . $className;
        $baseClassPath = $this->_storagePath . 'bases/' . $baseClassName . '.php';
        FSService::makeWritable($this->_modelsPath);
        FSService::makeWritable($this->_storagePath . 'bases/');

        $propertiesText = '';
        $methodsText    = '';
        foreach ($this->_structures[$className]['columns'] as $key => $props) {
            $propertiesText .= ' * @property mixed ' . $key . "\n";
            $methodsText .= ' * @method mixed get' . Inflector::camelize($key) . '()' . "\n";
            $methodsText .= ' * @method ' . $className . ' set' . Inflector::camelize($key) . '($value)' . "\n";
        }
        if (!empty($this->_structures[$className]['relations'])) {
            foreach ($this->_structures[$className]['relations'] as $key => $props) {
                $propertiesText .= ' * @property ' . (isset($props['model']) ? $props['model'] : 'slModel') . ' ' . $key . "\n";
            }
        }
        if (!empty($propertiesText)) {
            $propertiesText = substr($propertiesText, 0, -1);
        }
//        $methods = array();
//        $abilities = array();
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
//                    $methodsText .= ' * @method ' . $method . '() ' . $method. "()\n";
//                }
//            }

        //@todo event dispatcher for project name needed
        $replace           = array(
            '__BASENAME__'   => $baseClassName,
            '__NAME__'       => $className,
            '__PROPERTIES__' => $propertiesText . (empty($methodsText) ? '' : "\n" . $methodsText),
            '__DATE__'       => date('d.m.Y H:i:s'),
            '__PROJECT__'    => ''
        );
        $baseClassTemplate = file_get_contents(__DIR__ . '/_baseTemplate.php');
        $baseClassTemplate = str_replace(array_keys($replace), array_values($replace), $baseClassTemplate);

        if (is_file($baseClassPath)) unlink($baseClassPath);
        if (is_file($baseClassPath)) {
            throw new \Exception('Cannot overwrite old Base class for ' . $baseClassPath);
        }
        file_put_contents($baseClassPath, $baseClassTemplate);

        chmod($baseClassPath, 0777);
        if (!is_file($this->_modelsPath . $className . '.php')) {
            $modelClassTemplate = file_get_contents(dirname(__FILE__) . '/_modelTemplate.php');
            $modelClassTemplate = str_replace(array_keys($replace), array_values($replace), $modelClassTemplate);
            FSService::makeWritable($this->_modelsPath);
            file_put_contents($this->_modelsPath . $className . '.php', $modelClassTemplate);
        }

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

    public function generateAllModelClasses() {
        $models = array_keys($this->_structures);
        foreach ($models as $model) $this->generateModelClass($model);
    }

    public function updateDBForModel($modelName, $safeUpdate = true) {
        DBOperator::getInstance()->updateDBFromStructure($this->getModelStructure($modelName), $safeUpdate);
        return $this;
    }

    public function updateDBForAllModels($safeUpdate = true) {
        $models = array_keys($this->_structures);
        foreach ($models as $model) $this->updateDBForModel($model, $safeUpdate);
    }

    /**
     * Dumps current database data to YML files
     * @param string|array $models
     */
    public function dataDump($models = null) {
        if (!empty($models)) {
            if (!is_array($models)) $models = array($models);
        } else {
            $models = array_keys($this->_structures);
        }

        $data_dir = $this->_storagePath . 'data/';
        FSService::makeWritable($data_dir);
        foreach ($models as $model) {
            $model     = ucfirst($model);
            $file_name = $data_dir . strtolower($model) . '.yml';
            if (!isset($this->_structures[$model])) continue;

            if (is_file($file_name)) {
                unlink($file_name);
            }
            $data = QC::create($this->_structures[$model]['table'])->execute();
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
            $models = array_keys($this->_structures);
        }

        $data_dir = $this->_storagePath . 'data/';
        foreach ($models as $model) {
            $model     = ucfirst($model);
            $file_name = $data_dir . strtolower($model) . '.yml';
            if (!isset($this->_structures[$model]) || !is_file($file_name)) continue;
            $data = Yaml::parse(file_get_contents($file_name));
            if (!is_array($data)) continue;

            QC::executeSQL('SET FOREIGN_KEY_CHECKS=0');
            QC::executeSQL('TRUNCATE `' . $this->_structures[$model]['table'] . '`');
            QC::create($this->_structures[$model]['table'])->insert($data)->execute();
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
            $data = array('structure' => $data);
        }
        foreach ($data as $model_name => $structure) {
            if (!isset($structure['columns']) || !empty($structure['manual'])) continue;

            foreach (array_keys($structure) as $key) {
                if (!in_array($key, self::$_allowedStructureKeys)) {
                    throw new \Exception('Unexpected key in model ' . $model_name . ': ' . $key);
                }
            }

            $pk_field = false;
            foreach ($structure['columns'] as $field => $info) {
                if (!is_array($info)) {
                    $info = array('type' => $info);
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
                            'type'           => 'int(11) unsigned',
                            'auto_increment' => true,
                            'not_null'       => true
                        )), $structure['columns']);
                    }
                    $pk_field = 'id';
                }
                $structure['indexes']['primary'] = array('columns' => array($pk_field), 'type' => 'primary');
            }
            if (isset($structure['relations'])) {
                // @todo auto constraints generation from relations
                foreach ($structure['relations'] as $key => $item) {
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

    /**
     * @param Model $model
     * @param string $relationName
     * @return array
     * @throws \Exception if not found relation info
     */
    public static function calculateRelationVariables($model, $relationName) {
        $relationInfo     = $model->_getStructure()->getRelationInfo($relationName);
        if (is_null($relationInfo)) throw new \Exception('Relation ' . $relationName . ' is not found');
        if (!is_array($relationInfo)) $relationInfo = array();

        $localTable       = $model->_getStructure()->getTableName();
        $info             = array();

        foreach ($relationInfo as $key => $value) {
            $info[lcfirst(Inflector::camelize($key))] = $value;
        }

        if (!isset($info['localKey'])) $info['localKey'] = $model->_getStructure()->getPrimaryKey();

        if (!isset($info['model']) && !isset($info['table'])) {
            $info['model'] = ucfirst(Inflector::singularize($relationName));
        }

        if (isset($info['model'])) {
            $relatedStructure         = ModelStructure::getInstanceForModel($info['model']);
            $info['relatedModelName'] = $info['model'];
            $info['hydration'] = 'model';
            unset($info['model']);
        } else {
            $info['hydration'] = 'simple';
            if (empty($info['fields'])) {
                $info['fields'] = '*';
            }
            $info['fieldsToRetrieve'] = $info['fields'];
            unset($info['fields']);
            $relatedStructure = null;
        }
        if (!isset($info['foreignKey'])) $info['foreignKey'] = empty($relatedStructure) ? 'id' : $relatedStructure->getPrimaryKey();

        $foreignTable = $relatedStructure ? $relatedStructure->getTableName() : (isset($info['table']) ? $info['table'] : $relationName);

        $autoLocalField   = 'id_' . Inflector::underscore($info['relatedModelName']);
        $autoForeignField = 'id_' . Inflector::underscore($model->_getName());
        $hasLocalField    = false;
        $hasForeignField   = false;

        if ($relatedStructure->hasColumn($autoForeignField)) {
            if (empty($info['type'])) $info['type'] = 'one_to_many';
            if (empty($info['foreignField'])) $info['foreignField'] = $autoForeignField;
            $hasForeignField = true;
        }
        if ($model->_getStructure()->hasColumn($autoLocalField)) {
            if (empty($info['type'])) $info['type'] = 'many_to_one';
            if (empty($info['localField'])) $info['localField'] = $autoLocalField;
            $hasLocalField = true;
        }
        if (!$hasForeignField && !$hasLocalField) {
            if (empty($info['type'])) $info['type'] = 'many_to_many';
        }

        if (empty($info['manyTable'])) $info['manyTable'] = ($localTable > $foreignTable ? $localTable . '_' . $foreignTable : $foreignTable . '_' . $localTable);

        return $info;
    }

    public static function getFieldArray($caller, $fieldName) {
        $fields = array();
        if ($caller instanceof Model) {
            $fields = $caller->$fieldName;
        } else {
            //@todo for #ModelCollection
        }
        return $fields;
    }

    /**
     * @param Model|ModelCollection $caller
     * @return array|null
     */
    public static function getIDs($caller) {
        return self::getFieldArray($caller, $caller->_getStructure()->getPrimaryKey());
    }


} 