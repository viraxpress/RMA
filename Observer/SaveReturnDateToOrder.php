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

namespace ViraXpress\Rma\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Store\Model\ScopeInterface;

/**
 * Observer: injects the configured RMA return_date into each new order.
 */
class SaveReturnDateToOrder implements ObserverInterface
{
    /**
     * Configuration path for the return window setting
     */
    private const CONFIG_PATH_RETURN_DATE = 'rma/general/return_window';

    /**
     * @param ScopeConfigInterface              $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface   $scopeConfig
    ) {
    }

    /**
     * Executed on sales_order_place_after.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();

        $configuredDate = $this->scopeConfig->getValue(
            self::CONFIG_PATH_RETURN_DATE,
            ScopeInterface::SCOPE_STORE,
            $order->getStoreId()
        );

        if (!$configuredDate) {
            return;
        }

        $order->setData('return_date', $configuredDate);
        $order->getResource()->saveAttribute($order, 'return_date');
    }
}
