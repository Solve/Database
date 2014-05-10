<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 13.04.14 17:10
 */

namespace Solve\Database;

require_once __DIR__ . "/../QC.php";

class QCTest extends \PHPUnit_Framework_TestCase {

    public function setUp() {
        DatabaseService::configProfile(array(
            'name'  => 'test',
            'user'  => 'root',
            'pass'  => 'root'
        ));
    }

    public function testCreation() {
        $c = QC::create();
        $this->assertTrue($c instanceof QC);
    }

    public function testDelete() {
//        $delete = QC::create('codes')->insert(array('code'=>'1233', 'id'=>'502'));
//        $delete = QC::create('codes')->delete('id = 502');
//        $delete = QC::create('codes')->where('id = :d', 502)->indexBy('id')->select();
//        var_dump($delete);die;
    }

    public function testUpdate() {
//        $qc = QC::create('codes');
//        $updateResult = $qc->update(array('code'=>'555'), false)->where('id = 501')->run();
//        $updateResult = $qc->update(array('code'=>'555'), false)->where('id = 501')->run();
//        $updateResult = $qc->update(array('code'=>'555'), false)->where('id = 501')->run();
//        $updateResult = $qc->data(array('code'=>'556'))->where('id = 501')->update();
//        var_dump($updateResult);die;
//        $updateResult = $qc->updateAll('');
    }

    public function testInsert() {
//        $qc = QC::create('codes');
//        $insertResult = $qc->insert(array(
//            'code'  => '123'
//        ));
//        var_dump($insertResult);die;
    }

    public function testSelect() {
//        $qc = QC::create();
//        $this->assertTrue($qc->isEmpty(), 'Newly created QC is empty');

//        $qc->and('age', 12);
//        $this->assertEquals(1, count($qc->getCriteria()), 'Added one criteria');

//        $qc->or('age', 13);
//        $this->assertEquals(2, count($qc->getCriteria()), 'Added two criteria');

//        $qc->select('id, name', false);

//        $qc->from('users u');
//        $qc->from(array('categories', 'products'));
//        $qc->leftJoin('users_details', 'u.id = user_details.id');
//        $qc->and('is_active > :d OR is_active = :s', 1, 2)->and(QC::create()->and('type = 1')->or('type = 2'));
//        var_dump($qc->getSQL());die;

//        $q = new QC('codes');
//        $q->and('id < :d', 10)->use('code')->indexBy('id')->foldBy('group');
//        $q->and('id < :d', 10)->use('code')->indexBy('id')->orderBy('id DESC')->limit(1, 2)->groupBy('group');
//        var_dump($q->select());die;

//        QC::create('users')->where('age > :d', 12)->data(array('title' => 'new title'))->update();
//        QC::create('users')->where('age > :d', 12)->data(array(array(), array()))->insert();
//        QC::create('users')->where('age > :d', 12)->data(array('title' => 'new title', 'id' => 1))->replace();
//
//        QC::create('users')->where('age > :d', 12)->update(array('title' => 'new title'));
//        QC::create('users')->where('age > :d', 12)->insert(array(array(), array()));
//        QC::create('users')->where('age > :d', 12)->replace(array('title' => 'new title', 'id' => 1));
//        QC::create('users')->where('age > :d', 12)->replace('title = :s and id = :d', 'new title', 1);

//        $qc->and('age', 12);
//        var_dump($qc->getParams());die;
//        $c = C::create()->andWhere('_created_at <= :date');
//        $c = C::create()->andWhere('_created_at <= :date');
//        $c = C::create()->andWhere('_created_at <= "2014-03-14"');
//        $c = C::create()->andWhere('_created_at <= "2014-03-14"');

//        $c = C::create()->andWhere(array('age' => '12'));
//        $c = C::create()->andWhere(array('c' => array('12','<=')));
//        $c = C::create()->andWhere(array('c' => array('12', '13', '14')));

//        $c = C::create()->andWhere('age = :d and name like "%:s"', array(12,'huy',15));

//        $c = C::create()->andWhere('age <= :d', 12);

//        $c = C::create('age', 14)->and('age', 12)->or(C::create('age', 13)->and('age', 14));
//        $c = C::create()->or('age', 13);

//        $c = C::create()->andWhereE('age', array(12, 13, 15));
//        $c = C::create()->andWhereEL('age', 12);
//        $c = C::create()->andWhereEG('age', 12);
//        $c = C::create()->andWhereLike('age', 12);

//        $c = C::create()->andWhere('age = 12');
//        $c->setParams(array('date'=>"2014-03-14"));
//        '_created_at <= "2014-03-14"';
    }
}
 