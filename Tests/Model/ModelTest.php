<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 27.06.14 02:38
 */


namespace Solve\Database\Tests\Model;

require_once __DIR__ . '/../../Models/Model.php';
require_once __DIR__ . '/../../DatabaseService.php';
use Solve\Database\DatabaseService;
use Solve\Database\Models\Model;

class ModelTest extends \PHPUnit_Framework_TestCase {

    public function setUp() {
        DatabaseService::configProfile(array(
            'name'  => 'solve_database_test',
            'user'  => 'root',
            'pass'  => 'root'
        ));
    }

    public function testModel() {
        $m = new Model();
        $m->_setName('User');
        $this->assertEquals('User', $m->_getName(), 'Internal change name ok');


    }

}
 