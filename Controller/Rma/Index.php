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
namespace ViraXpress\Rma\Controller\Rma;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\Registry;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\View\Result\Page;

/**
 * Class Index
 *
 * Renders the RMA details page for logged-in customers.
 */
class Index extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var Session
     */
    protected $customerSession;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * Index constructor.
     *
     * @param Context               $context
     * @param PageFactory           $resultPageFactory
     * @param Session               $customerSession
     * @param Registry              $registry
     */
    public function __construct(
        Context                     $context,
        PageFactory                 $resultPageFactory,
        Session                     $customerSession,
        Registry                    $registry
    ) {
        $this->resultPageFactory    = $resultPageFactory;
        $this->customerSession      = $customerSession;
        $this->registry             = $registry;
        parent::__construct($context);
    }

    /**
     * Execute method.
     *
     * Checks if the customer is logged in and renders the RMA page.
     * If not logged in, redirects to the login page.
     *
     * @return Page|Redirect
     */
    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->_redirect('customer/account/login');
        }

        // Register customer ID to be used in blocks or other components
        $this->registry->register('rma_details_customer_id', $this->customerSession->getCustomerId());

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__(''));

        return $resultPage;
    }
}
