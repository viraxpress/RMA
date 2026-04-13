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
namespace ViraXpress\Rma\Block\Adminhtml\Rma;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Collection;

/**
 * Admin RMA Create Form Block
 */
class CreateForm extends Template
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * Constructor
     *
     * @param Context                 $context
     * @param ScopeConfigInterface    $scopeConfig
     * @param CollectionFactory       $orderCollectionFactory
     * @param array                   $data
     */
    public function __construct(
        Context                       $context,
        ScopeConfigInterface          $scopeConfig,
        CollectionFactory             $orderCollectionFactory,
        array                         $data = []
    ) {
        $this->scopeConfig            = $scopeConfig;
        $this->orderCollectionFactory = $orderCollectionFactory;
        parent::__construct($context, $data);
    }

    /**
     * Get URL to load order items via AJAX
     *
     * @return string
     */
    public function getLoadUrl()
    {
        return $this->getUrl('vx_rma/rma/loadOrderItems');
    }

    /**
     * Get allowed RMA statuses from system configuration
     *
     * @return array
     */
    public function getRmaStatus()
    {
        $statuses = $this->scopeConfig->getValue(
            'rma/general/status',
            ScopeInterface::SCOPE_STORE
        );

        if ($statuses) {
            return array_map('trim', explode(',', $statuses));
        }

        return [];
    }
     /**
      * Get allowed order statuses.
      *
      * @return string[]
      */
    public function getStatus(): array
    {
        $statuses = $this->scopeConfig->getValue(
            'rma/eligibility/allowed_statuses',
            ScopeInterface::SCOPE_STORE
        );
        return $statuses ? explode(',', $statuses) : [];
    }
    /**
     * Get order collection based on request parameters (pagination, filters, etc.)
     *
     * @return Collection
     */
    public function getOrders()
    {
        $params = $this->getRequest()->getParams();
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 20;
        $sortField = $params['sort'] ?? 'entity_id';
        $sortDir = $params['dir'] ?? 'DESC';

        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToSelect('*');
        $collection->setPageSize($limit);
        $collection->setCurPage($page);
        $collection->setOrder($sortField, $sortDir);

        $allowedStatuses = $this->getStatus();
        if (!empty($allowedStatuses)) {
            $collection->addFieldToFilter('status', ['in' => $allowedStatuses]);
        }

        if (!empty($params['order_id'])) {
            $collection->addFieldToFilter('entity_id', ['eq' => $params['order_id']]);
        }
        if (!empty($params['increment_id'])) {
            $collection->addFieldToFilter('increment_id', ['like' => '%' . $params['increment_id'] . '%']);
        }
        if (!empty($params['email'])) {
            $collection->addFieldToFilter('customer_email', ['like' => '%' . $params['email'] . '%']);
        }

        return $collection;
    }

    /**
     * Get total number of orders (unfiltered)
     *
     * @return int
     */
    public function getTotalCount()
    {
        return $this->orderCollectionFactory->create()->getSize();
    }

    /**
     * Get URL for sorting columns in the grid
     *
     * @param string $field
     * @param array $params
     * @return string
     */
    public function getSortUrl($field, $params)
    {
        $dir = (isset($params['sort']) && $params['sort'] === $field && $params['dir'] === 'ASC') ? 'DESC' : 'ASC';
        $params['sort'] = $field;
        $params['dir'] = $dir;
        return $this->getUrl('*/*/*', ['_current' => true, '_query' => $params]);
    }
    /**
     * Returns a sort direction icon based on the current sorting field and direction.
     *
     * @param string $field       The field being evaluated for sorting.
     * @param string $sortField   The currently sorted field.
     * @param string $sortDir     The current sorting direction ('ASC' or 'DESC').
     *
     * @return string             The corresponding sort icon (' ↑' for ASC, ' ↓' for DESC, or empty string).
     */
    public function getSortIcon($field, $sortField, $sortDir)
    {
        if ($sortField !== $field) {
            return '';
        }
        return $sortDir === 'ASC' ? ' ↑' : ' ↓';
    }

    /**
     * Get base AJAX URL (can be overridden for specific use-cases)
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('*/*/*');
    }
}
