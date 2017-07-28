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
        for ($i = 0; $i < count($filters->filters); $i++) {
            $filter = $filters->filters[$i];

			if(!$tableAlias = $this->getTableAliasByField($query, $filter->field))
                continue;
				
			if($filter->field=="id")
				$filter->operator = KendoFiltersCollection::OPERATOR_EQUAL;
				
			if($filter->field=="created_at" OR $filter->field=="updated_at")
			{
				$data = preg_split('|\ |',$filter->value);
				$time = strtotime($data[1].' '.$data[2].' '.$data[3]);
				$filter->operator = KendoFiltersCollection::OPERATOR_STRING;
				$filter->value = "{$tableAlias}.{$filter->field} BETWEEN {$time} AND ".($time+(3600*24));
			}
				
            if ($filter->conditions) {
                for ($j = 0; $j < count($filter->conditions); $j++) {
                    $query = $query->andWhere($filter->conditions[$j]);
                }
            } elseif ($filter->operator === KendoFiltersCollection::OPERATOR_EQUAL) {
                $query = $query->andWhere(["{$tableAlias}.{$filter->field}" => $filter->value]);
            } elseif ($filter->operator === KendoFiltersCollection::OPERATOR_LIKE) {
                $query = $query->andWhere(['like', "LOWER({$tableAlias}.{$filter->field})", $filter->value]);
            } elseif ($filter->operator === KendoFiltersCollection::OPERATOR_NOT_EQUAL) {
                $query = $query->andWhere(['not', ["{$tableAlias}.{$filter->field}" => $filter->value]]);
            } elseif ($filter->operator === KendoFiltersCollection::OPERATOR_STARTS_WITH) {
                $query = $query->andWhere(['like', "LOWER({$tableAlias}.{$filter->field})", "{$filter->value}%", false]);
            } elseif ($filter->operator === KendoFiltersCollection::OPERATOR_NOT_LIKE) {
                $query = $query->andWhere(['not like', "LOWER({$tableAlias}.{$filter->field})", $filter->value]);
            } elseif ($filter->operator === KendoFiltersCollection::OPERATOR_ENDS_WITH) {
                $query = $query->andWhere(['like', "LOWER({$tableAlias}.{$filter->field})", "%{$filter->value}", false]);
            } elseif ($filter->operator ===  KendoFiltersCollection::OPERATOR_STRING) {
                $query->andWhere($filter->value);
            }
        }

        return $query;
    }

	protected static function getStructuredFields(Query $queryOriginal)
	{
		$out = [];
		$query = clone $queryOriginal;
		$model = new $query->modelClass;
		
		$query = $query->prepare(Yii::$app->db->queryBuilder);
		
		$table_name = preg_replace('|\"|','',$query->from[0]);
		$out[$table_name] = $model::getTableSchema()->getColumnNames();
		
		if(count($queryOriginal->joinWith[0][0])>0)
		{
			foreach($queryOriginal->joinWith[0][0] as $table_name)
			{
				$class_item = 'common\\models\\'.ucfirst($table_name);
				$out["{{%".$table_name."}}"] = $class_item::getTableSchema()->getColumnNames();
			}
		}
        return $out;
	}
	
	protected function getTableAliasByField(Query $queryOriginal, $field_name)
	{
		$query = clone $queryOriginal;
		
		foreach(self::getStructuredFields($query) as $table_name => $fields)
			if(in_array($field_name, $fields))
				return $table_name;
		
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