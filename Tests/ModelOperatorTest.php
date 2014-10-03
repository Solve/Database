<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 03.10.14 12:09
 */

namespace Solve\Database\Tests;
use Solve\Database\DatabaseService;
use Solve\Database\Models\DBOperator;
use Solve\Database\Models\ModelOperator;
use Solve\Database\QC;
use Solve\Utils\FSService;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Models/ModelOperator.php';

class ModelOperatorTest extends \PHPUnit_Framework_TestCase {

    private $_storagePath;

    public function setUp() {
        DatabaseService::configProfile(array(
            'user'  => 'root',
            'pass'  => 'root'
        ));
        $DBName = 'solve_test_database';
        DBOperator::getInstance()->createDB($DBName)->useDB($DBName);

        $this->_storagePath = __DIR__ . '/storage/';
        FSService::unlinkRecursive($this->_storagePath);
    }

    public function testBasic() {
        $mo = ModelOperator::getInstance($this->_storagePath);
        $this->assertEmpty($mo->getModelStructure('User'), 'Empty structure returns array()');

        $mo->generateBasicStructure('User');
        $data = $mo->getModelStructure('User');
        $this->assertEquals(array(
                'table'     => 'users',
                'columns'   => array(
                'id'    => array(
                    'type'  => 'int(11) unsigned',
                    'auto_increment'    => true
                ),
                'title' => array(
                    'type'  => 'varchar(255)'
                ),
            ),
                'indexes'   => array(
                'primary'   => array( 'columns' => 'id' )
            )
        ), $data, 'Basic structure generator is ok');

        $mo->saveModelStructure('User');
        $this->assertFileExists($this->_storagePath . 'structure/User.yml', 'Save model structure is ok');

        $mo->generateModelClass('User');
        require_once __DIR__ . '/storage/bases/BaseUser.php';
        require_once __DIR__ . '/storage/classes/User.php';

        $this->assertFileExists($this->_storagePath . 'bases/BaseUser.php', 'BaseModel generated');
        $this->assertFileExists($this->_storagePath . 'classes/User.php', 'Model generated');

        $this->assertTrue(class_exists('\User'), 'Generated model class is available');

        QC::executeSQL('DROP TABLE IF EXISTS users');
        $mo->updateDBForModel('User');
        $data = DBOperator::getInstance()->getTableStructure('users');
        $this->assertNotEmpty($data, 'Table generation is ok');
    }


    public function tearDown() {
        FSService::unlinkRecursive($this->_storagePath);
    }
}
 