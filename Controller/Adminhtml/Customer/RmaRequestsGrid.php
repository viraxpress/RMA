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
namespace ViraXpress\Rma\Controller\Adminhtml\Customer;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;
use Magento\Customer\Controller\RegistryConstants;

/**
 * Controller class for loading the RMA requests grid via AJAX in the admin customer edit page.
 *
 * This controller retrieves the customer ID from the request, registers it in the registry,
 * and returns the HTML for the RMA requests grid block as a raw response.
 */
class RmaRequestsGrid extends Action
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
     * Execute method for the controller.
     *
     * Retrieves the customer ID from the request, registers it,
     * and returns the RMA requests grid HTML block content as raw response.
     *
     * @return \Magento\Framework\Controller\Result\Raw|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $customerId = $this->getRequest()->getParam('id');
        if (!$customerId) {
            $this->messageManager->addErrorMessage(__('Customer ID is missing.'));
            return $this->_redirect('customer/index/index');
        }

        // Register current customer ID for the grid block
        $this->coreRegistry->register(RegistryConstants::CURRENT_CUSTOMER_ID, $customerId);

        /** @var \Magento\Framework\View\Result\Layout $resultLayout */
        /** @var \Magento\Framework\Controller\Result\Raw $resultRaw */
        $resultRaw = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $resultRaw->setContents(
            $this->_view->getLayout()->createBlock(\ViraXpress\Rma\Block\Adminhtml\Customer\Edit\Tab\RmaRequests::class)
            ->setData('is_ajax', true) // optional
            ->toHtml()
        );
        return $resultRaw;
    }
}
