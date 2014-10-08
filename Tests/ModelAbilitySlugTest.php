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
use Solve\Database\Models\ModelOperator;
use Solve\Database\Models\ModelRelation;
use Solve\Database\Models\ModelStructure;
use Solve\Database\QC;

class ModelAbilitySlugTest extends SolveDatabaseTestBasic {

    public function testBasic() {
        $brand = new \Brand(array('title' => 'Apple'));
        $brand->save();
        $this->assertEquals('apple', $brand->getSlug(), 'slug ability for Apple');

        $brand->setTitle('Саша')->save();
        $this->assertEquals('sasha', $brand->getSlug(), 'slug ability for Sasha');

        $brand->setTitle('')->save();
        $this->assertEquals('n-a', $brand->getSlug(), 'slug ability for empty');

        $brand->setTitle('12 -_  s')->save();
        $this->assertEquals('12-s', $brand->getSlug(), 'slug ability for bad string');
    }


    protected static function putTestContent() {
        QC::executeSQL('SET FOREIGN_KEY_CHECKS = 0');
        QC::executeSQL('DROP TABLE IF EXISTS brands');
        $storagePath = __DIR__ . '/storage/';

        $mo = ModelOperator::getInstance($storagePath);
        $mo->generateBasicStructure('Brand');

        ModelStructure::getInstanceForModel('Brand')
                            ->addAbility('slug')
                            ->saveStructure();

        $mo->generateAllModelClasses();
        require_once $storagePath . 'bases/BaseBrand.php';
        require_once $storagePath . 'classes/Brand.php';
        $mo->updateDBForAllModels();
    }

    public static function tearDownAfterClass() {}

}
 