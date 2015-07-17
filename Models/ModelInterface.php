<?php
/*
 * This file is a part of Database project.
 *
 * @author Alexandr Viniychuk <a@viniychuk.com>
 * created: 12:15 AM 7/17/15
 */

namespace Solve\Database\Models;


Interface ModelInterface
{
    public static function getModel($modelName);
    public static function loadOne($criteria);
    public static function loadList($criteria = null);
    public function loadRelated($relations);

    public function mergeWithData($data);
    public function save($forceSave = false);
    public function delete();

    public function validate();

    public function isNew();
    public function isEmpty();
    public function isExists();
    public function isChanged();

    public function getChangedData($field = null);

    public function getArray();
    public function getArrayCasted();
}