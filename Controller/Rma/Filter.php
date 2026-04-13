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
namespace ViraXpress\Rma\Controller\Rma;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use ViraXpress\Rma\Model\RequestFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Filter
 *
 * RMA Filter Controller
 * Handles server-side filtering, sorting, and pagination
 */
class Filter implements HttpPostActionInterface
{
    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;

    /**
     * @var RequestFactory
     */
    private RequestFactory $requestFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var TimezoneInterface
     */
    private TimezoneInterface $timezone;

    /**
     * Filter constructor.
     *
     * @param Context            $context
     * @param JsonFactory        $resultJsonFactory
     * @param RequestInterface   $request
     * @param CustomerSession    $customerSession
     * @param RequestFactory     $requestFactory
     * @param TimezoneInterface  $timezone
     * @param LoggerInterface    $logger
     */
    public function __construct(
        Context                  $context,
        JsonFactory              $resultJsonFactory,
        RequestInterface         $request,
        CustomerSession          $customerSession,
        RequestFactory           $requestFactory,
        TimezoneInterface        $timezone,
        LoggerInterface          $logger
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request           = $request;
        $this->customerSession   = $customerSession;
        $this->requestFactory    = $requestFactory;
        $this->timezone          = $timezone;
        $this->logger            = $logger;
    }

    /**
     * Execute the controller action.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            // Validate customer session
            if (!$this->customerSession->isLoggedIn()) {
                return $result->setData([
                    'success' => false,
                    'message' => 'Authentication required'
                ]);
            }

            // Get request parameters
            $search   = trim((string) $this->request->getParam('search', ''));
            $page     = max(1, (int) $this->request->getParam('page', 1));
            $pageSize = min(100, max(5, (int) $this->request->getParam('pageSize', 10)));
            $sortKey  = (string) $this->request->getParam('sortKey', 'created_at');
            $sortDir  = strtoupper((string) $this->request->getParam('sortDir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

            // Validate sort key to prevent SQL injection
            $allowedSortKeys = ['rma_id', 'status', 'created_at', 'updated_at'];
            if (!in_array($sortKey, $allowedSortKeys)) {
                $sortKey = 'created_at';
            }

            // Get customer ID
            $customerId = $this->customerSession->getCustomerId();

            $collection = $this->requestFactory->create()->getCollection()
                ->addFieldToFilter('main_table.customer_id', $customerId)
                ->setCurPage($page)
                ->setPageSize($pageSize)
                ->setOrder("main_table.$sortKey", $sortDir);

            // Join with sales_order to fetch increment_id
            $collection->getSelect()->joinLeft(
                ['so' => $collection->getTable('sales_order')],
                'main_table.order_id = so.entity_id',
                ['increment_id']
            );

            // Apply search filter if provided
            if (strlen($search) >= 2) {
                $collection->getSelect()->where(
                    'main_table.rma_id LIKE :q OR main_table.status LIKE :q OR so.increment_id LIKE :q'
                );
                $collection->addBindParam(':q', '%' . $search . '%');
            }

            // Get total count before pagination
            $totalCount = $collection->getSize();

            // Prepare data
            $data = [];
            foreach ($collection as $rma) {
                $data[] = [
                    'rma_id'     => (int) $rma->getRmaId(),
                    'order_id'   => $rma->getData('increment_id'),
                    'status'     => (string) $rma->getStatus(),
                    'created_at' => $rma->getCreatedAt() ? $this->formatUtcDate($rma->getCreatedAt()) : '',
                    'updated_at' => $rma->getUpdatedAt() ? $this->formatUtcDate($rma->getUpdatedAt()) : '',
                    // Optional: fetch items if needed
                    'items'      => $this->getRmaItems($rma->getRmaId()),
                    'customer_id' => $rma->getCustomerId(),
                ];
            }

            return $result->setData([
                'success'    => true,
                'data'       => $data,
                'total'      => $totalCount,
                'page'       => $page,
                'pageSize'   => $pageSize,
                'totalPages' => ceil($totalCount / $pageSize),
                'search'     => $search,
                'sortKey'    => $sortKey,
                'sortDir'    => $sortDir
            ]);

        } catch (LocalizedException $e) {
            $this->logger->error('RMA Filter LocalizedException: ' . $e->getMessage());
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('RMA Filter Exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $result->setData([
                'success' => false,
                'message' => 'An error occurred while filtering data'
            ]);
        }
    }

    /**
     * Get RMA items for a given RMA ID
     *
     * @param int $rmaId
     * @return array
     */
    private function getRmaItems($rmaId)
    {
        try {
            // This is a simplified version - adjust according to your RMA item model
            $itemsCollection = $this->rmaItemCollectionFactory->create();
            $itemsCollection->addFieldToFilter('rma_id', $rmaId);

            $items = [];
            foreach ($itemsCollection as $item) {
                $items[] = [
                    'item_id' => $item->getItemId(),
                    'product_name' => $item->getProductName(),
                    'sku' => $item->getSku(),
                    'qty_requested' => $item->getQtyRequested(),
                    'reason' => $item->getReason(),
                    'condition' => $item->getCondition(),
                    'status' => $item->getStatus()
                ];
            }

            return $items;
        } catch (\Exception $e) {
            $this->logger->error('Error loading RMA items: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Validate and sanitize search input
     *
     * @param string $search
     * @return string
     */
    private function sanitizeSearch($search)
    {
        // Remove potentially dangerous characters
        $search = preg_replace('/[^\w\s\-]/', '', $search);
        // Limit length to prevent abuse
        return substr(trim($search), 0, 100);
    }

    /**
     * Convert UTC datetime string to store-local formatted datetime.
     *
     * @param string $utcDatetime
     * @return string
     */
    private function formatUtcDate(string $utcDatetime): string
    {
        $localDate = $this->timezone->date(new \DateTime($utcDatetime));
        return $localDate->format('Y-m-d H:i:s');
    }
}
