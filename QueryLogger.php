<?php
/*
 * This file is a part of trill-api project.
 *
 * @author Alexandr Viniychuk <a@viniychuk.com>
 * created: 1:20 AM 7/17/15
 */

namespace Solve\Database;


use Solve\EventDispatcher\BaseEvent;
use Solve\Kernel\DC;

class QueryLogger
{

    public function onQueryExecuted(BaseEvent $event)
    {
        $params = $event->getParameters();
        DC::getLogger()->add('(' . substr($params['time'], 0, 6) . ') ' . $params['query'], 'db');

    }

    public function getEventListeners() {
        return array(
            'db.query' => array(
                'listener' => array($this, 'onQueryExecuted'),
            ),
        );
    }


}