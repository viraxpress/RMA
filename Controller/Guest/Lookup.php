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

namespace ViraXpress\Rma\Controller\Guest;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Model\OrderFactory;

/**
 * Guest RMA order‑lookup controller.
 *
 * Validates an order’s increment ID, billing e‑mail, and last name supplied
 * by a guest user, returning a JSON payload indicating whether the credentials
 * match an existing order. Intended for AJAX use on the storefront RMA lookup
 * form.
 *
 */
class Lookup extends Action
{
    /**
     * Factory for loading orders.
     *
     * @var OrderFactory
     */
    protected OrderFactory $orderFactory;

    /**
     * Factory for JSON‑formatted responses.
     *
     * @var JsonFactory
     */
    protected JsonFactory $resultJsonFactory;

    /**
     * Lookup constructor.
     *
     * @param Context      $context            Action context (request, response, etc.).
     * @param OrderFactory $orderFactory       Order factory instance.
     * @param JsonFactory  $resultJsonFactory  JSON result factory instance.
     */
    public function __construct(
        Context $context,
        OrderFactory $orderFactory,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->orderFactory      = $orderFactory;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * Execute controller action.
     *
     * Expected request parameters:
     *  • **order_id** (string) – the order increment ID, e.g. “100000123”
     *  • **email**    (string) – billing e‑mail address
     *  • **lastname** (string) – billing last name
     *
     * @inheritdoc
     *
     * @return Json JSON‑encoded success flag and message.
     */
    public function execute(): Json
    {
        $orderId  = (string) $this->getRequest()->getParam('order_id');
        $email    = (string) $this->getRequest()->getParam('email');
        $lastname = (string) $this->getRequest()->getParam('lastname');

        /** @var Json $result */
        $result = $this->resultJsonFactory->create();

        // Validate required fields.
        if ($orderId === '' || $email === '' || $lastname === '') {
            return $result->setData([
                'success' => false,
                'message' => __('All fields are required.')
            ]);
        }

        // Load the order by increment ID.
        $order = $this->orderFactory->create()->loadByIncrementId($orderId);

        if (!$order->getId()) {
            return $result->setData([
                'success' => false,
                'message' => __('Order not found.')
            ]);
        }

        // Confirm e‑mail and last name match the billing address.
        $billing = $order->getBillingAddress();

        $isMatch = $billing
            && strcasecmp((string) $billing->getEmail(), $email) === 0
            && strcasecmp((string) $billing->getLastname(), $lastname) === 0;

        if ($isMatch) {
            return $result->setData(['success' => true]);
        }

        return $result->setData([
            'success' => false,
            'message' => __('Customer details do not match the order.')
        ]);
    }
}
