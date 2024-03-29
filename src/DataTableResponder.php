<?php
namespace LangleyFoxall\ReactDynamicDataTableLaravelApi;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Class DataTableResponder
 * @package LangleyFoxall\ReactDynamicDataTableLaravelApi
 */
class DataTableResponder
{
    /**
     * @var Model
     */
    private $model;

    /**
     * @var Builder
     */
    private $queryBuilder;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var callable
     */
    private $queryManipulator;

    /**
     * @var array|callable[]
     */
    private $orderByOverrides = [];

    /**
     * @var callable
     */
    private $collectionManipulator;

    /**
     * @var int
     */
    private $perPage = 15;

    /**
     * @var array
     */
    private $meta = [];

    /**
     * DataTableResponder constructor.
     *
     * @param $classNameOrQueryBuilder
     * @param Request $request
     */
    public function __construct($classNameOrQueryBuilder, Request $request)
    {
        if ($classNameOrQueryBuilder instanceof QueryBuilder) {
            $this->model = null;
            $this->queryBuilder = $classNameOrQueryBuilder;
        } else {
            if (!class_exists($classNameOrQueryBuilder)) {
                throw new InvalidArgumentException('Provided class does not exist.');
            }

            $this->model = new $classNameOrQueryBuilder();
            $this->queryBuilder = null;

            if (!$this->model instanceof Model) {
                throw new InvalidArgumentException('Provided class is not an Eloquent model.');
            }
        }

        $this->request = $request;
    }

    /**
     * Sets the number of records to return per page.
     *
     * @param int $perPage
     * @return DataTableResponder
     */
    public function setPerPage($perPage)
    {
        $this->perPage = $perPage;
        return $this;
    }

    /**
     * Sets the callable used to manipulate the model query.
     *
     * @param callable $queryManipulator
     * @return DataTableResponder
     */
    public function query(callable $queryManipulator)
    {
        $this->queryManipulator = $queryManipulator;
        return $this;
    }

    /**
     * Sets the field name to callable mapping array used to override the query order by logic
     *
     * @param array|callable[] $orderByOverride
     * @return DataTableResponder
     */
    public function overrideOrderByLogic(array $orderByOverrides)
    {
        $this->orderByOverrides = $orderByOverrides;
        return $this;
    }

    /**
     * Sets the callable used to manipulate the query results collection
     *
     * @param callable $collectionManipulator
     * @return DataTableResponder
     */
    public function collectionManipulator(callable $collectionManipulator)
    {
        $this->collectionManipulator = $collectionManipulator;
        return $this;
    }

    /**
     * Sets the meta for the API response
     *
     * @see DataTableResponder::makeMeta
     *
     * @param callable $collectionManipulator
     * @return DataTableResponder
     */
    public function setResponseMeta(array $meta = [])
    {
        $this->meta = $meta;
        return $this;
    }

    /**
     * Builds the Eloquent query based on the request.
     *
     * @param Request $request
     * @return Builder
     */
    private function buildQuery(Request $request)
    {
        $query = $this->queryBuilder ?? $this->model->query();

        $queryManipulator = $this->queryManipulator;

        if ($queryManipulator) {
            $queryManipulator($query);
        }

        $orderByField = $request->get('orderByField');
        $orderByDirection = $request->get('orderByDirection');

        if ($orderByField && $orderByDirection) {
            if (!in_array(strtolower($orderByDirection), ['asc', 'desc'])) {
                throw new InvalidArgumentException('Order by direction must be either asc or desc.');
            }

            if (in_array($orderByField, array_keys($this->orderByOverrides))) {
                call_user_func_array(
                    $this->orderByOverrides[$orderByField],
                    [$query, $orderByDirection]
                );
            } else {
                $query->orderBy($orderByField, $orderByDirection);
            }
        }

        return $query;
    }

    /**
     * @param Builder|QueryBuilder $query
     * @return LengthAwarePaginator
     */
    private function paginateQuery($query)
    {
        return $query->paginate($this->perPage);
    }

    /**
     * @param LengthAwarePaginator $results
     * @return LengthAwarePaginator
     */
    private function manipulateCollection($results)
    {
        $collection = $results->getCollection();
        $manipulator = $this->collectionManipulator;

        if ($manipulator) {
            $manipulated = $manipulator($collection);

            if ($manipulated) {
                $results->setCollection($manipulated);
            }
        }

        return $results;
    }

    /**
     * Make response meta
     * 
     * If a callable is given as an element value then
     * the query and collection as parameters
     * 
     * `disallow_ordering_by` will always be overwritten
     * as it is managed internally
     * 
     * @param Builder|QueryBuilder $query
     * @param Collection $collection
     * @return array
     */
    private function makeMeta($query, Collection $collection)
    {
        $meta = $this->meta;
        $out = [];

        foreach($meta as $element => $value) {
            if (is_callable($value)) {
                $out[$element] = call_user_func_array(
                    $value, [$query, $collection]
                );

                continue;
            }

            $out[$element] = $value;
        }

        $out['disallow_ordering_by'] = $this->disallowOrderingBy();

        return $out;
    }

    /**
     * @return array|string[]
     */
    private function disallowOrderingBy()
    {
        $customAttributes = [];

        if ($this->model !== null) {
            $methods = get_class_methods($this->model);
            
            foreach($methods as $method) {
                if (!preg_match('/^get(\w+)Attribute$/', $method, $matches)) {
                    continue;
                }

                if (empty($matches[1])) {
                    continue;
                }

                $customAttribute = Str::snake($matches[1]);

                if (in_array($customAttribute, array_keys($this->orderByOverrides))) {
                    continue;
                }

                $customAttributes[] = $customAttribute;
            }
        }

        return $customAttributes;
    }

    /**
     * @return JsonResponse
     */
    public function respond()
    {
        $query = $this->buildQuery($this->request);

        $results = $this->paginateQuery($query);
        $results = $this->manipulateCollection($results);
        $meta = $this->makeMeta($query, $results->getCollection());

        return DataTableResponse::success($results, $meta)->json();
    }
}
