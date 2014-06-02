<?php

namespace SS6\ShopBundle\Model\PKGrid;

use Doctrine\ORM\QueryBuilder;
use SS6\ShopBundle\Model\PKGrid\ActionColumn;
use SS6\ShopBundle\Model\PKGrid\Column;
use SS6\ShopBundle\Model\PKGrid\PKGridView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Router;
use Twig_Environment;

class PKGrid {

	/**
	 * @var string
	 */
	private $id;

	/**
	 * @var \SS6\ShopBundle\Model\PKGrid\Column[]
	 */
	private $columns = array();

	/**
	 * @var \SS6\ShopBundle\Model\PKGrid\ActionColumn[]
	 */
	private $actionColumns = array();

	/**
	 * @var bool
	 */
	private $allowPaging = false;

	/**
	 * @var array
	 */
	private $limits = array(30, 100, 200, 500);

	/**
	 * @var int
	 */
	private $limit;

	/**
	 * @var bool
	 */
	private $isLimitFromRequest = false;

	/**
	 * @var int
	 */
	private $defaultLimit = 30;

	/**
	 * @var int
	 */
	private $page = 1;

	/**
	 * @var int|null
	 */
	private $totalCount;

	/**
	 * @var int|null
	 */
	private $pageCount;

	/**
	 * @var string|null
	 */
	private $order;

	/**
	 * @var string|null
	 */
	private $orderDirection;

	/**
	 * @var row
	 */
	private $rows = array();

	/**
	 * @var \Symfony\Component\HttpFoundation\RequestStack
	 */
	private $requestStack;

	/**
	 * @var \Symfony\Component\Routing\Router
	 */
	private $router;

	/**
	 * @var \Twig_Environment
	 */
	private $twig;

	/**
	 * @var \Doctrine\ORM\QueryBuilder
	 */
	private $queryBuilder;

	/**
	 * @var string
	 */
	private $groupBy;

	/**
	 * @param string $id
	 * @param \SS6\ShopBundle\Model\PKGrid\RequestStack $requestStack
	 * @param \SS6\ShopBundle\Model\PKGrid\Router $router
	 * @param \SS6\ShopBundle\Model\PKGrid\Twig_Environment $twig
	 */
	public function __construct($id, RequestStack $requestStack, Router $router, Twig_Environment $twig) {
		$this->id = $id;
		$this->requestStack = $requestStack;
		$this->router = $router;
		$this->twig = $twig;

		$this->limit = $this->defaultLimit;
		$this->page = 1;

		$this->loadFromRequest();
	}

	/**
	 * @param string $id
	 * @param string $queryId
	 * @param string $title
	 * @param boolean $sortable
	 * @return \SS6\ShopBundle\Model\PKGrid\Column
	 */
	public function addColumn($id, $queryId, $title, $sortable = false) {
		if (array_key_exists($id, $this->columns)) {
			throw new \Exception('Duplicate column id "' . $id . '" in grid "' . $this->id .  '"');
		}
		$column = new Column($id, $queryId, $title, $sortable);
		$this->columns[$id] = $column;
		return $column;
	}

	/**
	 * @param string $type
	 * @param string $name
	 * @param string $route
	 * @param array $bindingRouteParams
	 * @param array $additionalRouteParams
	 * @return \SS6\ShopBundle\Model\PKGrid\ActionColumn
	 */
	public function addActionColumn($type, $name, $route, array $bindingRouteParams = null, 
		array $additionalRouteParams = null
	) {
		$actionColumn = new ActionColumn(
			$this->router,
			$type,
			$name,
			$route,
			(array)$bindingRouteParams,
			(array)$additionalRouteParams
		);
		$this->actionColumns[] = $actionColumn;

		return $actionColumn;
	}

	/**
	 * @return \SS6\ShopBundle\Model\PKGrid\PKGridView
	 */
	public function createView() {
		$this->executeQuery();
		if ($this->isAllowedPaging()) {
			$this->executeTotalQuery();
		}
		$gridView = new PKGridView($this, $this->requestStack, $this->router, $this->twig);

		return $gridView;
	}

	public function allowPaging() {
		$this->allowPaging = true;
	}

	/**
	 * @param int $limit
	 */
	public function setDefaultLimit($limit) {
		if (!$this->isLimitFromRequest) {
			$this->limit = (int)$limit;
		}
	}

	/**
	 * @param \Doctrine\ORM\QueryBuilder $queryBuilder
	 * @param string $groupBy
	 */
	public function setQueryBuilder(QueryBuilder $queryBuilder, $groupBy = null) {
		$this->queryBuilder = $queryBuilder;
		$this->groupBy = $groupBy;
	}

	/**
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return \SS6\ShopBundle\Model\PKGrid\Column[]
	 */
	public function getColumns() {
		return $this->columns;
	}

	/**
	 * @return \SS6\ShopBundle\Model\PKGrid\ActionColumn[]
	 */
	public function getActionColumns() {
		return $this->actionColumns;
	}

	/**
	 * @return array
	 */
	public function getRows() {
		return $this->rows;
	}

	/**
	 * @return bool
	 */
	public function isAllowedPaging() {
		return $this->allowPaging;
	}

	/**
	 * @return int
	 */
	public function getLimit() {
		return $this->limit;
	}

	/**
	 * @return array
	 */
	public function getLimits() {
		return $this->limits;
	}

	/**
	 * @return int|null
	 */
	public function getTotalCount() {
		return $this->totalCount;
	}

	/**
	 * @return int
	 */
	public function getPage() {
		return $this->page;
	}

	/**
	 * @return int
	 */
	public function getPageCount() {
		return $this->pageCount;
	}

	/**
	 * @return string|null
	 */
	public function getOrder() {
		return $this->order;
	}

	/**
	 * @return string|null
	 */
	public function getOrderDirection() {
		return $this->orderDirection;
	}

	/**
	 * @param string $order
	 */
	private function setOrder($order) {
		if (substr($order, 0, 1) === '-') {
			$this->orderDirection = 'desc';
		} else {
			$this->orderDirection = 'asc';
		}
		$this->order = trim($order, '-');
	}

	private function loadFromRequest() {
		$requestData = $this->requestStack->getMasterRequest()->get('q', array());
		if (array_key_exists($this->id, $requestData)) {
			$gridData = $requestData[$this->id];
			if (array_key_exists('limit', $gridData)) {
				$this->limit = (int)$gridData['limit'];
				$this->isLimitFromRequest = true;
			}
			if (array_key_exists('page', $gridData)) {
				$this->page = (int)$gridData['page'];
			}
			if (array_key_exists('order', $gridData)) {
				$this->setOrder($gridData['order']);
			}
		}
	}

	private function prepareQuery() {
		if ($this->isAllowedPaging()) {
			$this->queryBuilder
				->setFirstResult($this->limit * ($this->page - 1))
				->setMaxResults($this->limit);
		}
		if ($this->order) {
			$this->queryBuilder
				->orderBy($this->columns[$this->order]->getQueryId(), $this->orderDirection);
		}
	}

	private function prepareTotalQuery() {
		if ($this->isAllowedPaging()) {
			$this->queryBuilder
				->select('COUNT(' . $this->groupBy . ') AS totalCount')
				->setFirstResult(null)
				->setMaxResults(null)
				->resetDQLPart('orderBy')
				->resetDQLPart('groupBy');
		}
	}

	private function executeQuery() {
		$this->prepareQuery();
		$this->rows = $this->queryBuilder->getQuery()->getArrayResult();
	}

	private function executeTotalQuery() {
		$this->prepareTotalQuery();
		$this->totalCount = $this->queryBuilder->getQuery()->getSingleScalarResult();
		$this->pageCount = ceil($this->totalCount / $this->limit);
		$this->page = min($this->page, $this->pageCount);
	}

}
