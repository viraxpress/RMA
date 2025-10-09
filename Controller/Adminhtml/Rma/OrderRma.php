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
 * @copyright   © 2024 ViraXpress (https://www.viraxpress.com/)
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
 * Return‑Request list (JSON) for a specific Order — Admin‑side.
 *
 * URL example:
 *   {ADMIN_BASE}/rma/request/list?order_id=12&page=1&pagesize=10
 */
class OrderRma extends Action implements HttpGetActionInterface
{
    /**
     * Authorization level of a basic admin session
     */
    public const ADMIN_RESOURCE = 'Magento_Backend::content';

    public const DEFAULT_PAGE_SIZE = 5;
    /** @var JsonFactory */
    protected $jsonFactory;

    /** @var RequestFactory */
    protected $requestFactory;

    /** @var RequestInterface */
    protected $request;

    /** @var OrderRepositoryInterface */
    protected $orderRepository;

    /**
     * @var TimezoneInterface
     */
    protected $timezone;

    /**
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param RequestFactory $requestFactory
     * @param RequestInterface $request
     * @param OrderRepositoryInterface $orderRepository
     * @param TimezoneInterface $timezone
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
        $this->jsonFactory = $jsonFactory;
        $this->requestFactory = $requestFactory;
        $this->request = $request;
        $this->orderRepository = $orderRepository;
        $this->timezone = $timezone;
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
     * Convert UTC datetime string to store-local formatted datetime.
     *
     * @param string $utcDatetime
     * @return string
     */
    public function formatUtcDate(string $utcDatetime): string
    {
        $timezone = $this->getTimezone();
        $localDate = $timezone->date(new \DateTime($utcDatetime));
        return $localDate->format('Y-m-d H:i:s');
    }
    /**
     * @inheritdoc
     */
    public function execute()
    {
        /* ---------- read & sanitize query params ---------- */
        $page           = max(1, (int) $this->request->getParam('page', 1));
        $pageSize       = max(1, (int) $this->request->getParam('pagesize', self::DEFAULT_PAGE_SIZE));
        $sort           = (string) $this->request->getParam('sort', 'created_at');
        $dir            = strtolower((string) $this->request->getParam('dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $rmaId          = trim($this->request->getParam('rma_id', ''));
        $status         = trim($this->request->getParam('status', ''));
        $createdFrom    = $this->request->getParam('created_at_from');
        $createdTo      = $this->request->getParam('created_at_to');
        $updatedFrom    = $this->request->getParam('updated_at_from');
        $updatedTo      = $this->request->getParam('updated_at_to');
        $OrderId     = (int) $this->request->getParam('order_id');

        if ($OrderId <= 0) {
            return $this->jsonFactory->create()->setData([
                'items'    => [],
                'total'    => 0,
                'page'     => $page,
                'pagesize' => $pageSize,
                'message'  => __('Missing or invalid order_id'),
            ]);
        }

        /* -------------------- collection ------------------- */
        $collection = $this->requestFactory->create()->getCollection()
            ->addFieldToFilter('main_table.order_id', $OrderId)
            ->setCurPage($page)
            ->setPageSize($pageSize)
            ->setOrder("main_table.$sort", $dir);

        $collection->getSelect()->joinLeft(
            ['so' => $collection->getTable('sales_order')],
            'main_table.order_id = so.entity_id',
            ['increment_id']
        );

        if ($rmaId !== '') {
            $collection->addFieldToFilter('main_table.rma_id', ['like' => '%' . $rmaId . '%']);
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

        /* --------------------- output ---------------------- */
        $items = [];
        foreach ($collection as $rma) {
            $items[] = [
                'rma_id'     => (int) $rma->getRmaId(),
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
