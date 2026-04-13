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
namespace ViraXpress\Rma\Block;

use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use ViraXpress\Rma\Model\RequestFactory;
use ViraXpress\Rma\Model\ItemFactory;
use ViraXpress\Rma\Model\ResourceModel\Request\Collection as RequestCollection;
use ViraXpress\Rma\Model\ResourceModel\Item\Collection as ItemCollection;
use Magento\Catalog\Api\ProductRepositoryInterface;

/**
 * Class GuestRma
 *
 * Block for Guest RMA Page
 */
class GuestRma extends Template
{
    /** @var OrderFactory */
    protected $orderFactory;

    /** 
     * @var RequestInterface    
     */  
    protected $request;

    /** @var ScopeConfigInterface */
    protected $scopeConfig;

    /** @var TimezoneInterface */
    protected $timezone;

    /** @var RequestFactory */
    protected $rmaRequestFactory;

    /** @var ItemFactory */
    protected $itemFactory;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * GuestRma constructor.
     *
     * @param Template\Context              $context
     * @param OrderFactory                  $orderFactory
     * @param RequestInterface              $request
     * @param ScopeConfigInterface          $scopeConfig
     * @param TimezoneInterface             $timezone
     * @param RequestFactory                $rmaRequestFactory
     * @param ItemFactory                   $itemFactory
     * @param ProductRepositoryInterface    $productRepository
     * @param array                         $data
     */
    public function __construct(
        Template\Context                    $context,
        OrderFactory                        $orderFactory,
        RequestInterface                    $request,
        ScopeConfigInterface                $scopeConfig,
        TimezoneInterface                   $timezone,
        RequestFactory                      $rmaRequestFactory,
        ItemFactory                         $itemFactory,
        ProductRepositoryInterface          $productRepository,
        array                               $data = []
    ) {
        parent::__construct($context, $data);
        $this->orderFactory                 = $orderFactory;
        $this->request                      = $request;
        $this->scopeConfig                  = $scopeConfig;
        $this->timezone                     = $timezone;
        $this->rmaRequestFactory            = $rmaRequestFactory;
        $this->itemFactory                  = $itemFactory;
        $this->productRepository            = $productRepository;
    }

    /**
     * Retrieve valid order based on request parameters and configuration.
     *
     * @return Order|null
     */
    public function getOrder()
    {
        if (!$this->isRmaEnabled()) {
            return null;
        }

        $orderId = $this->request->getParam('order_id');
        $email = $this->request->getParam('email');
        $lastname = $this->request->getParam('lastname');

        if (!$orderId || !$email || !$lastname) {
            return null;
        }

        $statuses = $this->scopeConfig->getValue(
            'rma/eligibility/allowed_statuses',
            ScopeInterface::SCOPE_STORE
        );
        $statuses = $statuses ? array_map('trim', explode(',', $statuses)) : [];

        $order = $this->orderFactory->create()->loadByIncrementId($orderId);

        if ($order->getId()) {
            $billing = $order->getBillingAddress();
            $orderStatus = $order->getStatus();

            if (strtolower($billing->getEmail()) === strtolower($email) &&
                strtolower($billing->getLastname()) === strtolower($lastname) &&
                (empty($statuses) || in_array($orderStatus, $statuses))
            ) {
                return $order;
            }
        }

        return null;
    }

    /**
     * Get request parameter by key
     *
     * @param string $key
     * @return mixed|null
     */
    public function getParam($key)
    {
        return $this->request->getParam($key);
    }

    /**
     * Check if RMA is enabled
     *
     * @return bool
     */
    public function isRmaEnabled()
    {
        return $this->scopeConfig->isSetFlag('rma/general/enable', ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get allowed category IDs
     *
     * @return int[]
     */
    public function getAllowedCategories()
    {
        $categories = $this->scopeConfig->getValue(
            'rma/eligibility/allowed_categories',
            ScopeInterface::SCOPE_STORE
        );
        return $categories ? explode(',', $categories) : [];
    }

    /**
     * Get return window in days
     *
     * @return int
     */
    public function getReturnWindow()
    {
        $returnWindow = $this->scopeConfig->getValue(
            'rma/general/return_window',
            ScopeInterface::SCOPE_STORE
        );
        return is_numeric($returnWindow) ? (int)$returnWindow : 0;
    }

    /**
     * Calculate remaining return days
     *
     * @param Order $order
     * @return int
     */
    public function getDaysRemaining($order)
    {
        $returnWindow = $this->getReturnWindow();
        if ($returnWindow <= 0) {
            return -1;
        }

        $dateUpdated = new \DateTime($order->getUpdatedAt());
        $now = $this->timezone->date();
        $daysPassed = (int)$now->diff($dateUpdated)->format('%a');

        return $returnWindow - $daysPassed;
    }

    /**
     * Check if file upload is allowed
     *
     * @return bool
     */
    public function isFileUploadAllowed(): bool
    {
        return $this->scopeConfig->isSetFlag(
            'rma/return_configuration/allow_file_upload',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get allowed file types
     *
     * @return string[]
     */
    public function getAllowedFileTypes(): array
    {
        $types = $this->scopeConfig->getValue(
            'rma/return_configuration/allowed_file_types',
            ScopeInterface::SCOPE_STORE
        );
        return $types ? array_map('trim', explode(',', $types)) : [];
    }

    /**
     * Get max file Count for uploads.
     *
     * @return int
     */
    public function getMaxFileCount(): int
    {
        $count = $this->scopeConfig->getValue(
            'rma/return_configuration/max_file_count',
            ScopeInterface::SCOPE_STORE
        );
        return is_numeric($count) ? (int)$count : 0;
    }

    /**
     * Get maximum file size in MB
     *
     * @return float
     */
    public function getMaxFileSize(): float
    {
        $size = $this->scopeConfig->getValue(
            'rma/return_configuration/max_file_size',
            ScopeInterface::SCOPE_STORE
        );
        return is_numeric($size) ? (float)$size : 0.0;
    }

    /**
     * Get allowed order statuses
     *
     * @return string[]
     */
    public function getStatus(): array
    {
        $statuses = $this->scopeConfig->getValue(
            'rma/eligibility/allowed_statuses',
            ScopeInterface::SCOPE_STORE
        );
        return $statuses ? explode(',', $statuses) : [];
    }

    /**
     * Get return reasons
     *
     * @return string[]
     */
    public function getReturnReasons(): array
    {
        $reasons = $this->scopeConfig->getValue(
            'rma/reasons/return_reasons',
            ScopeInterface::SCOPE_STORE
        );
        return $reasons ? explode(',', $reasons) : [];
    }

    /**
     * Get return resolutions
     *
     * @return string[]
     */
    public function getReturnResolutions(): array
    {
        $resolutions = $this->scopeConfig->getValue(
            'rma/reasons/resolutions',
            ScopeInterface::SCOPE_STORE
        );
        return $resolutions ? explode(',', $resolutions) : [];
    }

    /**
     * Get item conditions
     *
     * @return string[]
     */
    public function getItemConditions(): array
    {
        $conditions = $this->scopeConfig->getValue(
            'rma/reasons/item_conditions',
            ScopeInterface::SCOPE_STORE
        );
        return $conditions ? explode(',', $conditions) : [];
    }

    /**
     * Get allowed product types
     *
     * @return string[]
     */
    public function getAllowedProductTypes(): array
    {
        $types = $this->scopeConfig->getValue(
            'rma/eligibility/allowed_product_types',
            ScopeInterface::SCOPE_STORE
        );
        return $types ? explode(',', $types) : [];
    }

    /**
     * Get category IDs for a given product ID.
     *
     * @param int $productId
     * @return array
     */
    public function getProductCategoryIds(int $productId): array
    {
        try {
            $product = $this->productRepository->getById($productId);
            return $product->getCategoryIds() ?? [];
        } catch (NoSuchEntityException $e) {
            return [];
        }
    }

    /**
     * Get the product type for a given product ID.
     *
     * @param int $productId
     * @return string|null
     */
    public function getProductType(int $productId): ?string
    {
        try {
            $product = $this->productRepository->getById($productId);
            return $product->getTypeId();
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * Check if order item belongs to any allowed category.
     *
     * @param \Magento\Sales\Model\Order\Item $item
     * @return bool
     */
    public function isItemAllowedCategory($item): bool
    {
        $allowed = $this->getAllowedCategories();
        if (empty($allowed)) {
            return true;
        }

        $productId = (int) $item->getProductId();
        $productCatIds = $this->getProductCategoryIds($productId);

        return !empty(array_intersect($productCatIds, $allowed));
    }

    /**
     * Check if order item has an allowed product type.
     *
     * @param \Magento\Sales\Model\Order\Item $item
     * @return bool
     */
    public function isItemAllowedProductType($item): bool
    {
        $allowedTypes = $this->getAllowedProductTypes();

        if (empty($allowedTypes)) {
            return true;
        }

        $productId = (int) $item->getProductId();
        $productType = $this->getProductType($productId);

        return in_array($productType, $allowedTypes, true);
    }

    /**
     * Get RMA requests by order ID
     *
     * @param int $orderId
     * @return RequestCollection
     */
    public function getRmaData($orderId)
    {
        return $this->rmaRequestFactory->create()->getCollection()
            ->addFieldToFilter('order_id', $orderId);
    }

    /**
     * Get RMA items by RMA ID
     *
     * @param int $rmaId
     * @return ItemCollection
     */
    public function getRmaItemData($rmaId)
    {
        return $this->itemFactory->create()->getCollection()
            ->addFieldToFilter('rma_id', $rmaId);
    }

    /**
     * Get quantity already requested for items in this order
     *
     * @param int $orderId
     * @return array
     */
    public function getPreviouslyRequestedQtyMap($orderId)
    {
        $rmaRequests = $this->getRmaData($orderId);
        $itemQtyMap = [];

        foreach ($rmaRequests as $rma) {
            $rmaItems = $this->getRmaItemData($rma->getId());

            foreach ($rmaItems as $rmaItem) {
                if (strtolower($rmaItem->getStatus()) === 'cancelled') {
                    continue;
                }

                $orderItemId = (int) $rmaItem->getProductId();
                $qty = (int) $rmaItem->getQtyRequested();

                if (!isset($itemQtyMap[$orderItemId])) {
                    $itemQtyMap[$orderItemId] = 0;
                }

                $itemQtyMap[$orderItemId] += $qty;
            }
        }

        return $itemQtyMap;
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
        $timezone=$this->getTimezone();
        $localDate = $timezone->date(new \DateTime($utcDatetime));
        return $localDate->format('Y-m-d H:i:s');
    }

    /**
     * Safely encode data into JSON format with HTML-sensitive characters escaped.
     *
     * This method uses `json_encode` with flags to escape:
     * - HTML tags (`<`, `>`)
     * - Ampersands (`&`)
     * - Apostrophes (`'`)
     * - Double quotes (`"`)
     *
     * This prevents potential XSS vulnerabilities when embedding JSON in HTML.
     *
     * @param mixed $data The data to be encoded to JSON.
     *
     * @return string The safely encoded JSON string.
     */
    public function safeJson($data)
    {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    /**
     * Get formatted order items.
     *
     * Retrieves the visible order items with returnable quantity, mapped for validation.
     * Includes formatted prices and other key order-related details for display or processing.
     *
     * @return array
     */
    public function getFormatedOrderItems()
    {
        $order = $this->getOrder();
        $previousRmaQty = $this->getPreviouslyRequestedQtyMap($order->getId());
        $orderItems = [];
        $validatedItemIdsMap = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $proId                      = (int)$item->getProductId();
            $orderedQty                 = (int)$item->getQtyOrdered();
            $returnedQty                = $previousRmaQty[$proId] ?? 0;
            $maxReturnable              = max($orderedQty - $returnedQty, 0);

            // mapping for JS validation
            if ($this->isItemAllowedCategory($item) && $this->isItemAllowedProductType($item)) {
                $validatedItemIdsMap[$order->getId()][] = $item->getId();
            }

            $orderItems[] = [
                'item_id'               => $item->getId(),
                'product_id'            => $item->getProductId(),
                'name'                  => $item->getName(),
                'sku'                   => $item->getSku(),
                'qty_ordered'           => $orderedQty,
                'qty_returnable'        => $maxReturnable,
                'price'                 => $order->formatPrice($item->getPrice()),
                'totalPrice'            => $order->formatPrice($item->getRowTotal()),
                'grandTotal'            => $order->formatPrice($order->getGrandTotal()),
                'created_at'            => $this->formatUtcDate($order->getCreatedAt()),
                'order_increment_id'    => $order->getIncrementId(),
                'parent_item_id'        => $item->getParentItemId()
            ];
        }
        return [
            'orderItems'         => $orderItems,
            'validatedItemIdsMap'=> $validatedItemIdsMap
        ];
    }

    /**
     * Get detailed order information.
     *
     * Returns the order entity with its items, totals, status, and remaining days for RMA.
     * Uses formatted order items and date formats for consistent display.
     *
     * @return array
     */
    public function getDetailedOrder()
    {
        $order = $this->getOrder();
        $FormatedOrder = $this->getFormatedOrderItems();
        $detailedOrders[] = [
            'entity_id'                 => $order->getId(),
            'items'                     => $FormatedOrder['orderItems'],
            'created_at'                => $this->formatUtcDate($order->getCreatedAt()),
            'status'                    => $order->getStatusLabel(),
            'grand_total'               => $order->formatPrice($order->getGrandTotal()),
            'increment_id'              => $order->getIncrementId(),
            'days_remaining'            => $this->getDaysRemaining($order)
        ];
        return $detailedOrders;
    }

    /**
     * Get upload configuration.
     *
     * Prepares file upload settings like allowed types, maximum size, and file count.
     * Encodes the configuration in a safe JSON format for frontend consumption.
     *
     * @return string
     */
    public function getUploadCfg()
    {
        $uploadCfg = $this->safeJson([
            'uploadAllowed'             => $this->isFileUploadAllowed(),
            'allowedTypes'              => $this->getAllowedFileTypes(),
            'maxSizeMb'                 => $this->getMaxFileSize(),
            'maxFileCount'              => $this->getMaxFileCount()
        ]);
        return $uploadCfg;
    }

    /**
     * Get context data for the RMA form.
     *
     * Provides key customer and form details such as customer info, save URL, and form key.
     * Returns the data in a safe JSON format for frontend initialization.
     *
     * @return string
     */
    public function getCtx()
    {
        $order = $this->getOrder();
        $ctx  = [
            "customerId"                => (int)$order->getCustomerId(),
            "customerEmail"             => $order->getCustomerEmail(),
            "customerName"              => $order->getCustomerFirstname() . " " . $order->getCustomerLastname(),
            "saveUrl"                   => $this->getUrl("vx_rma/rmareturn/save"),
            "formKey"                   => $this->getFormKey()
        ];
        return $ctx;
    }
}
