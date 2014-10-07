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
use Solve\Database\Models\ModelCollection;
use Solve\Database\Models\ModelOperator;
use Solve\Database\Models\ModelStructure;
use Solve\Database\QC;

class ModelCollectionTest extends SolveDatabaseTestBasic {

    public function testCollectionLoader() {

        /**
         * @var ModelCollection $products
         */
        $products = \Product::loadList();
        $this->assertInstanceOf('Solve\Database\Models\ModelCollection', $products, 'Instance of model collection created');

        /**
         * @var ModelCollection $categories
         */
        $categories = \Category::loadList();
        $this->assertInstanceOf('CategoriesCollection', $categories, 'Custom model collection instance loaded');

        $this->assertEquals(3, $products->count(), 'Count with method');
        $this->assertEquals(2, count($categories), 'Count as function');

        $this->assertEquals('Macbook Air', $products[0]->title, 'Object accessor by index');
        $this->assertEquals('Macbook Pro', $products->getOneByPK(2)->title, 'Object accessor by primary key');
        $this->assertEquals('Macbook Air', $products->getFirst()->title, 'Collection getFirst');
        $this->assertEquals('iMac 27"', $products->getLast()->title, 'Collection getLast');

        $products = \Product::loadList(QC::create()->where('id < 3'));
        $this->assertEquals(2, count($products), 'Loaded with criteria');
        
        $product = \Product::loadOne(3);
        $products[] = $product;
        try {
            $products[] = $product;
        } catch (\Exception $e) {
            $this->getExpectedException('Can\'t add object with same id to collection');
        }

        $products->delete();
        $this->assertEmpty(QC::create('products')->where('id < 3')->execute(), 'Model collection delete');
    }


    protected static function putTestContent() {
        QC::executeSQL('DROP TABLE IF EXISTS products');
        QC::executeSQL('DROP TABLE IF EXISTS categories');
        $storagePath = __DIR__ . '/storage/';

        $mo = ModelOperator::getInstance($storagePath);
        $mo->generateBasicStructure('Product');
        $mo->generateBasicStructure('Category');

        ModelStructure::getInstanceForModel('Product')
                      ->addColumn('id_category', array('type' => 'int(11) unsigned'))
                      ->addRelation('category')
                      ->addRelation('category_title', array(
                          'table'       => 'categories',
                          'fields'      => array(
                              'title'
                          ),
                          'use'         => 'title',
                          'type'        => 'many_to_one',
                          'local_field' => 'id_category'
                      ))->saveStructure();

        ModelStructure::getInstanceForModel('Category')
                      ->addRelation('products')
                      ->saveStructure();

        $mo->generateAllModelClasses();
        require_once $storagePath . 'bases/BaseProduct.php';
        require_once $storagePath . 'bases/BaseCategory.php';
        require_once $storagePath . 'classes/Product.php';
        require_once $storagePath . 'classes/Category.php';
        $mo->updateDBForAllModels();
        QC::create('products')->insert(array('title' => 'Macbook Air', 'id_category' => 1))->execute();
        QC::create('products')->insert(array('title' => 'Macbook Pro', 'id_category' => 1))->execute();
        QC::create('products')->insert(array('title' => 'iMac 27"', 'id_category' => 2))->execute();
        QC::create('categories')->insert(array('title' => 'Notebooks'))->execute();
        QC::create('categories')->insert(array('title' => 'Computers'))->execute();

        $categoriesCollectionText = <<<TEXT
<?php
use Solve\Database\Models\ModelCollection;

class CategoriesCollection extends ModelCollection {

    public function getProductsCount() {
        return 12;
    }

}
TEXT;
        file_put_contents($storagePath . 'classes/CategoriesCollection.php', $categoriesCollectionText);
        require_once $storagePath . 'classes/CategoriesCollection.php';
    }

//    public static function tearDownAfterClass() {}
}
 