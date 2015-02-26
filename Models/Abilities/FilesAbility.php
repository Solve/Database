<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 09.10.14 00:14
 */

namespace Solve\Database\Models\Abilities;
use Solve\Database\Models\Model;
use Solve\Database\Models\ModelCollection;
use Solve\Graphics\ImageProcessor;
use Solve\Utils\FSService;
use Solve\Utils\Inflector;


/**
 * Class FilesAbility
 * @package Solve\Database\Models\Abilities
 *
 * Class FilesAbility is a model ability to operate with files and images
 *
 * @publish attachFileFromPath($alias, $filePath)
 * @publish deleteFile($alias)
 * @publish attachFilesFromArray($filesArray)
 * @publish setFieldNameForAlias($alias, $fieldName)
 * @publish skipFileAliasForSave($alias)
 * @publish loadFiles($alias = null)
 *
 * @version 1.0
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 */
class FilesAbility extends BaseModelAbility {

    private static $_baseStoreLocation;

    private $_storePath;
    private $_aliasesFields = array();
    private $_aliasedToSkip = array();

    public function initialize() {
        $this->_storePath = self::$_baseStoreLocation . $this->_modelName . '/';
        $this->publishMethod('loadFiles');
        $this->publishMethod('deleteFile');
        $this->publishMethod('attachFileFromPath');
        $this->publishMethod('attachFilesFromArray');
        $this->publishMethod('setFieldNameForAlias');
        $this->publishMethod('skipFileAliasForSave');
        foreach(array_keys($this->_config) as $alias) {
            $this->publishMethod('get' . Inflector::camelize($alias));
        }
    }

    public function postSave($caller) {
        $this->attachFilesFromArray($caller, $_FILES);
    }

    public function preDelete($caller) {
        FSService::unlinkRecursive($this->getBaseStoreLocation() . $this->_getObjectFolder($caller));
    }

    /**
     * @return mixed
     */
    public static function getBaseStoreLocation() {
        return self::$_baseStoreLocation;
    }

    /**
     * @param mixed $baseStoreLocation
     */
    public static function setBaseStoreLocation($baseStoreLocation) {
        self::$_baseStoreLocation = $baseStoreLocation;
    }


    /**
     * @param Model|ModelCollection $caller
     * @param $alias
     * @param $filePath
     * @param array $params
     * @throws \Exception
     */
    public function attachFileFromPath($caller, $alias, $filePath, $params = array()) {
        $this->requireModeSingle($caller);
        if (!is_file($filePath)) throw new \Exception('File specified is not readable');
        if (!array_key_exists($alias, $this->_config)) throw new \Exception('There is no such alias '.$alias);
        if (!is_array($this->_config[$alias])) $this->_config[$alias] = array();

        $storeLocation = $this->_getAliasFolder($caller, $alias) . '/';
        FSService::makeWritable($storeLocation);
        $sourceFileInfo = FSService::getFileInfo($filePath);

        $newFileExtension = $sourceFileInfo['ext'];
        $newFileName = md5($sourceFileInfo['name'] . time());
        if (!empty($this->_config[$alias]['name'])) {
            if ($this->_config[$alias]['name'] == 'original') {
                $newFileName = $sourceFileInfo['name'];
                while (is_file($storeLocation . $newFileName . $newFileExtension)) {
                    $newFileName .= '_1';
                }
            }
        }
        if (empty($this->_config[$alias]['multiple'])) {
            $filesToDelete = GLOB($storeLocation . '*.*');
            foreach($filesToDelete as $file) {
                unlink($file);
            }
        }
        copy($filePath, $storeLocation . $newFileName . $newFileExtension);
        if (!empty($this->_config[$alias]['sizes'])) {
            $ip = new ImageProcessor($storeLocation . $newFileName . $newFileExtension);
            foreach($this->_config[$alias]['sizes'] as $sizeAlias => $sizeInfo) {
                if (!is_array($sizeInfo)) $sizeInfo = array('size'=>$sizeInfo);
                $method = empty($sizeInfo['method']) ? 'fitOut' : $sizeInfo['method'];
                $params = explode('x', $sizeInfo['size']);
                if (!empty($sizeInfo['geometry'])) {
                    $params[] = $sizeInfo['geometry'];
                }
                call_user_func_array(array($ip, $method), $params);
                $ip->saveAs($storeLocation . $sizeAlias . '/' . $newFileName . $newFileExtension);
            }
        }

        $caller->_setRawFieldValue($alias, $this->getModelValue($storeLocation, $this->_config[$alias]));
    }

    /**
     * @param Model $caller
     * @param $filesArray
     * @throws \Exception
     */
    public function attachFilesFromArray($caller, $filesArray) {
        $internalObjectHash = $caller->getInternalObjectHash();
        foreach($this->_config as $aliasName=>$aliasInfo) {
            if (!empty($this->_aliasedToSkip[$internalObjectHash][$aliasName])) continue;
            $fieldName = $this->getFieldNameForAlias($caller, $aliasName);
            if (isset($filesArray[$fieldName]) && !empty($filesArray[$fieldName]['name'])) {
                $workArray = self::reformatFilesArray($filesArray[$fieldName]);
                foreach($workArray as $item) {
                    $this->attachFileFromPath($caller, $aliasName, $item['tmp_name']);
                }
            }
        }
    }

    /**
     * @param Model|ModelCollection $caller
     * @param $alias
     */
    public function deleteFile($caller, $alias) {
        $this->requireModeSingle($caller);
        $path = $this->_getAliasFolder($caller, $alias);
        FSService::unlinkRecursive($path);
        $caller->_setRawFieldValue($alias, array());
    }

    /**
     * @param Model|ModelCollection $caller
     * @param $alias
     * @return Model|ModelCollection $caller
     */
    public function loadFiles($caller, $alias = null) {
        $aliases = $alias ? explode(',', $alias) : array_keys($this->_config);
        if ($caller instanceof Model) {
            $callers = array($caller);
        } else {
            $callers = $caller;
        }

        foreach($aliases as $alias) {
            foreach($callers as $oneObject) {
                $this->_loadAliasForObject($oneObject, $alias);
            }
        }
        return $caller;
    }


    private function _getAliasFolder($caller, $alias) {
        return self::$_baseStoreLocation . $this->_getObjectFolder($caller) . '/' . $alias;
    }

    public function getModelValue($storagePath, $aliasInfo) {
        $filesToInfo = GLOB($storagePath . '*', GLOB_MARK);
        $value = array();
        foreach($filesToInfo as $file) {
            if (is_dir($file)) continue;
            $fileInfo = FSService::getFileInfo($file);
            if (!empty($aliasInfo['sizes'])) {
                foreach($aliasInfo['sizes'] as $sizeAlias => $sizeInfo) {
                    $fileInfo[$sizeAlias] = FSService::getFileInfo($storagePath  . $sizeAlias . '/' . $fileInfo['full_name']);
                }
            }
            $value[] = $fileInfo;
        }
        return empty($aliasInfo['multiple']) && count($value) ? $value[0] : $value;
    }

    public function __call($method, $params) {
        /**
         * @var Model $caller
         */
        $caller = $params[0];

        if (substr($method, 0, 3) == 'get') {
            $alias = Inflector::underscore(substr($method, 3));
            $this->_loadAliasForObject($caller, $alias);
            return true;
        }
        throw new \Exception('Called undefined method in FilesAbility '.$method);
    }

    /**
     * @param Model $caller
     * @return string
     */
    private function _getObjectFolder($caller) {
        $id = $caller->getID();
        if (!intval($id)) {
            $int = ord($id[0]) . ord($id[1]);
        } else {
            $int = $id > 9 ? $id[0] . $id[1] : '0' . $id;
        }
        return Inflector::pluralize(Inflector::underscore($this->_modelName)) . '/' . $int . '/' . (int)$id % 100;

    }

    static public function reformatFilesArray($filesArray) {
        if (!empty($filesArray['name']) && is_array($filesArray['name'])) {
            $files = array();
            foreach($filesArray['name'] as $key=>$name) {
                $files[] = array(
                    'name'	=> $name,
                    'type'	=> $filesArray['type'][$key],
                    'tmp_name'	=> $filesArray['tmp_name'][$key],
                    'error'	=> $filesArray['error'][$key],
                    'size'	=> $filesArray['size'][$key],
                );
            }
        } else {
            $files = array($filesArray);
        }
        return $files;
    }

    /**
     * @param Model $caller
     * @param $alias
     * @param $fieldName
     */
    public function setFieldNameForAlias($caller, $alias, $fieldName) {
        $hash = $caller->getInternalObjectHash();
        if (empty($this->_aliasesFields[$hash])) $this->_aliasesFields[$hash] = array();
        $this->_aliasesFields[$hash][$alias] = $fieldName;
    }

    /**
     * @param Model $caller
     * @param $alias
     * @return mixed
     */
    public function getFieldNameForAlias($caller, $alias) {
        $fieldName = empty($this->_config[$alias]['field_name']) ? $alias : $this->_config[$alias]['field_name'];
        if (!empty($this->_aliasesFields[$caller->getInternalObjectHash()][$alias])) $fieldName = $this->_aliasesFields[$caller->getInternalObjectHash()][$alias];
        return $fieldName;
    }

    /**
     * @param Model $caller
     * @param $alias
     */
    public function skipFileAliasForSave($caller, $alias) {
        $hash = $caller->getInternalObjectHash();
        if (empty($this->_aliasedToSkip[$hash])) $this->_aliasedToSkip[$hash] = array();
        $this->_aliasedToSkip[$hash][$alias] = true;
    }

    /**
     * @param $caller Model
     * @param $alias
     */
    protected function _loadAliasForObject($caller, $alias) {
        if (array_key_exists($alias, $this->_config)) {
            $caller->_setRawFieldValue($alias, $this->getModelValue($this->_getAliasFolder($caller, $alias) . '/', $this->_config[$alias]));
        }
    }

} 