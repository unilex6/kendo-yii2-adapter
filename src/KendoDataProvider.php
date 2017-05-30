<?php

namespace KendoAdapter;

use yii\data\ActiveDataProvider;
use yii\db\Query;
use Yii;
use yii\data\Pagination;
use yii\base\InvalidParamException;

/**
 * Class KendoDataProvider
 * @package KendoAdapter
 */
class KendoDataProvider extends ActiveDataProvider
{
    const DEFAULT_PAGE_SIZE = 15;

    protected $tableAlias;

    public $filters;
    public $sorting;
    public $pagination;
    public $_pagination;

    public function init()
    {
        parent::init();

        if (!isset($this->pagination['pageSize'])) {
            $pageSize = (int) Yii::$app->request->get('pageSize') ?: self::DEFAULT_PAGE_SIZE;

            $this->pagination['pageSize'] = $pageSize;
        }

        if (empty($this->filters)) {
            $filters = Yii::$app->request->get('filter');

            if ($filters) {
                $filtersCollection = $filters ? new KendoFiltersCollection($filters) : null;

                $this->filters = $filtersCollection;
            }
        }

        if (!$this->sorting) {
            $sort = Yii::$app->request->get('sort');

            if ($sort) {
                $this->sorting = $sort;
            }
        }
    }

    public function getPagination()
    {
        if ($this->_pagination === null) {
            $this->setPagination($this->pagination);
        }

        return $this->_pagination;
    }

    public function setPagination($value)
    {
        if (is_array($value)) {
            $config = [
                'class' => Pagination::className(),
                'pageSizeParam' => 'pageSize'
            ];
            $this->_pagination = Yii::createObject(array_merge($config, $value));
        } elseif ($value instanceof Pagination || $value === false) {
            $this->_pagination = $value;
        } else {
            throw new InvalidParamException('Only Pagination instance, configuration array or false is allowed.');
        }
    }

    protected function prepareModels()
    {
        if ($this->filters instanceof KendoFiltersCollection) {
            $this->query = $this->addFilters($this->query, $this->filters);
        }

        if ($this->sorting) {
            $this->sorting = $this->sorting[0];

            $this->sorting['dir'] = $this->sorting['dir'] === 'asc' ? SORT_ASC : SORT_DESC;

            $this->query = $this->query->orderBy([
                $this->sorting['field'] => $this->sorting['dir']
            ]);
        }

        $method = __FUNCTION__;

        return parent::$method(func_get_args());
    }

    protected function addFilters(Query $query, KendoFiltersCollection $filters)
    {
        $secureFieldsList = $this->getSecureFields($query);
        $tableAlias = $this->getTableAlias($query);

        for ($i = 0; $i < count($filters->filters); $i++) {
            $filter = $filters->filters[$i];

            if (!in_array($filter->field, $secureFieldsList))
                continue;
				
			if($filter->field=="id")
				$filter->operator = KendoFiltersCollection::OPERATOR_EQUAL;
				
            if ($filter->conditions) {
                for ($j = 0; $j < count($filter->conditions); $j++) {
                    $query = $query->andWhere($filter->conditions[$j]);
                }
            } elseif ($filter->operator === KendoFiltersCollection::OPERATOR_EQUAL) {
                $query = $query->andWhere(["{$tableAlias}.{$filter->field}" => $filter->value]);
            } elseif ($filter->operator === KendoFiltersCollection::OPERATOR_LIKE) {
                $query = $query->andWhere(['like', "{$tableAlias}.{$filter->field}", $filter->value]);
            } elseif ($filter->operator === KendoFiltersCollection::OPERATOR_NOT_EQUAL) {
                $query = $query->andWhere(['not', ["{$tableAlias}.{$filter->field}" => $filter->value]]);
            } elseif ($filter->operator === KendoFiltersCollection::OPERATOR_STARTS_WITH) {
                $query = $query->andWhere(['like', "{$tableAlias}.{$filter->field}", "{$filter->value}%", false]);
            } elseif ($filter->operator === KendoFiltersCollection::OPERATOR_NOT_LIKE) {
                $query = $query->andWhere(['not like', "{$tableAlias}.{$filter->field}", $filter->value]);
            } elseif ($filter->operator === KendoFiltersCollection::OPERATOR_ENDS_WITH) {
                $query = $query->andWhere(['like', "{$tableAlias}.{$filter->field}", "%{$filter->value}", false]);
            } elseif ($filter->operator ===  KendoFiltersCollection::OPERATOR_STRING) {
                $query->andWhere($filter->value);
            }
        }

        return $query;
    }

    protected function getSecureFields(Query $queryOriginal)
    {
        $query = clone $queryOriginal;
		$model = new $query->modelClass;;
		
        return $model::getTableSchema()->getColumnNames();
    }

    public function getTableAlias(Query $queryOriginal)
    {
        if (!$this->tableAlias) {
            $query = clone $queryOriginal;
            $query = $query->prepare(Yii::$app->db->queryBuilder);

            $this->tableAlias = $query->from[0];
        }

        return $this->tableAlias;
    }

    public function setTableAlias($alias)
    {
        $this->tableAlias = $alias;
    }

    public function getSortingByField($field)
    {
        for ($i = 0; $i < count($this->sorting); $i++) {
            if ($this->sorting[$i]['field'] === $field)
                return $this->sorting[$i];
        }

        return [];
    }

    public function setSortingByName($field, array $data)
    {
        for ($i = 0; $i < count($this->sorting); $i++) {
            if ($this->sorting[$i]['field'] === $field) {
                return ($this->sorting[$i] = $data);
            }
        }

        return [];
    }
}