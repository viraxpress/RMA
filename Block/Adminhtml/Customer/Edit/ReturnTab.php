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
namespace ViraXpress\Rma\Block\Adminhtml\Customer\Edit;

use Magento\Customer\Controller\RegistryConstants;
use Magento\Ui\Component\Layout\Tabs\TabInterface;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Framework\Registry;
use ViraXpress\Rma\Model\ResourceModel\Request\CollectionFactory;

/**
 * Class ReturnTab
 *
 * Admin customer edit tab for displaying RMA (Return Merchandise Authorization) requests.
 */
class ReturnTab extends Generic implements TabInterface
{
    /**
     * Core registry instance.
     *
     * @var Registry
     */
    protected $coreRegistry;

    /**
     * RMA Request collection factory instance.
     *
     * @var CollectionFactory
     */
    protected $requestCollectionFactory;

    /**
     * Template file for the return tab block.
     *
     * @var string
     */
    protected $_template = 'ViraXpress_Rma::customer/edit/tab/returntab.phtml';

    /**
     * Constructor.
     *
     * @param \Magento\Backend\Block\Template\Context   $context
     * @param Registry                                  $registry
     * @param \Magento\Framework\Data\FormFactory       $formFactory
     * @param CollectionFactory                         $requestCollectionFactory
     * @param array                                     $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context         $context,
        Registry                                        $registry,
        \Magento\Framework\Data\FormFactory             $formFactory,
        CollectionFactory                               $requestCollectionFactory,
        array                                           $data = []
    ) {
        $this->coreRegistry                             = $registry;
        $this->requestCollectionFactory                 = $requestCollectionFactory;
        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * Retrieve the current customer ID from registry.
     *
     * @return int|null
     */
    public function getCustomerId(): ?int
    {
        return $this->coreRegistry->registry(RegistryConstants::CURRENT_CUSTOMER_ID);
    }

    /**
     * Get tab label.
     *
     * @return \Magento\Framework\Phrase
     */
    public function getTabLabel()
    {
        return __('Return Requests');
    }

    /**
     * Get tab title.
     *
     * @return \Magento\Framework\Phrase
     */
    public function getTabTitle()
    {
        return __('Return Requests');
    }

    /**
     * Determine if the tab should be displayed.
     *
     * @return bool
     */
    public function canShowTab(): bool
    {
        return (bool) $this->getCustomerId();
    }

    /**
     * Check if the tab is hidden.
     *
     * @return bool
     */

    public function isHidden(): bool
    {
        return !$this->getCustomerId();
    }

    /**
     * Get tab CSS class.
     *
     * @return string
     */
    public function getTabClass()
    {
        return '';
    }

    /**
     * Get tab URL (not used since isAjaxLoaded() is false).
     *
     * @return string
     */
    public function getTabUrl()
    {
        return '';
    }

    /**
     * Check if the tab content is loaded via AJAX.
     *
     * @return bool
     */
    public function isAjaxLoaded(): bool
    {
        return false; // no ajax for this tab (simpler)
    }

    /**
     * Get the sort order of the tab in the tab listing.
     *
     * @return int
     */
    public function getTabSortOrder(): int
    {
        return 120;
    }

    /**
     * Render the HTML for the RMA Requests grid.
     *
     * @return string
     */
    public function getGridHtml()
    {
        return $this->getLayout()->
            createBlock(\ViraXpress\Rma\Block\Adminhtml\Customer\Edit\Tab\RmaRequests::class)->toHtml();
    }
}
