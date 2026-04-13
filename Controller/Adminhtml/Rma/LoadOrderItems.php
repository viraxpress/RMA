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
namespace ViraXpress\Rma\Controller\Adminhtml\Rma;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\LayoutFactory;
use Magento\Sales\Model\OrderFactory;

/**
 * Admin controller to load order items by order increment ID.
 *
 * Returns order item HTML and customer name as JSON response
 * for dynamic loading in the admin RMA creation UI.
 */
class LoadOrderItems extends Action
{
    /**
     * @var JsonFactory
     */
    protected $jsonFactory;

    /**
     * @var LayoutFactory
     */
    protected $layoutFactory;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * Constructor
     *
     * @param Action\Context $context
     * @param JsonFactory $jsonFactory
     * @param LayoutFactory $layoutFactory
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        Action\Context $context,
        JsonFactory $jsonFactory,
        LayoutFactory $layoutFactory,
        OrderFactory $orderFactory
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->layoutFactory = $layoutFactory;
        $this->orderFactory = $orderFactory;
    }

    /**
     * Execute action
     *
     * Loads order by increment ID, generates HTML for order items block,
     * and returns JSON response with HTML and customer name.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->jsonFactory->create();
        $incrementId = $this->getRequest()->getParam('increment_id');

        try {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $this->orderFactory->create()->loadByIncrementId($incrementId);

            if (!$order->getId()) {
                throw new \Magento\Framework\Exception\NoSuchEntityException(__('Order does not exist.'));
            }

            $layout = $this->layoutFactory->create();
            $block = $layout->createBlock(\ViraXpress\Rma\Block\Adminhtml\Rma\Items::class);
            $block->setOrder($order);

            $html = $block->toHtml();

            return $resultJson->setData([
                'success' => true,
                'html' => $html,
                'customer_name' => $order->getCustomerName(),
                'customer_email'=>$order->getCustomerEmail()
            ]);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return $resultJson->setData(['success' => false, 'message' => __('Order not found')]);
        } catch (\Exception $e) {
            return $resultJson->setData(['success' => false,
            'message' => __('Unable to load order items: ') . $e->getMessage()]);
        }
    }
}
