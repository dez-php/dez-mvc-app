<?php

namespace Dez\Mvc\GridRouteMapper;

use Dez\DependencyInjection\Injectable;
use Dez\Http\Request;
use Dez\Router\Router;
use Dez\Url\Url;

abstract class Mapper extends Injectable
{

    const MAPPER_IDENTITY = 'grid-mapper';

    const MAPPER_ORDER_DESC = 'desc';
    const MAPPER_ORDER_ASC = 'asc';

    const MAPPER_LIKE = 'lk';
    const MAPPER_NOT_LIKE = 'nl';

    const MAPPER_NULL = 'null';

    const MAPPER_EQUAL = 'eq';
    const MAPPER_NOT_EQUAL = 'ne';
    const MAPPER_GREATER_THAN = 'gt';
    const MAPPER_GREATER_THAN_EQUAL = 'ge';
    const MAPPER_LESS_THAN = 'lt';
    const MAPPER_LESS_THAN_EQUAL = 'le';

    protected $dataSource = null;

    /**
     * @var array
     */
    protected $allowedFilter = [];

    /**
     * @var array
     */
    protected $allowedOrder = [];

    /**
     * @var null
     */
    protected $uniqueIdentity = null;

    /**
     * @var string
     */
    protected $prefix = '';

    /**
     * @var array
     */
    protected $filter = [];

    /**
     * @var array
     */
    protected $order = [];

    /**
     * @var string
     */
    protected $prefixUrl = '/';

    /**
     * Mapper constructor.
     */
    public function __construct()
    {
        if ($this->uniqueIdentity !== null) {
            $this->setPrefix("{$this->getUniqueIdentity()}-");
        }
    }

    /**
     * @return null
     */
    public function getUniqueIdentity()
    {
        return $this->uniqueIdentity;
    }

    /**
     * @param null $uniqueIdentity
     * @return static
     */
    public function setUniqueIdentity($uniqueIdentity)
    {
        $this->uniqueIdentity = $uniqueIdentity;

        return $this;
    }

    /**
     * @throws MapperException
     */
    public function processRequestParams()
    {
        /** @var Request $request */
        $request = $this->getDi()->get('request');
        /** @var Router $router */
        $router = $this->getDi()->get('router');

        if (null === $request || null === $router) {
            throw new MapperException("Request or Router is required for mapper");
        }

        $dirtyMatches = $router->getDirtyMatches();

        $index = array_search(Mapper::MAPPER_IDENTITY, $dirtyMatches);

        if ($index !== false) {

            $matches = array_slice($dirtyMatches, $index + 1);
            $matches = array_column(array_chunk($matches, 2), 1, 0);

            foreach ($this->getAllowedFilter() as $filterColumn) {
                $conditions = $request->getFromArray($matches, "{$this->getPrefix()}filter-{$filterColumn}", null);

                if (null !== $conditions) {

                    $conditions = array_chunk(explode('-', $conditions), 2);
                    $conditions = array_column($conditions, 1, 0);

                    foreach ($conditions as $criterion => $value) {
                        if ($this->checkFilterCriterion($criterion)) {
                            $this->addFilter($filterColumn, $criterion, $value);
                        }
                    }

                }
            }

            foreach ($this->getAllowedOrder() as $orderColumn) {
                $founded = $request->getFromArray($matches, "{$this->getPrefix()}order-{$orderColumn}", null);

                if (null !== $founded) {
                    $this->setOrder($orderColumn, $founded);
                }
            }
        }

//        $request->getFromArray($router->getRawMatches(), 'filter-');
    }

    public function processDataSource()
    {
        if(! ($this->dataSource instanceof Adapter)) {
            throw new MapperException("Initialize before data source adapter");
        }

        $dataSourceParams = [
            'filter' => $this->getFilter(),
            'order' => $this->getOrder(),
        ];

        $this->getDataSource()->process($dataSourceParams);
    }

    /**
     * @return array
     */
    public function getAllowedFilter()
    {
        return $this->allowedFilter;
    }

    /**
     * @param array $allowedFilter
     * @return static
     */
    public function setAllowedFilter(array $allowedFilter = [])
    {
        $this->allowedFilter = $allowedFilter;

        return $this;
    }

    /**
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * @param string $prefix
     * @return static
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * @param string $criterion
     * @return mixed
     */
    public function checkFilterCriterion($criterion = Mapper::MAPPER_EQUAL)
    {
        return in_array($criterion, $this->getFilterCriteria(), true);
    }

    /**
     * @return array
     */
    public function getFilterCriteria()
    {
        return [
            Mapper::MAPPER_EQUAL,
            Mapper::MAPPER_NOT_EQUAL,
            Mapper::MAPPER_GREATER_THAN,
            Mapper::MAPPER_GREATER_THAN_EQUAL,
            Mapper::MAPPER_LESS_THAN,
            Mapper::MAPPER_LESS_THAN_EQUAL,
            Mapper::MAPPER_LIKE,
            Mapper::MAPPER_NOT_LIKE,
            Mapper::MAPPER_NULL,
        ];
    }

    /**
     * @param $column
     * @param string $criterion
     * @param $value
     * @return $this
     */
    public function addFilter($column, $criterion = Mapper::MAPPER_EQUAL, $value)
    {
        if ($this->checkFilterCriterion($criterion)) {
            $this->filter[$column][$criterion][] = $value;
        }

        return $this;
    }

    /**
     * @param $column
     * @param string $criterion
     * @param null $value
     * @return static
     */
    public function setFilter($column, $criterion = Mapper::MAPPER_EQUAL, $value = null)
    {
        if ($this->checkFilterCriterion($criterion)) {
            $this->filter[$column][$criterion] = [$value];
        }

        return $this;
    }

    /**
     * @param $column
     * @param string $criterion
     * @return $this
     */
    public function resetFilter($column, $criterion = Mapper::MAPPER_EQUAL)
    {
        if ($this->checkFilterCriterion($criterion)) {
            $this->filter[$column][$criterion] = [];
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getAllowedOrder()
    {
        return $this->allowedOrder;
    }

    /**
     * @param array $allowedOrder
     * @return static
     */
    public function setAllowedOrder(array $allowedOrder = [])
    {
        $this->allowedOrder = $allowedOrder;

        return $this;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        $parts = [];

        foreach ($this->getFilter() as $column => $filter) {
            $parts[] = "{$this->getPrefix()}filter-{$column}";
            $filterConditions = [];
            foreach ($filter as $criterion => $value) {
                $value = implode('-', $value);
                $filterConditions[] = "{$criterion}-{$value}";
            }
            $parts[] = implode('-', $filterConditions);
        }

        foreach ($this->getOrder() as $column => $vector) {
            $parts[] = "{$this->getPrefix()}order-{$column}/{$vector}";
        }

        $requestSuffix = implode('/', $parts);

        /** @var Url $url */
        $url = $this->getDi()->get('url');

        $identity = Mapper::MAPPER_IDENTITY;

        return $url->path("{$this->getPrefixUrl()}/{$identity}/{$requestSuffix}");
    }

    /**
     * @return array
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * @return array
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param $column
     * @param string $vector
     * @return static
     */
    public function setOrder($column, $vector = Mapper::MAPPER_ORDER_ASC)
    {
        $this->order[$column] = $vector;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPrefixUrl()
    {
        return $this->prefixUrl;
    }

    /**
     * @param mixed $prefixUrl
     * @return static
     */
    public function setPrefixUrl($prefixUrl)
    {
        $this->prefixUrl = $prefixUrl;

        return $this;
    }

    /**
     * @return Adapter
     */
    public function getDataSource()
    {
        return $this->dataSource;
    }

    /**
     * @param Adapter $dataSource
     */
    public function setDataSource(Adapter $dataSource = null)
    {
        $this->dataSource = $dataSource;
    }


}