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

class ModelAbilityMLTTest extends SolveDatabaseTestBasic {

    public function testForTest() {
    }

    protected static function putTestContent() {
        QC::executeSQL('SET FOREIGN_KEY_CHECKS = 0');
        QC::executeSQL('DROP TABLE IF EXISTS products');
        QC::executeSQL('DROP TABLE IF EXISTS brands');
        $storagePath = __DIR__ . '/storage/';

        $mo = ModelOperator::getInstance($storagePath);
        $mo->generateBasicStructure('Product');
        $mo->generateBasicStructure('Brand');

        ModelStructure::getInstanceForModel('Product')
                      ->addColumn('id_brand', array('type' => 'int(11) unsigned'))
                      ->addRelation('brand')
                      ->saveStructure();

        ModelStructure::getInstanceForModel('Brand')
                      ->addRelation('products')
                      ->addAbility('mlt', array(
                          'columns' => array(
                              'title'
                          )
                      ))
                      ->saveStructure();

        $mo->generateAllModelClasses();
        require_once $storagePath . 'bases/BaseProduct.php';
        require_once $storagePath . 'bases/BaseBrand.php';
        require_once $storagePath . 'classes/Product.php';
        require_once $storagePath . 'classes/Brand.php';
        $mo->updateDBForAllModels();
    }

    public static function tearDownAfterClass() {
    }


}
 