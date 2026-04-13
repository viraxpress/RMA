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
namespace ViraXpress\Rma\Block;

use Magento\Framework\View\Element\Template;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Registry;
use ViraXpress\Rma\Model\RequestFactory;
use ViraXpress\Rma\Model\ItemFactory;
use ViraXpress\Rma\Model\ItemImageFactory;
use ViraXpress\Rma\Model\ResourceModel\Request\Collection as RequestCollection;
use ViraXpress\Rma\Model\ResourceModel\Item\Collection as ItemCollection;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Data\Form\FormKey;

/**
 * Class RmaDetails
 *
 * Block class to retrieve RMA (Return Merchandise Authorization) data
 * for the current or registered customer.
 */
class RmaDetails extends Template
{
    /**
     * RequestFactory instance to create RMA request models.
     *
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * ItemFactory instance to create RMA item models.
     *
     * @var ItemFactory
     */
    protected $itemFactory;

    /**
     * Customer session model for current logged in customer.
     *
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * Registry instance to access registry values.
     *
     * @var Registry
     */
    protected $registry;
    /**
     * OrderRepository for Orders.
     *
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * ItemImageFactory for images.
     *
     * @var ItemImageFactory
     */
    protected $itemImageFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var TimezoneInterface
     */
    protected $timezone;

    /**
     * @var FromKey
     */
    protected $formKey;

    /**
     * RmaDetails constructor.
     *
     * @param Template\Context              $context
     * @param RequestFactory                $requestFactory
     * @param ItemFactory                   $itemFactory
     * @param CustomerSession               $customerSession
     * @param Registry                      $registry
     * @param OrderRepositoryInterface      $orderRepository
     * @param ItemImageFactory              $itemImageFactory
     * @param StoreManagerInterface         $storeManager
     * @param TimezoneInterface             $timezone
     * @param FormKey                       $formKey
     * @param array                         $data
     */
    public function __construct(
        Template\Context                    $context,
        RequestFactory                      $requestFactory,
        ItemFactory                         $itemFactory,
        CustomerSession                     $customerSession,
        Registry                            $registry,
        OrderRepositoryInterface            $orderRepository,
        ItemImageFactory                    $itemImageFactory,
        StoreManagerInterface               $storeManager,
        TimezoneInterface                   $timezone,
        FormKey                             $formKey,
        array                               $data = []
    ) {
        $this->requestFactory               = $requestFactory;
        $this->itemFactory                  = $itemFactory;
        $this->customerSession              = $customerSession;
        $this->registry                     = $registry;
        $this->orderRepository              = $orderRepository;
        $this->itemImageFactory             = $itemImageFactory;
        $this->storeManager                 = $storeManager;
        $this->timezone                     = $timezone;
        $this->formKey                      = $formKey;
        parent::__construct($context, $data);
    }
    /**
     * Get the Form Key.
     *
     * @return string
     */
    public function getFormKey()
    {
        return $this->formKey->getFormKey();
    }

    /**
     * Get the current customer ID.
     * Priority is given to the registry value 'rma_details_customer_id'.
     * Returns null if no customer ID is found.
     *
     * @return int|null Customer ID or null if not set
     */
    public function getCustomerId()
    {
        return $this->registry->registry('rma_details_customer_id');
    }

    /**
     * Retrieve all RMA requests associated with the current customer.
     *
     * @return RequestCollection Collection of RMA requests filtered by customer ID
     */
    public function getRmaData()
    {
        $customerId = $this->getCustomerId();
        $collection = $this->requestFactory->create()->getCollection()
            ->addFieldToFilter('customer_id', $customerId ?: 0)
            ->setOrder('created_at', 'DESC');

        return $collection;
    }

    /**
     * Retrieve all RMA items associated with a specific RMA request ID.
     *
     * @param int $rmaId RMA request ID
     * @return ItemCollection Collection of RMA items filtered by RMA ID
     */
    public function getRmaItemData($rmaId)
    {
        return $this->itemFactory->create()->getCollection()
            ->addFieldToFilter('rma_id', $rmaId);
    }
    /**
     * Retrieve images associated with a specific RMA item.
     *
     * @param int $itemId
     * @return \ViraXpress\Rma\Model\ResourceModel\ItemImage\Collection
     */
    public function getItemImages($itemId)
    {
        return $this->itemImageFactory->create()->getCollection()
            ->addFieldToFilter('item_id', $itemId);
    }

    /**
     * Retrieve the order by ID.
     *
     * @param int $orderId
     * @return \Magento\Sales\Api\Data\OrderInterface|null
     */
    public function getOrder($orderId)
    {
        try {
            return $this->orderRepository->get($orderId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            // Handle case where the order does not exist
            return null;
        }
    }
    
    /**
     * Get StoreManager instance
     *
     * @return \Magento\Store\Model\StoreManagerInterface
     */
    public function getStoreManager()
    {
        
        return $this->storeManager;
    }
    /**
     * Get the base URL of the current store
     *
     * @return string
     */
    public function getImageBaseUrl()
    {
        $storeManager = \Magento\Framework\App\ObjectManager::getInstance()
        ->get(\Magento\Store\Model\StoreManagerInterface::class);
        $mediaBaseUrl = $storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        return $mediaBaseUrl;
    }

    /**
     * Get the current store's timezone object
     *
     * @return TimezoneInterface
     */
    public function getTimezone(): TimezoneInterface
    {
        return $this->timezone;
    }

    /**
     * Convert UTC datetime string to store-local formatted datetime.
     *
     * @param string $utcDatetime
     * @return string
     */
    public function formatUtcDate(string $utcDatetime): string
    {
        $timezone=$this->getTimezone();
        $localDate = $timezone->date(new \DateTime($utcDatetime));
        return $localDate->format('Y-m-d H:i:s');
    }

    /**
     * Compile RMA and related order data into a structured array format.
     *
     * This method retrieves all RMA requests, their associated order data, and
     * relevant item details including product information, requested quantity,
     * reason, resolution, and uploaded images. Image URLs are constructed using
     * the media base path.
     *
     * The resulting array structure includes:
     * - RMA ID, Order Increment ID, RMA status, created/updated timestamps
     * - Associated items with name, SKU, price, quantity, reason, resolution,
     *   individual item status, RMA status, and image URLs.
     *
     * @return array The compiled RMA data with all related order and item details.
     */
    public function getCompiledData()
    {
        $rmaData = $this->getRmaData();
        $mediaBaseUrl = $this->getImageBaseUrl();
        $compiledData = [];
        foreach ($rmaData as $rma) {
            $rmaId = $rma->getRmaId();
            $orderId = $rma->getOrderId();
            $rmaItems = $this->getRmaItemData($rmaId);
            $order = $this->getOrder($orderId);
            $orderIncrementId=$order->getIncrementId();

            $orderItems = [];
            foreach ($order->getAllItems() as $item) {
                $orderItems[$item->getProductId()] = $item;
            }

            $items = [];
            foreach ($rmaItems as $item) {
                $productId = $item->getProductId();
                $orderItem = $orderItems[$productId] ?? null;

                $images = $this->getItemImages($item->getId())->getItems();
                $imageUrls = array_values(array_map(function ($image) use ($mediaBaseUrl) {
                    return $mediaBaseUrl . 'rma/uploads/' . $image->getImageName();
                }, $images));

                $items[] = [
                    'item_id'     => $item->getItemId(),
                    'name'        => $orderItem ? $orderItem->getName() : 'N/A',
                    'sku'         => $orderItem ? $orderItem->getSku() : 'N/A',
                    'price'       => (float) ($orderItem ? $orderItem->getPrice() : 0),
                    'qty'         => (int) $item->getQtyRequested(),
                    'reason'      => $item->getReason(),
                    'resolution'  => $item->getResolution(),
                    'status'      => $item->getStatus(),
                    'rma_status'  => $rma->getStatus(),
                    'images'      => $imageUrls

                ];
            }

            $compiledData[] = [
                'rma_id'    => $rmaId,
                'order_id'  => $orderIncrementId,
                'status'    => $rma->getStatus(),
                'created_at'=> $this->formatUtcDate($rma->getCreatedAt()),
                'updated_at'=> $this->formatUtcDate($rma->getUpdatedAt()),
                'items'     => $items
            ];
        }
        return $compiledData;
    }

    /**
     * Safely encode data into JSON format with HTML-sensitive characters escaped.
     *
     * This method uses `json_encode` with flags to escape:
     * - HTML tags (`<`, `>`)
     * - Ampersands (`&`)
     * - Apostrophes (`'`)
     * - Double quotes (`"`)
     *
     * This prevents potential XSS vulnerabilities when embedding JSON in HTML.
     *
     * @param mixed $data The data to be encoded to JSON.
     *
     * @return string The safely encoded JSON string.
     */
    public function safeJson($data)
    {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}
