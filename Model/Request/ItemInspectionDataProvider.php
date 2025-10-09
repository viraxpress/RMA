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
declare(strict_types=1);

namespace ViraXpress\Rma\Model\Request;

use ViraXpress\Rma\Model\ResourceModel\ItemInspection\Collection as ItemInspectionCollection;
use ViraXpress\Rma\Model\ResourceModel\ItemInspection\CollectionFactory as ItemInspectionCollectionFactory;
use ViraXpress\Rma\Model\ResourceModel\Request\CollectionFactory as RequestCollectionFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * Data provider for the Item Inspection UI component (Admin panel).
 *
 * Merges inspection‑item records with related RMA request and order data so that
 * the grid can show:
 *   • Increment ID of the parent order
 *   • Customer’s full name
 *
 * The provider also injects any data persisted via the Magento
 * `DataPersistorInterface` (e.g. when a form submission fails validation) so
 * the UI can repopulate the grid with unsaved rows.
 *
 */
class ItemInspectionDataProvider extends AbstractDataProvider
{
    /**
     * Collection of inspection‑item entities.
     *
     * @var ItemInspectionCollection
     */
    protected $collection;

    /**
     * Cross‑request data persistor (for failed form submissions).
     *
     * @var DataPersistorInterface
     */
    protected DataPersistorInterface $dataPersistor;

    /**
     * Cached array returned by {@see getData()}.
     *
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $loadedData = null;

    /**
     * Factory for RMA request collections.
     *
     * @var RequestCollectionFactory
     */
    protected RequestCollectionFactory $requestCollectionFactory;

    /**
     * Factory for loading orders.
     *
     * @var OrderFactory
     */
    protected OrderFactory $orderFactory;

    /**
     * ItemInspectionDataProvider constructor.
     *
     * @param string                            $name
     * @param string                            $primaryFieldName
     * @param string                            $requestFieldName
     * @param ItemInspectionCollectionFactory   $collectionFactory
     * @param RequestCollectionFactory          $requestCollectionFactory
     * @param OrderFactory                      $orderFactory
     * @param DataPersistorInterface            $dataPersistor
     * @param array                             $meta
     * @param array                             $data
     */
    public function __construct(
        string                                  $name,
        string                                  $primaryFieldName,
        string                                  $requestFieldName,
        ItemInspectionCollectionFactory         $collectionFactory,
        RequestCollectionFactory                $requestCollectionFactory,
        OrderFactory                            $orderFactory,
        DataPersistorInterface                  $dataPersistor,
        array                                   $meta = [],
        array                                   $data = []
    ) {
        $this->collection               = $collectionFactory->create();
        $this->dataPersistor            = $dataPersistor;
        $this->requestCollectionFactory = $requestCollectionFactory;
        $this->orderFactory             = $orderFactory;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Return grid data in the format expected by UI components.
     *
     * Each row is enriched with:
     *   • `increment_id`   – order increment ID or placeholder text
     *   • `customer_name`  – “Firstname Lastname” or placeholder text
     *
     * @return array{totalRecords:int,items:array<int,array<string,mixed>>}
     */
    public function getData(): array
    {
        if ($this->loadedData !== null) {
            return [
                'totalRecords' => $this->collection->getSize(),
                'items'        => array_values($this->loadedData),
            ];
        }

        $this->loadedData = [];

        /** @var \ViraXpress\Rma\Model\ItemInspection $item */
        foreach ($this->collection->getItems() as $item) {
            $this->loadedData[$item->getId()] = $this->enrichRow($item->getData());
        }

        // Handle unsaved form data (e.g. after a failed save).
        $persistedData = $this->dataPersistor->get('rma_item_inspection');
        if (!empty($persistedData)) {
            $item = $this->collection->getNewEmptyItem();
            $item->setData($persistedData);

            $this->loadedData[] = $this->enrichRow($item->getData());
            $this->dataPersistor->clear('rma_item_inspection');
        }

        return [
            'totalRecords' => $this->collection->getSize(),
            'items'        => array_values($this->loadedData),
        ];
    }

    /**
     * Add order increment ID and customer name to a raw inspection‑item row.
     *
     * @param array $data
     *
     * @return array
     */
    private function enrichRow(array $data): array
    {
        // Default placeholders.
        $data['increment_id']  = 'Unknown';
        $data['customer_name'] = 'Unknown';

        if (empty($data['rma_id'])) {
            return $data;
        }

        // Retrieve the parent RMA request to get the order ID.
        $request = $this->requestCollectionFactory->create()
            ->addFieldToFilter('rma_id', $data['rma_id'])
            ->getFirstItem();

        $orderId = (int) $request->getData('order_id');
        if (!$orderId) {
            return $data;
        }

        try {
            /** @var Order $order */
            $order = $this->orderFactory->create()->load($orderId);
            if ($order->getId()) {
                $data['increment_id']  = (string) $order->getIncrementId();
                $data['customer_name'] = \trim(
                    ($order->getCustomerFirstname() ?? '') . ' ' . ($order->getCustomerLastname() ?? '')
                );
            } else {
                $data['increment_id']  = 'N/A';
                $data['customer_name'] = 'N/A';
            }
        } catch (\Throwable $e) { // phpcs:ignore Magento2.CodeAnalysis.EmptyCatch
            // On error we surface placeholders rather than break the grid.
            $data['increment_id']  = 'Error';
            $data['customer_name'] = 'Error';
        }

        return $data;
    }
}
