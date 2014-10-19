<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 05.10.14 10:32
 */

namespace Solve\Database\Tests;

require_once 'SolveDatabaseTestBasic.php';
require_once __DIR__ . '/CustomNameValidationRule.php';
use Solve\Database\Models\ModelOperator;
use Solve\Database\Models\ModelRelation;
use Solve\Database\Models\ModelStructure;
use Solve\Database\QC;

class ModelValidatorTest extends SolveDatabaseTestBasic {

    public function testBasic() {
        $user = new \User(array('name' => '  Alex', 'email' => 'invalid-email'));
        $this->assertFalse($user->save(), 'Save result is false for invalid email');

        $this->assertArrayHasKey('email', $user->getErrors(), 'Has an email error');

        $this->assertArrayHasKey('name', $user->getErrors(), 'Has error on name');
        $this->assertEquals(array('invalid name'), $user->getErrors('name'), 'Custom error description works');

        $user->name = 'Alexandr Viniychuk   ';
        $user->email = 'a@viniychuk.com';
        $this->assertTrue($user->save(), 'Saving correct values');
        $this->assertEquals('Alexandr Viniychuk', $user->name, 'Trim process rule worked');
    }


    protected static function putTestContent() {
        QC::executeSQL('SET FOREIGN_KEY_CHECKS = 0');
        QC::executeSQL('DROP TABLE IF EXISTS brands');
        $storagePath = __DIR__ . '/storage/';

        $mo = ModelOperator::getInstance($storagePath);
        $mo->generateBasicStructure('User');
        ModelStructure::getInstanceForModel('User')
            ->dropColumn('title')
            ->addColumn('name', array('type' => 'varchar(255)',
                                      'validation' =>
                                          array(
                                              'CustomName' => array('class' => 'Solve\Database\Tests\CustomNameValidationRule', 'error'=>'invalid name'
                                              )
                                          ),
                                      'process' => array('trim')
            ))
            ->addColumn('email', array('type' => 'varchar(255)'))
            ->saveStructure();
        $mo->generateDataProcessorRules('User');
        $mo->generateAllModelClasses();
        require_once $storagePath . 'bases/BaseUser.php';
        require_once $storagePath . 'classes/User.php';
        $mo->updateDBForAllModels();
    }

//    public static function tearDownAfterClass() {}

}
 