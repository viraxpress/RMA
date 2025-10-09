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
namespace ViraXpress\Rma\Controller\Guest;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\View\Result\Page;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class Index
 *
 * Controller for rendering the guest RMA index page.
 */
class Index extends Action
{
    /**
     * @var PageFactory
     */
    protected PageFactory $resultPageFactory;

    /** @var ScopeConfigInterface */
    protected $scopeConfig;
    /**
     * Index constructor.
     *
     * @param Context                       $context
     * @param PageFactory                   $resultPageFactory
     * @param ScopeConfigInterface          $scopeConfig
     */
    public function __construct
    (
        Context                             $context,
        PageFactory                         $resultPageFactory,
        ScopeConfigInterface                $scopeConfig,
    )
    {
        $this->resultPageFactory            = $resultPageFactory;
        $this->scopeConfig                  = $scopeConfig;
        parent::__construct($context);
    }
    /**
     * Check if RMA is enabled
     *
     * @return bool
     */
    public function isRmaEnabled()
    {
        return $this->scopeConfig->isSetFlag('rma/general/enable', ScopeInterface::SCOPE_STORE);
    }
    /**
     * Execute method
     *
     * Loads and returns the RMA guest index page.
     *
     * @return Page
     */
    public function execute(): Page
    {
        if (!$this->isRmaEnabled()) {
            return null;
        }
        return $this->resultPageFactory->create();
    }
}
