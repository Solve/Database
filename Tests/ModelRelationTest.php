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

class ModelRelationTest extends SolveDatabaseTestBasic {

    public function testForTest(){
    }

    public function testSimpleRelation() {
        $products  = \Product::loadList();
        $products->loadRelative('brand');
        $this->assertEquals('2', $products[2]->brand->id, 'Loaded Brand for array (many_to_one)');

        $product = \Product::loadOne(3);
        $this->assertEquals('Samsung', $product->brand->title, 'Loaded Brand for one object (many_to_one)');

        $brands = \Brand::loadList();
        $this->assertEquals(2, $brands[0]->products->count(), 'Loaded 2 Products for Brand 1 (one to many)');

        $brand = \Brand::loadOne(2);
        $this->assertEquals(1, count($brand->products), 'loaded collection with 1 Product (one to many)');

        $product = \Product::loadOne(1);
        $this->assertEquals(2, $product->categories->count(), 'loaded two Categories for Product 1 (many_to_many)');

        $category = \Category::loadOne(1);
        $this->assertEquals('Macbook Air', $category->products->getFirst()->title, 'Product loaded for Category (many_to_many)');

        $products->setRelatedIDs('brand', 2);
        $this->assertEquals('2', $products[0]->brand->id, 'Set related for many_to_one');

        $brand->setRelatedIDs('products', array(1,2,3));
        $this->assertEquals(3, $brand->products->count(), 'Set related for one_to_many');

        $cat = \Category::loadOne(2);
        $cat->setRelatedIDs('products', array(1,3));
        $this->assertEquals(array(1,3), $cat->products->getIDs(), 'set related ids for many_to_many');

        $cat->clearRelatedIDs('products', array(1));
        $this->assertEmpty(QC::create('products_categories')->where(array('id_product'=>1, 'id_category'=>2))->execute(), 'Clear related IDs for many_to_many');

        $cat->setRelatedProducts(1);
        $this->assertNotEmpty(QC::create('products_categories')->where(array('id_product'=>1, 'id_category'=>2))->execute(), 'Set related IDs with setter');

        $brand = \Brand::loadOne(2);
        $brand->clearRelatedProducts();
        $this->assertEmpty(QC::create('products')->where(array('id_brand'=>2))->execute(), 'Clear related IDs with setter');

        $product->setRelatedBrand($brand);
        $this->assertEquals(2, $product->brand->id, 'Related brand via object');
    }

    protected static function putTestContent() {
        QC::executeSQL('SET FOREIGN_KEY_CHECKS = 0');
        QC::executeSQL('DROP TABLE IF EXISTS products');
        QC::executeSQL('DROP TABLE IF EXISTS brands');
        QC::executeSQL('DROP TABLE IF EXISTS categories');
        QC::executeSQL('DROP TABLE IF EXISTS products_categories');
        $storagePath = __DIR__ . '/storage/';

        $mo = ModelOperator::getInstance($storagePath);
        $mo->generateBasicStructure('Product');
        $mo->generateBasicStructure('Brand');
        $mo->generateBasicStructure('Category');

        ModelStructure::getInstanceForModel('Product')
                      ->addColumn('id_brand', array('type' => 'int(11) unsigned'))
                      ->addRelation('brand')
                      ->addRelation('categories')
                      ->saveStructure();

        ModelStructure::getInstanceForModel('Brand')
                      ->addRelation('products')
                      ->saveStructure();

        ModelStructure::getInstanceForModel('Category')
                      ->addRelation('products')
                      ->saveStructure();

        $mo->generateAllModelClasses();
        require_once $storagePath . 'bases/BaseProduct.php';
        require_once $storagePath . 'bases/BaseBrand.php';
        require_once $storagePath . 'bases/BaseCategory.php';
        require_once $storagePath . 'classes/Product.php';
        require_once $storagePath . 'classes/Brand.php';
        require_once $storagePath . 'classes/Category.php';
        $mo->updateDBForAllModels();
        QC::create('products')->insert(array('title' => 'Macbook Air', 'id_brand' => 1))->execute();
        QC::create('products')->insert(array('title' => 'Macbook Pro', 'id_brand' => 1))->execute();
        QC::create('products')->insert(array('title' => 'iMac 27"', 'id_brand' => 2))->execute();
        QC::create('brands')->insert(array('title' => 'Apple'))->execute();
        QC::create('brands')->insert(array('title' => 'Samsung'))->execute();
        QC::create('categories')->insert(array('title' => 'Notebooks'))->execute();
        QC::create('categories')->insert(array('title' => 'Computers'))->execute();
        QC::create('products_categories')->insert(array('id_product'=>1,'id_category'=>1))->execute();
        QC::create('products_categories')->insert(array('id_product'=>1,'id_category'=>2))->execute();
    }

    public static function tearDownAfterClass() {}


}
 