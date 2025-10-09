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
namespace ViraXpress\Rma\Block;

use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use ViraXpress\Rma\Model\RequestFactory;
use ViraXpress\Rma\Model\ItemFactory;
use Magento\Customer\Model\CustomerFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Data\Form\FormKey;

/**
 * Class ReturnOrders
 * Block for retrieving and managing return orders and related RMA data.
 */
class ReturnOrders extends Template
{
    /**
     * @var OrderCollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var TimezoneInterface
     */
    protected $timezone;

    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * @var ItemFactory
     */
    protected $itemFactory;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var FromKey
     */
    protected $formKey;
    /**
     * ReturnOrders constructor.
     *
     * @param Template\Context              $context
     * @param OrderCollectionFactory        $orderCollectionFactory
     * @param ScopeConfigInterface          $scopeConfig
     * @param CustomerSession               $customerSession
     * @param Registry                      $registry
     * @param RequestFactory                $requestFactory
     * @param ItemFactory                   $itemFactory
     * @param CustomerFactory               $customerFactory
     * @param TimezoneInterface             $timezone
     * @param ProductRepositoryInterface    $productRepository
     * @param FormKey                       $formKey
     * @param array                         $data
     */
    public function __construct(
        Template\Context                    $context,
        OrderCollectionFactory              $orderCollectionFactory,
        ScopeConfigInterface                $scopeConfig,
        CustomerSession                     $customerSession,
        Registry                            $registry,
        RequestFactory                      $requestFactory,
        ItemFactory                         $itemFactory,
        CustomerFactory                     $customerFactory,
        TimezoneInterface                   $timezone,
        ProductRepositoryInterface          $productRepository,
        FormKey                             $formKey,
        array                               $data = []
    ) {
        $this->orderCollectionFactory       = $orderCollectionFactory;
        $this->scopeConfig                  = $scopeConfig;
        $this->customerSession              = $customerSession;
        $this->registry                     = $registry;
        $this->requestFactory               = $requestFactory;
        $this->itemFactory                  = $itemFactory;
        $this->customerFactory              = $customerFactory;
        $this->timezone                     = $timezone;
        $this->productRepository            = $productRepository;
        $this->formKey                      = $formKey;
        parent::__construct($context, $data);
    }
    /**
     * Get the Form Key.
     *
     * @return string
     */
    public function getFormKey()
    {
        return $this->formKey->getFormKey();
    }
    /**
     * Check if RMA module is enabled.
     *
     * @return bool
     */
    public function isRmaEnabled()
    {
        return $this->scopeConfig->isSetFlag('rma/general/enable', ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get customer ID from registry.
     *
     * @return int|null
     */
    public function getCustomerId()
    {
        return $this->registry->registry('rma_customer_id');
    }

    /**
     * Get customer model for current customer ID.
     *
     * @return \Magento\Customer\Model\Customer|null
     */
    public function getCustomer()
    {
        $cus_id = $this->getCustomerId();
        return $this->customerFactory->create()->load($cus_id);
    }

    /**
     * Get allowed category IDs.
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
     * Get return window (in days).
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
     * Get return-eligible orders.
     *
     * @return \Magento\Sales\Model\ResourceModel\Order\Collection|array
     */
    public function getReturnOrders()
    {
        if (!$this->isRmaEnabled() || !$this->getCustomerId()) {
            return [];
        }

        $statuses = $this->scopeConfig->getValue(
            'rma/eligibility/allowed_statuses',
            ScopeInterface::SCOPE_STORE
        );
        $statuses = $statuses ? explode(',', $statuses) : [];

        $collection = $this->orderCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter('customer_id', $this->getCustomerId());

        if (!empty($statuses)) {
            $collection->addFieldToFilter('status', ['in' => $statuses]);
        }

        return $collection->setOrder('created_at', 'DESC');
    }

    /**
     * Get remaining return window days.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return int
     */
    public function getDaysRemaining($order)
    {
        $returnWindow = $order->getReturnDate() ?? $this->getReturnWindow();
        if ($returnWindow <= 0) {
            return -1;
        }

        $dateUpdated = new \DateTime($order->getUpdatedAt());
        $now = $this->timezone->date();
        $daysPassed = (int)$now->diff($dateUpdated)->format('%a');

        return $returnWindow - $daysPassed;
    }

    /**
     * Check if file upload is enabled.
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
     * Get allowed file extensions.
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
     * Get max file size for uploads (MB).
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
     * Get allowed order statuses.
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
     * Get return reasons.
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
     * Get return resolutions.
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
     * Get item conditions.
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
     * Get allowed product types.
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
     * Get RMA request collection by order ID.
     *
     * @param int $orderId
     * @return \ViraXpress\Rma\Model\ResourceModel\Request\Collection
     */
    public function getRmaData($orderId)
    {
        return $this->requestFactory->create()->getCollection()
            ->addFieldToFilter('order_id', $orderId);
    }

    /**
     * Get RMA item collection by RMA ID.
     *
     * @param int $rmaId
     * @return \ViraXpress\Rma\Model\ResourceModel\Item\Collection
     */
    public function getRmaItemData($rmaId)
    {
        return $this->itemFactory->create()->getCollection()
            ->addFieldToFilter('rma_id', $rmaId);
    }

    /**
     * Get requested quantity map for order items.
     *
     * @param int $orderId
     * @return array [order_item_id => requested_qty]
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
        $orders = $this->getReturnOrders();
        $validatedItemIdsMap = [];
        $orderItems = [];
        foreach ($orders as $order) {
            $previousRmaQty = $this->getPreviouslyRequestedQtyMap($order->getId());

            foreach ($order->getAllVisibleItems() as $item) {
                $proId                      = (int)$item->getProductId();
                $orderedQty                 = (int)$item->getQtyOrdered();
                $returnedQty                = $previousRmaQty[$proId] ?? 0;
                $maxReturnable              = max($orderedQty - $returnedQty, 0);

                // mapping for JS validation
                if ($this->isItemAllowedCategory($item) && $this->isItemAllowedProductType($item)) {
                    $validatedItemIdsMap[$order->getId()][] = $item->getId();
                }

                $orderItems[$order->getId()][] = [
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
    public function getDetailedOrders()
    {
        $orders         = $this->getReturnOrders();
        $FormatedOrder  = $this->getFormatedOrderItems();
        $detailedOrders =[];
        foreach ($orders as $order) {
            $detailedOrders[] = [
                'entity_id'                 => $order->getId(),
                'items'                     => $FormatedOrder['orderItems'][$order->getId()] ?? [],
                'created_at'                => $this->formatUtcDate($order->getCreatedAt()),
                'status'                    => $order->getStatusLabel(),
                'grand_total'               => $order->formatPrice($order->getGrandTotal()),
                'increment_id'              => $order->getIncrementId(),
                'days_remaining'            => $this->getDaysRemaining($order)
            ];
        }
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
        $customer = $this->getCustomer();
        $ctx  = [
            "customerId"                => (int)$customer->getId(),
            "customerEmail"             => $customer->getEmail(),
            "customerName"              => $customer->getFirstname() . " " . $customer->getLastname(),
            "saveUrl"                   => $this->getUrl("vx_rma/rmareturn/save"),
            "formKey"                   => $this->getFormKey()
        ];
        return $ctx;
    }
}
