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
namespace ViraXpress\Rma\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;

/**
 * Controller to load the RMA requests grid for a specific order via AJAX.
 *
 * This controller fetches the order ID from the request, validates the order,
 * registers it in the registry, and returns the HTML of the RMA requests grid block.
 */
class RmaOrderRequestsGrid extends Action
{
    /**
     * @var \Magento\Framework\Registry
     */
    protected $coreRegistry;

    /**
     * Constructor
     *
     * @param Action\Context                $context
     * @param \Magento\Framework\Registry   $coreRegistry
     */
    public function __construct(
        Action\Context                      $context,
        \Magento\Framework\Registry         $coreRegistry
    ) {
        parent::__construct($context);
        $this->coreRegistry                 = $coreRegistry;
    }

    /**
     * Execute method.
     *
     * Validates the order ID, loads the order, registers it,
     * and returns the RMA requests grid HTML as raw response.
     *
     * @return \Magento\Framework\Controller\Result\Raw|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        if (!$orderId) {
            $this->messageManager->addErrorMessage(__('Order ID is missing.'));
            return $this->_redirect('sales/order/index');
        }

        $order = $this->_objectManager->create(\Magento\Sales\Model\Order::class)->load($orderId);
        if (!$order->getId()) {
            $this->messageManager->addErrorMessage(__('Order not found.'));
            return $this->_redirect('sales/order/index');
        }

        $this->coreRegistry->register('current_order', $order);

        $resultRaw = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $resultRaw->setContents(
            $this->_view->getLayout()->createBlock(\ViraXpress\Rma\Block\Adminhtml\Order\View\Tab\RmaRequests::class)
            ->setData('is_ajax', true)
            ->toHtml()
        );
        return $resultRaw;
    }
}
