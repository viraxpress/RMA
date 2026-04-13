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
declare(strict_types=1);

namespace ViraXpress\Rma\Model\Request;

use ViraXpress\Rma\Model\ResourceModel\Request\CollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\App\RequestInterface;

/**
 * Class DataProvider
 *
 * Provides data to UI components (form/grid) for RMA Requests
 */
class DataProvider extends AbstractDataProvider
{
    /**
     * @var \ViraXpress\Rma\Model\ResourceModel\Request\Collection
     */
    protected $collection;

    /**
     * @var DataPersistorInterface
     */
    protected $dataPersistor;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * Cached loaded data
     *
     * @var array|null
     */
    protected $loadedData = null;

    /**
     * DataProvider constructor.
     *
     * @param string                 $name
     * @param string                 $primaryFieldName
     * @param string                 $requestFieldName
     * @param CollectionFactory      $collectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param OrderFactory           $orderFactory
     * @param RequestInterface       $request
     * @param array                  $meta
     * @param array                  $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory            $collectionFactory,
        DataPersistorInterface       $dataPersistor,
        OrderFactory                 $orderFactory,
        RequestInterface             $request,
        array                        $meta = [],
        array                        $data = []
    ) {
        $this->collection            = $collectionFactory->create();
        $this->dataPersistor         = $dataPersistor;
        $this->orderFactory          = $orderFactory;
        $this->request               = $request;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Return data for the RMA listing/grid.
     *
     * If the current URL contains id/{customerId} (i.e. we are inside the
     * “Customer view” in Admin), the RMA collection is filtered by
     * `customer_id` and the `customer_name` column is omitted.
     *
     * @return array
     */
    public function getData(): array
    {
        // 1. Detect customer filter context
        $customerId = (int) (
            $this->request->getParam('current_customer_id')
                ?: $this->request->getParam('id')
        );

        $includeCustName = ($customerId === 0);

        if ($customerId) {
            $this->collection->addFieldToFilter('customer_id', $customerId);
        }

        // 2. Let Magento handle filtering, sorting, and pagination
        $data = parent::getData();

        // 3. Enrich each item with order details
        foreach ($data['items'] as &$item) {
            $item['increment_id'] = 'Unknown';
            if ($includeCustName) {
                $item['customer_name'] = 'Unknown';
            }

            if (!empty($item['order_id'])) {
                try {
                    $order = $this->orderFactory->create()->load($item['order_id']);
                    if ($order->getId()) {
                        $item['increment_id'] = $order->getIncrementId();
                        if ($includeCustName) {
                            $item['customer_name'] = trim(
                                $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname()
                            );
                        }
                    } else {
                        $item['increment_id'] = 'N/A';
                        if ($includeCustName) {
                            $item['customer_name'] = 'N/A';
                        }
                    }
                } catch (\Exception $e) {
                    $item['increment_id'] = 'Error';
                    if ($includeCustName) {
                        $item['customer_name'] = 'Error';
                    }
                }
            }
        }

        return $data;
    }
}
