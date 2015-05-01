<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 27.06.14 02:38
 */

namespace Solve\Database\Tests;

require_once 'test_autoloader.php';
use Solve\Database\Models\Model;
use Solve\Database\Models\ModelOperator;
use Solve\Database\Models\ModelStructure;
use Solve\Database\QC;

class DataTypeArrayTest extends SolveDatabaseTestBasic {

    public function testCreation() {
        $product = new \Product();
        $product->info = array('test');
        $this->assertEquals(array('test'), $product->getInfo(), 'Test before saving');
        $product->save();
        $this->assertEquals(array('test'), $product->getInfo(), 'Test after saving');
        $product = \Product::loadOne(2);
        $this->assertEquals(array('test'), $product->getInfo(), 'Test after loading');

        $this->assertTrue(is_array($product->info));
    }

    protected static function putTestContent() {
        QC::executeSQL('DROP TABLE IF EXISTS products');
        $storagePath = __DIR__ . '/storage/';

        $mo = ModelOperator::getInstance($storagePath);
        $mo->generateBasicStructure('Product');
        $ms = new ModelStructure('Product');
        $ms->addColumn('info', array('type' => 'array'));
        $ms->saveStructure();

        $mo->generateAllModelClasses();
        $mo->updateDBForAllModels();
        require_once $storagePath . 'bases/BaseProduct.php';
        require_once $storagePath . 'classes/Product.php';
        QC::create('products')->insert(array('title' => 'Macbook air'))->execute();
    }

    public static function tearDownAfterClass() {}

}
 