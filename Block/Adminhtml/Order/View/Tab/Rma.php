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
namespace ViraXpress\Rma\Block\Adminhtml\Order\View\Tab;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Framework\Registry;
use ViraXpress\Rma\Model\RequestFactory;
use Magento\Framework\Escaper;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Class Rma
 *
 * Admin Order View tab that displays Return Merchandise Authorization (RMA) information
 * associated with the current order in the Magento admin panel.
 *
 * The block renders the `order/view/tab/rma.phtml` template, exposing helper methods
 * for retrieving the current order, formatting dates in the store’s timezone, and
 * building base URLs for viewing RMA details.
 *
 */
class Rma extends Template implements TabInterface
{
    /**
     * Relative path to the template used to render the tab.
     *
     * @var string
     */
    protected $_template = 'order/view/tab/order_rma.phtml';

    /**
     * Core registry instance for accessing global data (e.g. current order).
     *
     * @var Registry
     */
    private $_coreRegistry;

    /**
     * Factory for loading individual RMA requests.
     *
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * HTML escaper utility.
     *
     * @var Escaper
     */
    protected $escaper;

    /**
     * Magento date‑time service that respects the store’s configured timezone.
     *
     * @var TimezoneInterface
     */
    protected $timezone;

    /**
     * Rma constructor.
     *
     * @param Template\Context   $context
     * @param Registry           $registry
     * @param RequestFactory     $requestFactory
     * @param Escaper            $escaper
     * @param TimezoneInterface  $timezone
     * @param array              $data
     */
    public function __construct(
        Template\Context        $context,
        Registry                $registry,
        RequestFactory          $requestFactory,
        Escaper                 $escaper,
        TimezoneInterface       $timezone,
        array                   $data = []
    ) {
        $this->_coreRegistry    = $registry;
        $this->requestFactory   = $requestFactory;
        $this->escaper          = $escaper;
        $this->timezone         = $timezone;
        parent::__construct($context, $data);
    }

    /**
     * Retrieve the current order instance from the registry.
     *
     * @return \Magento\Sales\Model\Order|null  The current order or null if not set.
     */
    public function getOrder()
    {
        return $this->_coreRegistry->registry('current_order');
    }

    /**
     * Get the current order’s entity ID.
     *
     * @return int|null  Order entity ID or null if the order is not loaded.
     */
    public function getOrderId()
    {
        return $this->getOrder() ? $this->getOrder()->getEntityId() : null;
    }

    /**
     * Get the current order’s increment ID (human‑readable order number).
     *
     * @return string|null  Increment ID or null if the order is not loaded.
     */
    public function getOrderIncrementId()
    {
        return $this->getOrder() ? $this->getOrder()->getIncrementId() : null;
    }

    /**
     * @inheritdoc
     */
    public function getTabLabel()
    {
        return __('Return Information');
    }

    /**
     * @inheritdoc
     */
    public function getTabTitle()
    {
        return __('Return Information');
    }

    /**
     * @inheritdoc
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function isHidden()
    {
        return false;
    }

    /**
     * Get the store‑configured timezone service.
     *
     * @return TimezoneInterface
     */
    public function getTimezone(): TimezoneInterface
    {
        return $this->timezone;
    }

    /**
     * Convert a UTC date‑time string to the store’s local timezone and format it.
     *
     * @param  string $utcDatetime  Datetime in UTC (e.g. "2025‑06‑18 10:20:00").
     * @return string               Formatted local datetime (Y‑m‑d H:i:s).
     */
    public function formatUtcDate(string $utcDatetime): string
    {
        $localDate = $this->getTimezone()->date(new \DateTime($utcDatetime));
        return $localDate->format('Y-m-d H:i:s');
    }

    /**
     * Build a base admin URL for viewing a specific RMA.
     *
     * Replace the placeholder `__rma_id__` with an actual RMA ID when generating
     * the link in your template or JavaScript.
     *
     * @return string  URL pattern containing the `__rma_id__` placeholder.
     */
    public function getRmaViewBaseUrl(): string
    {
        return $this->getUrl('vx_rma/rma/view', ['rma_id' => '__rma_id__']);
    }
    /**
     * Retrieve the HTML for the RMA Requests grid block in the Order View tab.
     *
     * @return string The rendered HTML of the RMA Requests grid.
     */
    public function getGridHtml()
    {
        return $this->getLayout()->
        createBlock(\ViraXpress\Rma\Block\Adminhtml\Order\View\Tab\RmaRequests::class)->toHtml();
    }
}
