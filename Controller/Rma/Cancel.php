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
use ViraXpress\Rma\Model\RequestFactory;
use ViraXpress\Rma\Model\ItemFactory;

/**
 * Class Cancel
 *
 * Handles AJAX request to cancel an RMA by setting its status to 'Cancelled'.
 * Also cancels all RMA items under the RMA.
 */
class Cancel extends Action
{
    /**
     * @var JsonFactory
     */
    protected $jsonFactory;

    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * @var ItemFactory
     */
    protected $itemFactory;

    /**
     * Cancel constructor.
     *
     * @param Context           $context
     * @param JsonFactory       $jsonFactory
     * @param RequestFactory    $requestFactory
     * @param ItemFactory       $itemFactory
     */
    public function __construct(
        Context                 $context,
        JsonFactory             $jsonFactory,
        RequestFactory          $requestFactory,
        ItemFactory             $itemFactory
    ) {
        $this->jsonFactory      = $jsonFactory;
        $this->requestFactory   = $requestFactory;
        $this->itemFactory      = $itemFactory;
        parent::__construct($context);
    }

    /**
     * Execute method.
     *
     * Cancels the RMA request if a valid RMA ID is provided and it exists.
     * Also cancels all RMA items under the RMA.
     *
     * @return Json
     */
    public function execute()
    {
        /** @var Json $result */
        $result = $this->jsonFactory->create();
        $rmaId = $this->getRequest()->getParam('rma_id');

        if (!$rmaId) {
            return $result->setData(['success' => false, 'message' => 'Invalid RMA ID']);
        }

        try {
            $rma = $this->requestFactory->create()->load($rmaId);
            if ($rma->getId()) {
                // Cancel the RMA itself
                $rma->setStatus('Cancelled');
                $rma->save();

                // Cancel all RMA items under this RMA
                $rmaItems = $this->itemFactory->create()
                    ->getCollection()
                    ->addFieldToFilter('rma_id', $rma->getId());

                foreach ($rmaItems as $item) {
                    $item->setStatus('Cancelled');
                    $item->save();
                }

                return $result->setData([
                    'success' => true,
                    'message' => 'Return request cancelled successfully.'
                ]);
            }
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        return $result->setData([
            'success' => false,
            'message' => 'Unable to cancel RMA.'
        ]);
    }
}
