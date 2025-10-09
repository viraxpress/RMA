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
namespace ViraXpress\Rma\Controller\Adminhtml\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

/**
 * Admin controller for the Item Inspection config page.
 */
class Index extends Action
{
    /**
     * Authorization resource for ACL check.
     */
    public const ADMIN_RESOURCE = 'Magento_Backend::content';

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * Constructor.
     *
     * @param Context       $context
     * @param PageFactory   $resultPageFactory
     */
    public function __construct(
        Context             $context,
        PageFactory         $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * Execute method to render the admin page.
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        // Set the active menu item for highlighting in admin sidebar
        $resultPage->setActiveMenu('RMA::top_level');
        // Set the page title
        $resultPage->getConfig()->getTitle()->prepend(__('Item Inspection'));
        return $resultPage;
    }
}
