<?php
namespace LangleyFoxall\ReactDynamicDataTableLaravelApi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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
     * @var Request
     */
    private $request;

    /**
     * @var callable
     */
    private $queryManipulator;

    /**
     * @var int
     */
    private $perPage = 15;

    /**
     * DataTableResponder constructor.
     *
     * @param $className
     * @param Request $request
     */
    public function __construct($className, Request $request)
    {
        if (!class_exists($className)) {
            throw new \InvalidArgumentException('Provided class does not exist.');
        }

        $this->model = new $className();
        $this->request = $request;

        if (!$this->model instanceof Model) {
            throw new \InvalidArgumentException('Provided class is not an Eloquent model.');
        }
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
     * Builds the Eloquent query based on the request.
     *
     * @param Request $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function buildQuery(Request $request)
    {
        $orderByField = $request->get('orderByField');
        $orderByDirection = $request->get('orderByDirection');

        $query = $this->model->query();

        if ($orderByField && $orderByDirection) {
            $query->orderBy($orderByField, $orderByDirection);
        }

        $queryManipulator = $this->queryManipulator;
        if ($queryManipulator) {
            $queryManipulator($query);
        }

        return $query;
    }

    /**
     * @param Builder $query
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    private function paginateQuery(Builder $query)
    {
        return $query->paginate($this->perPage);
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator $results
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function respond()
    {
        $query = $this->buildQuery($this->request);

        $results = $this->paginateQuery($query);
        $results = $this->manipulateCollection($results);

        return DataTableResponse::success($results)->json();
    }
}
