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
namespace ViraXpress\Rma\Controller\Adminhtml\Rma;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;
use ViraXpress\Rma\Model\ItemInspectionFactory;
use ViraXpress\Rma\Model\ItemFactory;

/**
 * Class MoveToInspection
 * Handles the process of moving an RMA item into inspection.
 */
class MoveToInspection extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var ItemInspectionFactory
     */
    protected $inspectionFactory;

    /** @var ItemFactory */
    protected ItemFactory $itemFactory;
    /**
     * Constructor
     *
     * @param Context               $context
     * @param JsonFactory           $resultJsonFactory
     * @param ItemInspectionFactory $inspectionFactory
     * @param ItemFactory           $itemFactory
     */
    public function __construct(
        Context                 $context,
        JsonFactory             $resultJsonFactory,
        ItemInspectionFactory   $inspectionFactory,
        ItemFactory             $itemFactory
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->inspectionFactory = $inspectionFactory;
        $this->itemFactory       = $itemFactory;
        parent::__construct($context);
    }

    /**
     * Executes the action to move an item to inspection.
     *
     * @return Json
     */
    public function execute()
    {
        /** @var Json $result */
        $result = $this->resultJsonFactory->create();
        $params = $this->getRequest()->getParams();

        try {
            $inspection = $this->inspectionFactory->create();
            $inspection->setData([
                'rma_id' => $params['rma_id'] ?? null,
                'item_id' => $params['item_id'] ?? null,
                'sku' => $params['sku'] ?? null,
                'product_id' => $params['product_id'] ?? null,
                'qty_received' => $params['qty'] ?? null,
                'condition' => $params['condition'] ?? null,
            ]);
            $inspection->save();

            $item = $this->itemFactory->create()->load($params['item_id']);
            if (!$item->getId()) {
                throw new NoSuchEntityException(__('RMA item not found.'));
            }
            $item->setIsMovedToInspection(1)->save();

            return $result->setData(['success' => true]);
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
