<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 19.10.14 18:56
 */

namespace Solve\Database\Tests;


use Solve\DataTools\DataProcessor;
use Solve\DataTools\Rules\BaseRule;

class CustomNameValidationRule extends BaseRule {
    /**
     * Returned result used for error messaging process
     * true - there is no error
     * false - there is error, but it will be added from params
     * null - error was handled by validation rule
     *
     * @param $fieldName
     * @param $value
     * @param $params
     * @param DataProcessor $dataProcessor
     * @return mixed
     */
    public function process($fieldName, $value, $params, $dataProcessor) {
        if (count(explode(' ', $value)) == 2) {
            return true;
        }
        return false;
    }


} 