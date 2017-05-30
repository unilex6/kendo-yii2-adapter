<?php

namespace KendoAdapter;

use yii\base\Exception;
use yii\base\Object;

/**
 * Class KendoFiltersCollection
 * @package KendoAdapter
 */
class KendoFiltersCollection extends Object
{
    const OPERATOR_AND = 'and';

    const OPERATOR_EQUAL = 'eq';
    const OPERATOR_LIKE = 'contains';
    const OPERATOR_NOT_LIKE = 'doesnotcontain';
    const OPERATOR_NOT_EQUAL = 'neq';
    const OPERATOR_STARTS_WITH = 'startswith';
    const OPERATOR_ENDS_WITH = 'endswith';
	const OPERATOR_STRING = 'string';

    public $logic;
    public $filters;

    protected $fieldsList;

    private $logics = [
        'and', 'or'
    ];
    private $operators = [
        self::OPERATOR_EQUAL, self::OPERATOR_LIKE, self::OPERATOR_NOT_EQUAL,
        self::OPERATOR_STARTS_WITH, self::OPERATOR_NOT_LIKE, self::OPERATOR_ENDS_WITH, self::OPERATOR_STRING
    ];

    public function __construct($config)
    {
        if (isset($config['logic']) && in_array($config['logic'], $this->logics)) {
            $this->logic = $config['logic'];
        }

        if (isset($config['filters']) && is_array($config['filters'])) {
            for ($i = 0; $i < count($config['filters']); $i++) {
                if ($config['filters'][$i] instanceof KendoFilter) {
                    $this->filters[] = $config['filters'][$i];

                    continue;
                }

                $operator = $config['filters'][$i]['operator'];
                $field = $config['filters'][$i]['field'];

                if (!in_array($operator, $this->operators)) {
                    continue;
                }

                $this->filters[] = new KendoFilter([
                    'operator' => $operator,
                    'value' => $config['filters'][$i]['value'],
                    'field' => $field,
                ]);
            }
        }
    }

    public function init()
    {
        $method = __FUNCTION__;

        $this->logic = $this->logic ?: self::OPERATOR_AND;

        return parent::$method(func_get_args());
    }

    /**
     * @return array
     */
    public function getFields()
    {
        if (!$this->fieldsList) {
            $fieldsList = [];

            for ($i = 0; $i < count($this->filters); $i++) {
                $fieldsList[] = $this->filters[$i]->field;
            }

            $this->fieldsList = $fieldsList;
        }

        return $this->fieldsList;
    }

    /**
     * @param $name
     * @return null|KendoFilter
     */
    public function getFilter($name)
    {
        for ($i = 0; $i < count($this->filters); $i++) {
            if ($this->filters[$i]->field === $name) {
                $filter = &$this->filters[$i];

                return $filter;
            }
        }

        return null;
    }

    public function deleteFilters($filtersNames)
    {
        if (is_string($filtersNames))
            $filtersNames = [$filtersNames];
        if (!is_array($filtersNames))
            throw new Exception('Wrong argument format');

        for ($i = 0; $i < count($filtersNames); $i++) {
            for ($j = 0; $j < count($this->filters); $j++) {
                if ($filtersNames[$i] === $this->filters[$j]->field) {
                    unset($this->filters[$j]);

                    continue;
                }
            }
        }
    }

    public function getFilters(array $names)
    {
        $filters = [];

        for ($i = 0; $i < count($this->filters); $i++) {
            if (in_array($this->filters[$i]->field, $names))
                $filters[] = &$this->filters[$i];
        }

        return $filters;
    }
}