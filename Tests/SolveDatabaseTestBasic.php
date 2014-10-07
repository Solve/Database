<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 05.10.14 10:37
 */

namespace Solve\Database\Tests;

use Solve\Database\DatabaseService;
use Solve\Database\Models\DBOperator;
use Solve\Database\QC;
use Solve\Utils\FSService;

require_once 'test_autoloader.php';

class SolveDatabaseTestBasic extends \PHPUnit_Framework_TestCase {

    protected static $_DBName = 'solve_test_database';
    protected static $_storagePath;

    public static function setUpBeforeClass() {
        DatabaseService::configProfile(array(
            'Product' => 'root',
            'pass'    => 'root'
        ));
        self::$_storagePath = __DIR__ . '/storage/';
        DBOperator::getInstance()->createDB(self::$_DBName)->useDB(self::$_DBName);
        FSService::unlinkRecursive(self::$_storagePath);
        QC::executeSQL('SET FOREIGN_KEY_CHECKS = 0');
        call_user_func(array(get_called_class(), 'putTestContent'));
    }


    protected static function putTestContent() {}

    public static function tearDownAfterClass() {
        DBOperator::getInstance()->dropDB(self::$_DBName);
        FSService::unlinkRecursive(self::$_storagePath);
    }


} 