<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 08.05.14 01:31
 */
namespace Solve\Database\Exceptions;

/**
 * Class MysqlDBAdapaterException
 * @package ${NAMESPACE}
 *
 * Class MysqlDBAdapaterException is used to ...
 *
 * @version 1.0
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 */
class MysqlDBAdapterException extends \Exception {
    public function __construct($message = "", $code = 0, \Exception $previous = null) {
        $message = 'MysqlDBAdapter - '.$message;
        parent::__construct($message, $code, $previous);
    }


} 