<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 08.05.14 01:37
 */

namespace Solve\Database;
use Solve\Storage\ArrayStorage;

/**
 * Class DatabaseService
 * @package Solve\Database
 *
 * Class DatabaseService is used to manage database connections
 *
 * @version 1.0
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 */
class DatabaseService {

    private static $_adapters           = array();

    private static $_profiles           = array();

    private static $_activeProfileName  = null;

    /**
     * @var ArrayStorage
     */
    private static $_config;

    public static function configProfile($options, $profileName = 'default') {
        if (empty(self::$_activeProfileName)) self::$_activeProfileName = $profileName;
        self::$_profiles[$profileName] = $options;
    }

    public static function getAdapter($adapterName = 'MysqlDBAdapter') {
        if (empty(self::$_adapters[$adapterName])) {
            $adapterClass = '\Solve\Database\Adapters\\' . $adapterName;
            self::$_adapters[$adapterName] = new $adapterClass(self::getActiveProfileConfig());
        }
        return self::$_adapters[$adapterName];
    }

    public static function getActiveProfileConfig() {
        if (empty(self::$_activeProfileName) || empty(self::$_profiles[self::$_activeProfileName])) {
            throw new \Exception('Profile for database is not defined: '. (self::$_activeProfileName ? self::$_activeProfileName : '(empty)'));
        }
        return self::$_profiles[self::$_activeProfileName];
    }

    /**
     * @return null
     */
    public static function getActiveProfileName() {
        return self::$_activeProfileName;
    }

    /**
     * @param null $activeProfileName
     */
    public static function setActiveProfileName($activeProfileName) {
        self::$_activeProfileName = $activeProfileName;
    }

    /**
     * @param $data
     * @changed
     * @param null $deepKey
     */
    public static function setConfig($data, $deepKey = null) {
        if (empty(self::$_config)) {
            self::$_config = new ArrayStorage();
        }
        if ($deepKey) {
            self::$_config->setDeepValue($deepKey, $data);
        } else {
            self::$_config->setData($data);
        }
    }

    public static function getConfig($deepKey = null, $defaultValue = null) {
        if (empty(self::$_config)) {
            return $defaultValue;
        }
        if ($deepKey) {
            return self::$_config->getDeepValue($deepKey, $defaultValue);
        }
    }

} 