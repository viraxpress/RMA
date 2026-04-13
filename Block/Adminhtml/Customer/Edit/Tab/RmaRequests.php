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
namespace ViraXpress\Rma\Block\Adminhtml\Customer\Edit\Tab;

use Magento\Backend\Block\Widget\Grid\Extended;
use Magento\Framework\Registry;
use ViraXpress\Rma\Model\ResourceModel\Request\CollectionFactory;
use Magento\Customer\Controller\RegistryConstants;

/**
 * Class RmaRequests
 *
 * Admin customer edit tab block for displaying a grid of RMA requests
 * associated with the current customer.
 */
class RmaRequests extends Extended
{
    /**
     * Core registry for accessing shared data.
     *
     * @var Registry
     */
    protected $coreRegistry;

    /**
     * Factory for creating RMA request collections.
     *
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var \Magento\Backend\Helper\Data 
     */
    protected $backendHelper;

    /**
     * RmaRequests constructor.
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Backend\Helper\Data            $backendHelper
     * @param Registry                                $registry
     * @param CollectionFactory                       $collectionFactory
     * @param array                                   $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context       $context,
        \Magento\Backend\Helper\Data                  $backendHelper,
        Registry                                      $registry,
        CollectionFactory                             $collectionFactory,
        array                                         $data = []
    ) {
        $this->coreRegistry                           = $registry;
        $this->collectionFactory                      = $collectionFactory;
        parent::__construct($context, $backendHelper, $data);
    }

    /**
     * Initialize grid configuration.
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('rma_requests_grid');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);  // optional, if you want ajax loading
    }

    /**
     * Prepare the RMA request collection for the grid.
     *
     * @return $this
     */
    protected function _prepareCollection()
    {
        $customerId = (int) $this->coreRegistry->registry(RegistryConstants::CURRENT_CUSTOMER_ID);
        $collection = $this->collectionFactory->create();

        if ($customerId) {
            $collection->addFieldToFilter('customer_id', $customerId);
        }

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * Prepare grid columns.
     *
     * @return $this
     */
    protected function _prepareColumns()
    {
        $this->addColumn('rma_id', [
            'header' => __('RMA #'),
            'index' => 'rma_id',
            'type' => 'number',
            'width' => '80px',
        ]);

        $this->addColumn('order_id', [
            'header' => __('Order #'),
            'index' => 'order_id',
        ]);

        $this->addColumn('status', [
            'header' => __('Status'),
            'index' => 'status',
        ]);

        $this->addColumn('created_at', [
            'header' => __('Created At'),
            'index' => 'created_at',
            'type' => 'datetime',
        ]);

        $this->addColumn('updated_at', [
            'header' => __('Updated At'),
            'index' => 'updated_at',
            'type' => 'datetime',
        ]);

        // Optional: Add actions column
        $this->addColumn('actions', [
            'header' => __('Actions'),
            'type' => 'action',
            'getter' => 'getId',
            'actions' => [
                [
                    'caption' => __('View'),
                    'url' => ['base' => 'vx_rma/rma/view'],
                    'field' => 'rma_id'
                ]
            ],
            'filter' => false,
            'sortable' => false,
            'index' => 'rma_id',
            'header_css_class' => 'col-actions',
            'column_css_class' => 'col-actions'
        ]);

        return parent::_prepareColumns();
    }

    /**
     * Get the grid AJAX URL.
     *
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('vx_rma/customer/rmaRequestsGrid', ['_current' => true]);
    }

    /**
     * Get the row click URL.
     *
     * @param \Magento\Framework\DataObject $row
     * @return string
     */
    public function getRowUrl($row)
    {
        return $this->getUrl('vx_rma/rma/view', ['rma_id' => $row->getId()]);
    }
}
