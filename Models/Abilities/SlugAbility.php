<?php
/*
 * This file is a part of Solve framework.
 *
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 * @copyright 2009-2014, Alexandr Viniychuk
 * created: 07.10.14 13:43
 */

namespace Solve\Database\Models\Abilities;

use Solve\Database\Models\Model;
use Solve\Database\Models\ModelOperator;
use Solve\Utils\Inflector;

/**
 * Class SlugAbility
 * @package Solve\Database\Models\Abilities
 *
 * Class SlugAbility is a model ability for creation url-like field
 *
 * @publish string getSlug()
 *
 * @version 1.0
 * @author Alexandr Viniychuk <alexandr.viniychuk@icloud.com>
 */
class SlugAbility extends BaseModelAbility {

    private $_sourceField = 'title';
    private $_valueField  = '_slug';

    public function setup() {
        if (!$this->_modelStructure->hasColumn($this->_valueField)) {
            $this->_modelStructure->addColumn($this->_valueField, array('type' => 'varchar(255)'))->saveStructure();
            ModelOperator::getInstance()->updateDBForModel($this->_model->_getName());
        }
    }

    public function initialize() {
        $this->_sourceField = $this->_modelStructure->getAbilityInfo('slug/source_field');
        if (empty($field)) {
            if ($this->_modelStructure->hasColumn('title')) {
                $this->_sourceField = 'title';
            } elseif ($this->_modelStructure->hasColumn('name')) {
                $this->_sourceField = 'name';
            } else {
                throw new \Exception('Can\'t detect slug field');
            }
        }
        $this->publishMethod('getSlug');
    }

    public function getSlug($caller) {
        $this->requireModeSingle($caller);
        return $caller[$this->_valueField];
    }

    /**
     * @param Model $caller
     */
    public function preSave($caller) {
        if (array_key_exists($this->_sourceField, $caller->getChangedData())) {
            $caller->{$this->_valueField} = Inflector::slugify($caller->getChangedData($this->_sourceField));
        }
    }

} 