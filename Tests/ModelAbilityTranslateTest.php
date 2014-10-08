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
use Solve\Database\Models\Abilities\TranslateAbility;
use Solve\Database\Models\ModelOperator;
use Solve\Database\Models\ModelStructure;
use Solve\Database\QC;

class ModelAbilityTranslateTest extends SolveDatabaseTestBasic {

    public function testForTest() {
        TranslateAbility::setLanguageId(1);
        $brand = new \Brand(array('title' => 'Apple'));
        $this->assertEquals('Apple', $brand->title, 'Accessor after creating');

        $brand->save();
        $this->assertEquals('Apple', $brand->title, 'Accessor after saving');

        $brand = \Brand::loadOne(1);
        $this->assertEquals('Apple', $brand->title, 'Accessor after loading');

        $this->assertEquals(array('id_language'=>1, 'id_object'=>1, 'title'=>'Apple'),
            QC::create('brands_translate')->executeOne(),
            'Data stored in DB after saving');

        TranslateAbility::setLanguageId(2);
        $brand->title = 'Яблоко';

        $brand->save();
        $this->assertEquals('Яблоко', $brand->title, 'Accessor after loading');

        $this->assertEquals(array('id_language'=>2, 'id_object'=>1, 'title'=>'Яблоко'),
            QC::create('brands_translate')->where('id_language = :d', 2)->executeOne(),
            'Data stored in DB after saving');

        $brand->loadTranslation(1);
        $this->assertEquals('Apple', $brand->title, 'loadTranslation works');

        $this->assertEquals(array(
            'id' => 1, 'id_object'=>1, 'id_language' => 2, 'title' => 'Яблоко'
        ), $brand->getTranslationForLanguage(2), 'getTranslationForLanguage');

        TranslateAbility::setLanguageId(1);
        $brand2 = new \Brand();
        $brand2->title = 'Samsung';
        $brand2->save();
        $brand2->loadTranslation(2);
        $this->assertEmpty($brand2->title, 'Empty data for not translated item');

        $brands = \Brand::loadList();
        $this->assertEquals(array('Apple', 'Samsung'), $brands->getFieldArray('title'), 'Loaded two translated items');

        $brands->loadTranslation(2);
        $this->assertEquals(array('Яблоко', null), $brands->getFieldArray('title'), 'Loaded two not fully translated items');

        $this->assertEquals(array(
            1 => array('id'=>1, 'id_object'=>1, 'id_language'=>1, 'title'=>'Apple'),
            2 => array('id'=>1, 'id_object'=>1, 'id_language'=>2, 'title'=>'Яблоко'),
        ), $brand->getAllTranslations(), 'getAllTranslations()');
    }

    protected static function putTestContent() {
        QC::executeSQL('SET FOREIGN_KEY_CHECKS = 0');
        QC::executeSQL('DROP TABLE IF EXISTS products');
        QC::executeSQL('DROP TABLE IF EXISTS brands');
        QC::executeSQL('DROP TABLE IF EXISTS brands_translate');
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
                      ->addAbility('translate', array(
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
        ModelOperator::getAbilityInstanceForModel('Brand', 'Translate')->cleanup();
        $mo->updateDBForAllModels();
    }

    public static function tearDownAfterClass() {
    }


}
 