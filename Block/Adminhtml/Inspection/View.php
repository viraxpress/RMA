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
namespace ViraXpress\Rma\Block\Adminhtml\Inspection;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use ViraXpress\Rma\Model\ItemFactory;
use ViraXpress\Rma\Model\ItemInspection;
use ViraXpress\Rma\Model\ItemInspectionFactory;
use ViraXpress\Rma\Model\Request;
use ViraXpress\Rma\Model\RequestFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Block class for viewing detailed inspection information in the admin panel.
 */
class View extends Template
{
    /**
     * @var RequestFactory
     */
    protected RequestFactory $requestFactory;

    /**
     * @var ItemFactory
     */
    protected ItemFactory $itemFactory;

    /**
     * @var ItemInspectionFactory
     */
    protected ItemInspectionFactory $itemInspectionFactory;

    /**
     * @var OrderFactory
     */
    protected OrderFactory $orderFactory;

    /**
     * @var CustomerFactory
     */
    protected CustomerFactory $customerFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var ItemInspection|null
     */
    protected ?ItemInspection $inspection = null;

    /**
     * @var Request|null
     */
    protected ?Request $rma = null;

    /**
     * @var Order|null
     */
    protected ?Order $order = null;

    /**
     * @var Customer|null
     */
    protected ?Customer $customer = null;

    /**
     * @var TimezoneInterface
     */
    protected TimezoneInterface $timezone;
    /**
     * Constructor.
     *
     * @param Context                   $context
     * @param RequestFactory            $requestFactory
     * @param ItemFactory               $itemFactory
     * @param ItemInspectionFactory     $itemInspectionFactory
     * @param OrderFactory              $orderFactory
     * @param CustomerFactory           $customerFactory
     * @param ScopeConfigInterface      $scopeConfig
     * @param TimezoneInterface         $timezone
     * @param array                     $data
     */
    public function __construct(
        Context                         $context,
        RequestFactory                  $requestFactory,
        ItemFactory                     $itemFactory,
        ItemInspectionFactory           $itemInspectionFactory,
        OrderFactory                    $orderFactory,
        CustomerFactory                 $customerFactory,
        ScopeConfigInterface            $scopeConfig,
        TimezoneInterface               $timezone,
        array                           $data = []
    ) {
        $this->requestFactory           = $requestFactory;
        $this->itemFactory              = $itemFactory;
        $this->itemInspectionFactory    = $itemInspectionFactory;
        $this->orderFactory             = $orderFactory;
        $this->customerFactory          = $customerFactory;
        $this->scopeConfig              = $scopeConfig;
        $this->timezone                 = $timezone;
        parent::__construct($context, $data);
    }

    /**
     * Get available RMA statuses from config.
     *
     * @return string[]
     */
    public function getRmaStatusOptions(): array
    {
        $raw = $this->scopeConfig->getValue('rma/general/status');
        return $raw ? array_map('trim', explode(',', $raw)) : [];
    }

    /**
     * Get available item statuses from config.
     *
     * @return string[]
     */
    public function getItemStatusOptions(): array
    {
        $raw = $this->scopeConfig->getValue('rma/general/item_status');
        return $raw ? array_map('trim', explode(',', $raw)) : [];
    }

    /**
     * Get test result options from config.
     *
     * @return string[]
     */
    public function getTestResultList(): array
    {
        $raw = $this->scopeConfig->getValue('rma/inspection/test_results');
        return $raw ? array_map('trim', explode(',', $raw)) : [];
    }

    /**
     * Get action taken options from config.
     *
     * @return string[]
     */
    public function getActionTakenList(): array
    {
        $raw = $this->scopeConfig->getValue('rma/inspection/action_taken');
        return $raw ? array_map('trim', explode(',', $raw)) : [];
    }

    /**
     * Get inspection status options from config.
     *
     * @return string[]
     */
    public function getInspectionStatusList(): array
    {
        $raw = $this->scopeConfig->getValue('rma/inspection/inspection_status');
        return $raw ? array_map('trim', explode(',', $raw)) : [];
    }

    /**
     * Get inspection data by inspection_id from request.
     *
     * @return ItemInspection|null
     */
    public function getInspectionData(): ?ItemInspection
    {
        if ($this->inspection === null) {
            $inspectionId = $this->getRequest()->getParam('inspection_id');
            if ($inspectionId) {
                $this->inspection = $this->itemInspectionFactory->create()->load($inspectionId);
            }
        }
        return $this->inspection;
    }

    /**
     * Get RMA request data linked to inspection.
     *
     * @return Request|null
     */
    public function getRmaData(): ?Request
    {
        if ($this->rma === null && $this->getInspectionData()) {
            $this->rma = $this->requestFactory
                ->create()
                ->load($this->getInspectionData()->getRmaId());
        }
        return $this->rma;
    }

    /**
     * Get order related to the RMA request.
     *
     * @return Order|null
     */
    public function getOrder(): ?Order
    {
        if ($this->order === null && $this->getRmaData()) {
            $this->order = $this->orderFactory
                ->create()
                ->load($this->getRmaData()->getOrderId());
        }
        return $this->order;
    }

    /**
     * Get customer associated with the order.
     *
     * @return Customer|null
     */
    public function getCustomer(): ?Customer
    {
        if ($this->customer === null && $this->getOrder()) {
            $this->customer = $this->customerFactory
                ->create()
                ->load($this->getOrder()->getCustomerId());
        }
        return $this->customer;
    }

    /**
     * Get item data from RMA linked to the inspection.
     *
     * @return AbstractDb|null
     */
    public function getRmaItemData(): ?AbstractDb
    {
        if (!$this->getInspectionData()) {
            return null;
        }

        return $this->itemFactory->create()->getCollection()
        ->addFieldToFilter('item_id', $this->getInspectionData()->getItemId());
    }

    /**
     * Format a price using the order's currency.
     *
     * @param float $price
     * @return string
     */
    public function formatPrice(float $price): string
    {
        return $this->getOrder()
            ? $this->getOrder()->getOrderCurrency()->formatPrecision($price, 2, [], false)
            : number_format($price, 2);
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
}
