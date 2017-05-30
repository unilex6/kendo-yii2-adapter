<?php

namespace KendoAdapter;

use yii\base\Object;

/**
 * Class KendoFilter
 * @package KendoAdapter
 */
class KendoFilter extends Object
{
    public $operator;
    public $value;
    public $field;
    public $conditions = null;

    public function filterDate()
    {
        $this->value = preg_replace('/\(.*\)/', '', $this->value);
    }
}