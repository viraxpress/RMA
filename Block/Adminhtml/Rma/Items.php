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
namespace ViraXpress\Rma\Block\Adminhtml\Rma;

use Magento\Backend\Block\Template;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use ViraXpress\Rma\Model\RequestFactory;
use ViraXpress\Rma\Model\ItemFactory;
use Magento\Catalog\Model\ProductRepository;

/**
 * Block class for displaying RMA-related order items in the admin panel.
 */
class Items extends Template
{
    /**
     * @var string
     */
    protected $_template = 'ViraXpress_Rma::rma/items.phtml';

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * @var ItemFactory
     */
    protected $itemFactory;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var Order|null
     */
    protected $order;

    /**
     * Constructor
     *
     * @param Template\Context          $context
     * @param OrderRepositoryInterface  $orderRepository
     * @param ScopeConfigInterface      $scopeConfig
     * @param RequestFactory            $requestFactory
     * @param ItemFactory               $itemFactory
     * @param ProductRepository         $productRepository
     * @param array                     $data
     */
    public function __construct(
        Template\Context                $context,
        OrderRepositoryInterface        $orderRepository,
        ScopeConfigInterface            $scopeConfig,
        RequestFactory                  $requestFactory,
        ItemFactory                     $itemFactory,
        ProductRepository               $productRepository,
        array                           $data = []
    ) {
        $this->orderRepository          = $orderRepository;
        $this->scopeConfig              = $scopeConfig;
        $this->requestFactory           = $requestFactory;
        $this->itemFactory              = $itemFactory;
        $this->productRepository        = $productRepository;
        parent::__construct($context, $data);
    }

    /**
     * Load order by increment ID
     *
     * @param string $incrementId
     * @return $this
     */
    public function setOrderByIncrementId($incrementId)
    {
        $this->order = $this->orderRepository->get($incrementId);
        return $this;
    }

    /**
     * Set order object
     *
     * @param Order $order
     * @return $this
     */
    public function setOrder(Order $order)
    {
        $this->order = $order;
        return $this;
    }

    /**
     * Get the loaded order
     *
     * @return Order|null
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * Get visible items from the order
     *
     * @return \Magento\Sales\Model\Order\Item[]
     */
    public function getOrderItems()
    {
        return $this->order ? $this->order->getAllVisibleItems() : [];
    }

    /**
     * Get return reasons from config
     *
     * @return string[]
     */
    public function getReturnReasons()
    {
        $reasons = $this->scopeConfig->getValue(
            'rma/reasons/return_reasons',
            ScopeInterface::SCOPE_STORE
        );
        return $reasons ? explode(',', $reasons) : [];
    }

    /**
     * Get return resolutions from config
     *
     * @return string[]
     */
    public function getReturnResolutions()
    {
        $resolutions = $this->scopeConfig->getValue(
            'rma/reasons/resolutions',
            ScopeInterface::SCOPE_STORE
        );
        return $resolutions ? explode(',', $resolutions) : [];
    }

    /**
     * Get item conditions from config
     *
     * @return string[]
     */
    public function getItemConditions()
    {
        $conditions = $this->scopeConfig->getValue(
            'rma/reasons/item_conditions',
            ScopeInterface::SCOPE_STORE
        );
        return $conditions ? explode(',', $conditions) : [];
    }

    /**
     * Get allowed product types from config
     *
     * @return string[]
     */
    public function getAllowedProductTypes()
    {
        $types = $this->scopeConfig->getValue(
            'rma/eligibility/allowed_product_types',
            ScopeInterface::SCOPE_STORE
        );
        return $types ? explode(',', $types) : [];
    }

    /**
     * Get allowed category IDs from config
     *
     * @return string[]
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
     * Check if item's product type is allowed
     *
     * @param \Magento\Sales\Model\Order\Item $item
     * @return bool
     */
    public function isItemAllowedProductType($item)
    {
        $allowedTypes = $this->getAllowedProductTypes();
        return empty($allowedTypes) || in_array($item->getProductType(), $allowedTypes);
    }

    /**
     * Check if item's category is allowed
     *
     * @param \Magento\Sales\Model\Order\Item $item
     * @return bool
     */
    public function isItemAllowedCategory($item)
    {
        $allowedCategories = $this->getAllowedCategories();
        if (empty($allowedCategories)) {
            return true;
        }

        try {
            $product = $this->productRepository->getById($item->getProductId());
            foreach ($product->getCategoryIds() as $catId) {
                if (in_array($catId, $allowedCategories)) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * Get RMA requests for the given order
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
     * Get RMA item data for a specific RMA ID
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
     * Get total previously requested quantity per product in an order
     *
     * @param int $orderId
     * @return array<int, int>  [product_id => qty_requested]
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
     * Check if file upload is allowed from config
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
     * Get allowed file types from config
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
     * Get maximum allowed file size from config
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
}
