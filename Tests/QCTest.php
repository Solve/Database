<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 13.04.14 17:10
 */

namespace Solve\Database;
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../QC.php";
require_once __DIR__ . "/../Models/DBOperator.php";

class QCTest extends \PHPUnit_Framework_TestCase {

    public function setUp() {
        DatabaseService::configProfile(array(
            'user'  => 'root',
            'pass'  => 'root'
        ));
    }

    public function testCreation() {
        $c = QC::create();
        $this->assertTrue($c instanceof QC);
    }

    public function testQCStructure() {
        $qc = QC::create();
        $this->assertTrue($qc->isEmpty(), 'Newly created QC is empty');

        $qc = QC::create('users u');
        $qc->where('age', 12);
        $this->assertEquals(1, count($qc->getCriteria()), 'Added one criteria');

        $qc->or('age', 13);
        $this->assertEquals(2, count($qc->getCriteria()), 'Added two criteria');

        $sql = $qc->select('id, name')->getSQL();
        $this->assertEquals('SELECT id, name FROM users u WHERE ((`age` = 12) OR (`age` = 13))', $sql, 'Select generator #1');

        $qc->from(array('categories', 'products'));
        $qc->leftJoin('users_details', 'u.id = user_details.id');
        $qc->and('is_active > :d OR is_active = :s', 1, 2)->and(QC::create()->and('type = 1')->or('type = 2'));
        $sql = $qc->getSQL();
        $this->assertEquals('SELECT id, name FROM users u,`categories`,`products` '
            . 'LEFT JOIN users_details ON (u.id = user_details.id) '
            . 'WHERE ((`age` = 12) OR (`age` = 13) AND (is_active > 1 OR is_active = \'2\') AND ((type = 1) OR (type = 2)))',
            $sql, 'Left join select generator');
    }

    public function testInsert() {
        $qc = QC::create('users');
        $sql = $qc->insert(array(
            'age'   => '13',
            'name'  => 'Alexandr'
        ))->getSQL();
        $this->assertEquals('INSERT INTO `users` (`age`, `name`) VALUES (\'13\', \'Alexandr\')',
            $sql, 'Insert one row with string age');

        $qc = QC::create('users');
        $sql = $qc->insert(array(
            array(
                'age'   => 13,
                'name'  => 'Alexandr'
            ),
            array(
                'age'   => 14,
                'name'  => 'Sergey'
            ),

        ))->getSQL();
        $this->assertEquals('INSERT INTO `users` (`age`, `name`) VALUES (13, \'Alexandr\'), (14, \'Sergey\')',
            $sql, 'Insert two rows with age as integers');
    }

    public function testReplace() {
        $qc = QC::create('users');
        $sql = $qc->replace(array(
            'age'  => '13',
            'name' => 'Alexandr'
        ))->getSQL();
        $this->assertEquals('REPLACE INTO `users` (`age`, `name`) VALUES (\'13\', \'Alexandr\')',
            $sql, 'Replace one row with string age');

    }

    public function testDelete() {
        $sql = QC::create('codes')->delete('id = 502')->getSQL();
        $this->assertEquals('DELETE FROM `codes` WHERE ((id = 502))', $sql, 'Delete generator');

        $sql = QC::create('codes')->delete('id = :s', 12)->getSQL();
        $this->assertEquals('DELETE FROM `codes` WHERE ((id = \'12\'))', $sql, 'Delete with params generator id as string');
    }

    public function testUpdate() {
        $qc = QC::create('users');
        $sql = $qc->update(array('name'=>'test name'))->where('id = 1')->getSQL();
        $this->assertEquals('UPDATE `users` SET `name` = \'test name\' WHERE ((id = 1))',
            $sql, 'Update generator');

        $qc = QC::create('users');
        $sql = $qc->update('name = :s', 'test name')->where('id = :d', 1)->getSQL();
        $this->assertEquals('UPDATE `users` SET name = \'test name\' WHERE ((id = 1))',
            $sql, 'Update generator with params');
    }

    public function testComplexConditions() {
        $qc = new QC('users');
        $qc->and('age < :d', 10)->use('name')->indexBy('id')->orderBy('id DESC')->limit(1, 2)->groupBy('age');
        $this->assertEquals('SELECT * FROM `users` WHERE ((age < 10)) GROUP BY `age` ORDER BY id DESC LIMIT 1, 2',
            $qc->getSQL(), '');

        $sql = QC::create('users')->where('age > :d', 12)->update('title = :s and id = :d', 'new title', 1)->getSQL();
        $this->assertEquals('UPDATE `users` SET title = \'new title\' and id = 1 WHERE ((age > 12))',
            $sql, 'multiple update with parameters');
    }
}
 