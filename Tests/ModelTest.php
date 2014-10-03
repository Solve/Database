<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 27.06.14 02:38
 */


namespace Solve\Database\Tests\Model;

require_once 'test_autoloader.php';
use Solve\Database\DatabaseService;
use Solve\Database\Models\DBOperator;
use Solve\Database\Models\Model;
use Solve\Database\Models\ModelOperator;
use Solve\Database\Models\ModelStructure;
use Solve\Database\QC;
use Solve\Utils\FSService;

class ModelTest extends \PHPUnit_Framework_TestCase {


    public static function setUpBeforeClass() {
        DatabaseService::configProfile(array(
            'Product' => 'root',
            'pass'    => 'root'
        ));
        $DBName = 'solve_test_database';
        DBOperator::getInstance()->createDB($DBName)->useDB($DBName);
        $storagePath = __DIR__ . '/storage/';
        FSService::unlinkRecursive($storagePath);

        self::putTestContent();
    }

    public function testCreatingLoading() {
        $m = new Model();
        $m->_setName('Product');
        $this->assertEquals('Product', $m->_getName(), 'Internal change name ok');

        $object = $m->getModel('Product');
        $this->assertInstanceOf('Product', $object, 'getModel returns model Instance');

        $object = \Product::loadOne(1);
        $this->assertInstanceOf('Product', $object, 'loadOne returns model Instance');

        $data = $object->getArray();
        $this->assertEquals(array('id' => 1, 'title' => 'Macbook air', 'id_category' => null), $data, 'User get array returns actual array');

        $this->assertEquals(1, $object->id, 'Getter of model object is works');

        $object->title = 'Test';
        $this->assertTrue($object->isChanged(), 'isChanged is true after changing');
        $this->assertEquals('Test', $object->title, 'Property changed after setter');
        $this->assertEquals('Test', $object['title'], 'Array accessor works');

        $res = $object->save();
        $this->assertTrue($res, 'Saved without errors');
        $this->assertFalse($object->isNew(), 'Object is not new after saving');
        $this->assertFalse($object->isChanged(), 'Object is not changed after saving');

        $object->setTitle('new title');
        $this->assertEquals('new title', $object->getTitle(), 'setter and getter works fine');
    }

    public function testAccessors() {
        $p = \ProductCustom::loadOne(1);
        $this->assertEquals('solve', $p->manufacturer, 'getter works fine');

        $p->manufacturer = 'Alexandr';
        $this->assertEquals('Alexandr', $p->getManufacturer(), 'setter works fine');
    }

    public function testSaving() {
        $object = \Product::loadOne(1);
        $this->assertEquals('Test', $object->title, 'Object has new title after loading');

        $object = new \Product();
        $this->assertTrue($object->isNew(), 'Newly created object returns isNew true');

        $object->mergeWithData(array(
            'title'       => 'ipad',
            'id_category' => 1,
        ));
        $object->save();
        $this->assertEquals(array(
            'id'          => 2,
            'title'       => 'ipad',
            'id_category' => 1
        ), $object->getArray(), 'Newly created object merged and saved');
    }

    public function testRelations() {
        $product = \Product::loadOne(2);
        var_dump($product->category->title);die();
    }


    private static function putTestContent() {
        QC::executeSQL('DROP TABLE IF EXISTS products');
        QC::executeSQL('DROP TABLE IF EXISTS categories');
        $storagePath = __DIR__ . '/storage/';

        $mo = ModelOperator::getInstance($storagePath);
        $mo->generateBasicStructure('Product');
        $mo->generateBasicStructure('Category');

        $ms = new ModelStructure('Product');
        $ms->addColumn('id_category', array('type' => 'int(11) unsigned'));
        $ms->addRelation('category');
        $ms->saveStructure();

        $mo->generateAllModelClasses();
        $mo->updateDBForAllModels();
        require_once $storagePath . 'bases/BaseProduct.php';
        require_once $storagePath . 'classes/Product.php';
        QC::create('products')->insert(array('title' => 'Macbook air'))->execute();
        QC::create('categories')->insert(array('title' => 'Notebooks'))->execute();

        $testProductFileContent = <<<TEXT
<?php

class ProductCustom extends BaseProduct {
    private \$_internalManufacturer = 'solve';
    public function getManufacturer() { return \$this->_internalManufacturer; }
    public function setManufacturer(\$value) { \$this->_internalManufacturer = \$value; return \$this; }
}
TEXT;

        file_put_contents($storagePath . 'classes/ProductCustom.php', $testProductFileContent);
        require_once $storagePath . 'classes/ProductCustom.php';
        $mo->setStructureForModel('ProductCustom', $mo->generateBasicStructure('Product', false), false);
    }

}
 