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
namespace ViraXpress\Rma\Controller\Rma;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;
use ViraXpress\Rma\Model\ItemFactory;
use ViraXpress\Rma\Model\RequestFactory;

/**
 * Class ItemCancel
 *
 * Handles AJAX request to cancel an RMA  Item by setting its status to 'Cancelled'.
 */
class ItemCancel extends Action
{
    /**
     * @var JsonFactory
     */
    protected $jsonFactory;

    /**
     * @var ItemFactory
     */
    protected $itemFactory;

    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * Cancel constructor.
     *
     * @param Context           $context
     * @param JsonFactory       $jsonFactory
     * @param ItemFactory       $itemFactory
     * @param RequestFactory    $requestFactory
     */
    public function __construct(
        Context                 $context,
        JsonFactory             $jsonFactory,
        ItemFactory             $itemFactory,
        RequestFactory          $requestFactory
    ) {
        $this->jsonFactory      = $jsonFactory;
        $this->itemFactory      = $itemFactory;
        $this->requestFactory   = $requestFactory;
        parent::__construct($context);
    }

    /**
     * Execute method.
     *
     * Cancels the RMA Item request if a valid RMA ID is provided and it exists.
     *
     * @return Json
     */
    public function execute()
    {
        /** @var Json $result */
        $result = $this->jsonFactory->create();
        $rmaItemId = $this->getRequest()->getParam('rma_item_id');

        if (!$rmaItemId) {
            return $result->setData(['success' => false, 'message' => 'Invalid RMA Item ID']);
        }

        try {
            $rmaItem = $this->itemFactory->create()->load($rmaItemId);

            if (!$rmaItem->getId()) {
                return $result->setData([
                    'success' => false,
                    'message' => 'RMA Item not found.'
                ]);
            }

            // Cancel the item
            $rmaItem->setStatus('Cancelled');
            $rmaItem->save();

            $rmaId = $rmaItem->getRmaId(); // Get parent RMA ID

            // Check all items under this RMA
            $itemCollection = $this->itemFactory->create()->getCollection()
                ->addFieldToFilter('rma_id', $rmaId);

            $allCancelled = true;
            foreach ($itemCollection as $item) {
                if ($item->getStatus() !== 'Cancelled') {
                    $allCancelled = false;
                    break;
                }
            }

            if ($allCancelled) {
                $rma = $this->requestFactory->create()->load($rmaId);
                if ($rma->getId()) {
                    $rma->setStatus('Cancelled');
                    $rma->save();
                }
            }

            return $result->setData(['success' => true]);

        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
