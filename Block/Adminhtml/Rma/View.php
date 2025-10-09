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
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use ViraXpress\Rma\Model\Request;
use ViraXpress\Rma\Model\RequestFactory;
use ViraXpress\Rma\Model\ItemFactory;
use ViraXpress\Rma\Model\ItemImageFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Filesystem\Io\File;

/**
 * Block for viewing RMA details in admin panel.
 */
class View extends Template
{
    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * @var ItemFactory
     */
    protected $itemFactory;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;
    /**
     * ItemImageFactory for images.
     *
     * @var ItemImageFactory
     */
    protected $itemImageFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var TimezoneInterface
     */
    protected $timezone;

    /**
     * @var GroupRepositoryInterface
     */
    protected $groupRepository;
    /**
     * @var File
     */
    protected $file;
    /**
     * View constructor.
     *
     * @param Context                   $context
     * @param RequestFactory            $requestFactory
     * @param ItemFactory               $itemFactory
     * @param OrderFactory              $orderFactory
     * @param CustomerFactory           $customerFactory
     * @param ItemImageFactory          $itemImageFactory
     * @param ScopeConfigInterface      $scopeConfig
     * @param StoreManagerInterface     $storeManager
     * @param TimezoneInterface         $timezone
     * @param GroupRepositoryInterface  $groupRepository
     * @param File                      $file
     * @param array                     $data
     */
    public function __construct(
        Context                         $context,
        RequestFactory                  $requestFactory,
        ItemFactory                     $itemFactory,
        OrderFactory                    $orderFactory,
        CustomerFactory                 $customerFactory,
        ItemImageFactory                $itemImageFactory,
        ScopeConfigInterface            $scopeConfig,
        StoreManagerInterface           $storeManager,
        TimezoneInterface               $timezone,
        GroupRepositoryInterface        $groupRepository,
        File                            $file,
        array                           $data = []
    ) {
        $this->requestFactory           = $requestFactory;
        $this->itemFactory              = $itemFactory;
        $this->orderFactory             = $orderFactory;
        $this->customerFactory          = $customerFactory;
        $this->itemImageFactory         = $itemImageFactory;
        $this->scopeConfig              = $scopeConfig;
        $this->storeManager             = $storeManager;
        $this->timezone                 = $timezone;
        $this->groupRepository          = $groupRepository;
        $this->file                     = $file;
        parent::__construct($context, $data);
    }

    /**
     * Get RMA status options from config.
     *
     * @return string[]
     */
    public function getRmaStatusOptions(): array
    {
        $raw = $this->scopeConfig->getValue('rma/general/status');
        return $raw ? array_map('trim', explode(',', $raw)) : [];
    }

    /**
     * Get item status options from config.
     *
     * @return string[]
     */
    public function getItemStatusOptions(): array
    {
        $raw = $this->scopeConfig->getValue('rma/general/item_status');
        return $raw ? array_map('trim', explode(',', $raw)) : [];
    }

    /**
     * Get RMA data by rma_id parameter.
     *
     * @return Request
     */
    public function getRmaData(): Request
    {
        $rmaId = $this->getRequest()->getParam('rma_id');
        return $this->requestFactory->create()->load($rmaId);
    }

    /**
     * Get RMA items by rma_id.
     *
     * @return AbstractDb
     */
    public function getRmaItemData(): AbstractDb
    {
        $rmaId = $this->getRequest()->getParam('rma_id');
        return $this->itemFactory->create()->getCollection()
            ->addFieldToFilter('rma_id', $rmaId);
    }
    /**
     * Retrieve uploaded images for a specific RMA item.
     *
     * @param int $itemId
     * @return \ViraXpress\Rma\Model\ResourceModel\ItemImage\Collection
     */
    public function getItemImages($itemId)
    {
        return $this->itemImageFactory->create()->getCollection()
            ->addFieldToFilter('item_id', $itemId);
    }

    /**
     * Get order associated with the RMA.
     *
     * @return Order
     */
    public function getOrder(): Order
    {
        $rma = $this->getRmaData();
        return $this->orderFactory->create()->load($rma->getOrderId());
    }

    /**
     * Get customer associated with the order.
     *
     * @return Customer
     */
    public function getCustomer(): Customer
    {
        $order = $this->getOrder();
        return $this->customerFactory->create()->load($order->getCustomerId());
    }
    
    /**
     * Get customer Group Name.
     *
     * @param  int $groupId
     * @return CustomerGroupName
     */
    public function getCustomerGroupName($groupId): string
    {
        try {
            return $this->groupRepository->getById($groupId)->getCode();
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return __('Guest');
        }
    }

    /**
     * Format price using order currency.
     *
     * @param float $price
     * @return string
     */
    public function formatPrice($price): string
    {
        return $this->getOrder()->getOrderCurrency()->formatPrecision($price, 2, [], false);
    }
    /**
     * Get StoreManager instance
     *
     * @return \Magento\Store\Model\StoreManagerInterface
     */
    public function getStoreManager()
    {
        
        return $this->storeManager;
    }
    /**
     * Get the base URL of the current store
     *
     * @return string
     */
    public function getImageBaseUrl()
    {
        $storeManager = \Magento\Framework\App\ObjectManager::getInstance()
        ->get(\Magento\Store\Model\StoreManagerInterface::class);
        $mediaBaseUrl = $storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        return $mediaBaseUrl;
    }
    /**
     * Get order ReplacedOrder with the RMA.
     *
     * @param int $orderId
     * @return Order
     */
    public function getReplacedOrder($orderId): Order
    {
        return $this->orderFactory->create()->load($orderId);
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
     * Get path information for a given file.
     *
     * @param string $file
     * @return array
     */
    public function getPathInfo($file)
    {
        // The correct method is getPathInfo, not gethPathInfo
        return $this->file->getPathInfo($file);
    }
}
