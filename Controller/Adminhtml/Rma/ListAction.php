<?php
/**
 * ViraXpress - https://www.viraxpress.com
 *
 * LICENSE AGREEMENT
 *
 * This file is part of the ViraXpress package and is licensed under the ViraXpress license agreement.
 * You can view the full license at:
 * https://www.viraxpress.com/license
 *
 * By utilizing this file, you agree to comply with the terms outlined in the ViraXpress license.
 *
 * DISCLAIMER
 *
 * Modifications to this file are discouraged to ensure seamless upgrades and compatibility with future releases.
 *
 * @category    ViraXpress
 * @package     ViraXpress_Rma
 * @author      ViraXpress
 * @copyright   © 2026 ViraXpress (https://www.viraxpress.com/)
 * @license     https://www.viraxpress.com/license
 */
declare(strict_types=1);

namespace ViraXpress\Rma\Controller\Adminhtml\Rma;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use ViraXpress\Rma\Model\RequestFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Class ListAction
 *
 * Controller to fetch a list of RMA requests for a specific customer via JSON response.
 * Handles filtering, sorting, pagination, and joins order increment ID for admin usage.
 *
 * Example URL:
 *   {ADMIN_BASE}/rma/request/list?customer_id=12&page=1&pagesize=10
 */
class ListAction extends Action implements HttpGetActionInterface
{
    /** @var string Admin ACL resource */
    public const ADMIN_RESOURCE = 'Magento_Backend::content';

    /** @var int Default page size for the result */
    public const DEFAULT_PAGE_SIZE = 5;

    /** @var JsonFactory */
    protected $jsonFactory;

    /** @var RequestFactory */
    protected $requestFactory;

    /** @var RequestInterface */
    protected $request;

    /** @var OrderRepositoryInterface */
    protected $orderRepository;

    /** @var TimezoneInterface */
    protected $timezone;

    /**
     * Constructor
     *
     * @param Context                   $context
     * @param JsonFactory               $jsonFactory
     * @param RequestFactory            $requestFactory
     * @param RequestInterface          $request
     * @param OrderRepositoryInterface  $orderRepository
     * @param TimezoneInterface         $timezone
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        RequestFactory $requestFactory,
        RequestInterface $request,
        OrderRepositoryInterface $orderRepository,
        TimezoneInterface $timezone
    ) {
        parent::__construct($context);
        $this->jsonFactory      = $jsonFactory;
        $this->requestFactory   = $requestFactory;
        $this->request          = $request;
        $this->orderRepository  = $orderRepository;
        $this->timezone         = $timezone;
    }

    /**
     * Get the current store's timezone object
     *
     * @return TimezoneInterface
     */
    public function getTimezone(): TimezoneInterface
    {
        return $this->timezone;
    }

    /**
     * Format a UTC datetime string to the store's timezone and return formatted string
     *
     * @param   string  $utcDatetime
     * @return  string  Store-local
     */
    public function formatUtcDate(string $utcDatetime): string
    {
        $timezone = $this->getTimezone();
        $localDate = $timezone->date(new \DateTime($utcDatetime));
        return $localDate->format('Y-m-d H:i:s');
    }

    /**
     * Execute controller action
     *
     * Reads query parameters such as page, sort order, filters RMA request collection,
     * and returns JSON data with paginated and filtered results.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        // Sanitize query parameters
        $page           = max(1, (int) $this->request->getParam('page', 1));
        $pageSize       = max(1, (int) $this->request->getParam('pagesize', self::DEFAULT_PAGE_SIZE));
        $sort           = (string) $this->request->getParam('sort', 'created_at');
        $dir            = strtolower((string) $this->request->getParam('dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $rmaId          = trim($this->request->getParam('rma_id', ''));
        $orderId        = trim($this->request->getParam('order_id', ''));
        $status         = trim($this->request->getParam('status', ''));
        $createdFrom    = $this->request->getParam('created_at_from');
        $createdTo      = $this->request->getParam('created_at_to');
        $updatedFrom    = $this->request->getParam('updated_at_from');
        $updatedTo      = $this->request->getParam('updated_at_to');
        $customerId     = (int) $this->request->getParam('customer_id');

        // Validate required customer_id
        if ($customerId <= 0) {
            return $this->jsonFactory->create()->setData([
                'items'    => [],
                'total'    => 0,
                'page'     => $page,
                'pagesize' => $pageSize,
                'customerId' => $customerId,
                'message'  => __('Missing or invalid customer_id'),
            ]);
        }

        // Create and filter collection
        $collection = $this->requestFactory->create()->getCollection()
            ->addFieldToFilter('main_table.customer_id', $customerId)
            ->setCurPage($page)
            ->setPageSize($pageSize)
            ->setOrder("main_table.$sort", $dir);

        // Join with sales_order table to get increment_id
        $collection->getSelect()->joinLeft(
            ['so' => $collection->getTable('sales_order')],
            'main_table.order_id = so.entity_id',
            ['increment_id']
        );

        // Apply filters if present
        if ($rmaId !== '') {
            $collection->addFieldToFilter('main_table.rma_id', ['like' => '%' . $rmaId . '%']);
        }
        if ($orderId !== '') {
            $collection->addFieldToFilter('so.increment_id', ['like' => '%' . $orderId . '%']);
        }
        if ($status !== '') {
            $collection->addFieldToFilter('main_table.status', ['like' => '%' . $status . '%']);
        }
        if ($createdFrom) {
            $collection->addFieldToFilter('main_table.created_at', ['gteq' => $createdFrom]);
        }
        if ($createdTo) {
            $collection->addFieldToFilter('main_table.created_at', ['lteq' => $createdTo . ' 23:59:59']);
        }
        if ($updatedFrom) {
            $collection->addFieldToFilter('main_table.updated_at', ['gteq' => $updatedFrom]);
        }
        if ($updatedTo) {
            $collection->addFieldToFilter('main_table.updated_at', ['lteq' => $updatedTo . ' 23:59:59']);
        }

        // Format and collect output
        $items = [];
        foreach ($collection as $rma) {
            $items[] = [
                'rma_id'     => (int) $rma->getRmaId(),
                'order_id'   => $rma->getData('increment_id'),
                'status'     => (string) $rma->getStatus(),
                'created_at' => (string) $this->formatUtcDate($rma->getCreatedAt()),
                'updated_at' => (string) $this->formatUtcDate($rma->getUpdatedAt()),
            ];
        }

        return $this->jsonFactory->create()->setData([
            'items'    => $items,
            'total'    => (int) $collection->getSize(),
            'page'     => $page,
            'pagesize' => $pageSize,
        ]);
    }
}
