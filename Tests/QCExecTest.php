<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 02.10.14 14:03
 */

namespace Solve\Database\Tests;
use Solve\Database\DatabaseService;
use Solve\Database\Models\DBOperator;
use Solve\Database\QC;

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../QC.php";
require_once __DIR__ . "/../Models/DBOperator.php";

class QCExecTest extends \PHPUnit_Framework_TestCase {

    public function setUp() {
        DatabaseService::configProfile(array(
            'user'  => 'root',
            'pass'  => 'root'
        ));
        $storagePath = __DIR__ . '/storage/';
        $DBName = 'solve_test_database';

        DBOperator::getInstance($storagePath)
            ->createDB($DBName)->useDB($DBName);

        QC::executeSQL('DROP TABLE IF EXISTS users');
        DBOperator::getInstance()->createTable('users', array(
                'table'   => 'users',
                'columns' => array(
                    'id'       => array(
                        'type'           => 'int(11) unsigned',
                        'auto_increment' => true,
                    ),
                    'position' => 'varchar(255)',
                    'age'      => 'tinyint(1) unsigned',
                    'name'     => 'varchar(255)',
                ),
                'indexes' => array(
                    'id' => array(
                        'type'    => 'primary',
                        'columns' => 'id'
                    )
                )
            )
        );
        QC::executeSQL('TRUNCATE `users`');
    }

    public function testBasicOperations() {
        $data = QC::create('users')->execute();
        $this->assertEmpty($data, 'Empty select');

        $id = QC::create('users')->insert(array('age' => 10, 'name' => 'Alexandr', 'position' => 'developer'))->execute();
        $this->assertEquals(1, $id, 'Insert returns id 1');

        QC::create('users')->insert(array('age' => 11, 'name' => 'Sergey', 'position' => 'developer'))->execute();
        $data = QC::create('users')->select('id, name')->execute();
        $this->assertEquals(array(
            array(
                'id'    => '1',
                'name'  => 'Alexandr'
            ),
            array(
                'id'    => '2',
                'name'  => 'Sergey'
            ),
        ), $data, 'select fields of two simple rows');

        $data = QC::create('users')->select('name')->where('id = 1')->executeOne();
        $this->assertEquals(array('name' => 'Alexandr'), $data, 'Select one value');

        $data = QC::create('users')->select('id, name, position')
            ->indexBy('id')->foldBy('position')->use('name')
            ->execute();
        $this->assertEquals(array(
                'developer' => array(
                    '1' => 'Alexandr',
                    '2' => 'Sergey'
                )
            ),
            $data, 'Select with foldBy, indexBy and use modifiers');

        $data = QC::create()->rawSelect('select id,name from users')->indexBy('id')->use('name')->execute();
        $this->assertEquals(array(
            1=>'Alexandr',
            2=>'Sergey'
        ), $data, 'Custom select with modifiers');

        $countDeleted = QC::create('users')->delete('id < :d', 3)->execute();
        $this->assertEquals(2, $countDeleted, 'Deleted 2 items');
    }

}
 