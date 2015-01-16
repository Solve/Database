<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 09.10.14 00:18
 */

namespace Solve\Database\Tests;

require_once 'SolveDatabaseTestBasic.php';
use Solve\Database\Models\Abilities\FilesAbility;
use Solve\Database\Models\ModelOperator;
use Solve\Database\Models\ModelStructure;
use Solve\Database\QC;
use Solve\Utils\FSService;

class ModelAbilityFilesTest extends SolveDatabaseTestBasic {

    public function testFirst() {
        $brand = new \Brand(array('title' => 'Apple'));
        $brand->save();
        $brand = \Brand::loadOne(1);
        $brand->attachFileFromPath('logo', __DIR__ . '/../README.md');

        $brand = \Brand::loadOne(1)->loadFiles('logo');
        var_dump($brand->logo);die();

    }

    public function testBasic() {
        $brand = new \Brand(array('title' => 'Apple'));
        $brand->save();
        $brand = \Brand::loadOne(1);
        $brand->attachFileFromPath('logo', __DIR__ . '/../README.md');
        $this->assertEquals('01/1/logo/README.md', $brand->logo[0]['link'], 'original file name');
        $brand->attachFileFromPath('logo', __DIR__ . '/../README.md');
        $this->assertEquals('01/1/logo/README_1.md', $brand->logo[1]['link'], 'duplicate of original file name');
        $brand = \Brand::loadOne(1);
        $this->assertEquals(2, count($brand->logo), 'loaded two files on demand');
        $brand->delete();
        $this->assertEmpty(FSService::getInstance()->in(__DIR__ . '/upload/01/')->find(), 'No files left after deleting');

        $brand = new \Brand(array('title'=>'Apple'));
        $_FILES['logo'] = array(
            'tmp_name'  => __DIR__ . '/../README.md',
            'name'   => 'readme.md'
        );
        $_FILES['info_file'] = array(
            'tmp_name'  => __DIR__ . '/../README.md',
            'name'   => 'readme.md'
        );
        $brand->setFieldNameForAlias('info', 'info_file');
        $brand->save();
        $this->assertEquals(2, count(FSService::getInstance()->in(__DIR__ . '/upload/02/2')->find()), 'Two files saved to object');
        $brand->delete();


        $brand = new \Brand(array('title'=>'Apple'));
        $_FILES['logo'] = array(
            'tmp_name'  => __DIR__ . '/../README.md',
            'name'   => 'readme.md'
        );
        $_FILES['info'] = array(
            'tmp_name'  => __DIR__ . '/../README.md',
            'name'   => 'readme.md'
        );
        $brand->skipFileAliasForSave('logo');
        $brand->save();
        $this->assertEquals(1, count(FSService::getInstance()->in(__DIR__ . '/upload/03/3')->find()), 'Skipped logo');
        $brand->delete();
    }

    public function testImages() {
        $brand = new \Brand(array('title' => 'test'));
        $brand->save();
        $brand->attachFileFromPath('avatar', __DIR__ . '/assets/flower.jpg');
        $this->assertEquals('04/4/avatar/small/' . $brand->avatar['full_name'], $brand->avatar['small']['link'], 'Small avatar works');
    }


    protected static function putTestContent() {
        QC::executeSQL('SET FOREIGN_KEY_CHECKS = 0');
        QC::executeSQL('DROP TABLE IF EXISTS brands');
        $storagePath = __DIR__ . '/storage/';

        $mo = ModelOperator::getInstance($storagePath);
        $mo->generateBasicStructure('Brand');

        ModelStructure::getInstanceForModel('Brand')
                      ->addAbility('files', array(
                          'logo' => array(
                              'name'     => 'original',
                              'multiple' => true
                          ),
                          'info' => array(
                          ),
                          'avatar'  => array(
                              'sizes'   => array(
                                  'small'  =>
                                      array(
                                          'size' => '100x100',
                                          'method' => 'fitOut'
                                      )
                              )
                          )
                      ))
                      ->saveStructure();

        $mo->generateAllModelClasses();
        require_once $storagePath . 'bases/BaseBrand.php';
        require_once $storagePath . 'classes/Brand.php';
        $webRoot = __DIR__ . '/upload/';
        FilesAbility::setBaseStoreLocation($webRoot);
        FSService::setWebRoot($webRoot);
        FSService::unlinkRecursive($webRoot);
        $mo->updateDBForAllModels();
    }

    public static function tearDownAfterClass() {
        parent::tearDownAfterClass();
        FSService::unlinkRecursive(__DIR__ . '/upload');
    }

}
 