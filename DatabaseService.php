<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 08.05.14 01:37
 */

namespace Solve\Database;
use Solve\Database\Adapters\MysqlDBAdapter;
require_once dirname(__FILE__) . '/Adapters/MysqlDBAdapter.php';

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

    public static function configProfile($options, $profileName = 'default') {
        if (empty(self::$_activeProfileName)) self::$_activeProfileName = $profileName;
        self::$_profiles[$profileName] = $options;
    }

    public static function getAdapter($adapterName = 'MysqlDBAdapter') {
        if (empty(self::$_adapters[$adapterName])) {
            $adapterClass = '\Solve\Database\Adapters\\' . $adapterName;
            self::$_adapters[$adapterName] = new $adapterClass(self::$_profiles[self::$_activeProfileName]);
        }
        return self::$_adapters[$adapterName];
    }

} 