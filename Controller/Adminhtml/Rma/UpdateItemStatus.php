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

namespace ViraXpress\Rma\Controller\Adminhtml\Rma;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use ViraXpress\Rma\Model\ItemFactory;
use ViraXpress\Rma\Model\RequestFactory;

/**
 * Update the status of a **single RMA item** from the Magento admin UI.
 *
 * Typical AJAX payload (POST or GET):
 *   rma_id   – parent RMA request ID
 *   item_id  – the RMA item row being updated
 *   status   – new status string
 *
 * Behaviour
 * ──────────────────────────────────────────────────────────────
 *  • Validates that the RMA ID is present (basic guard‑rail)
 *  • Loads the item by `item_id`; throws 404‑style exception if missing
 *  • Persists the new status (`$item->setStatus()`)
 *  • Returns `{ success: true }` on success, otherwise `{ success:false, message:... }`
 *
 * ACL
 * ──────────────────────────────────────────────────────────────
 *  Requires the `ViraXpress_Rma::config_rma` permission (see `_isAllowed()`).
 *
 */
class UpdateItemStatus extends Action
{
    /* ──────────────────────────────────────────────────────────
     *  Injected services
     * ────────────────────────────────────────────────────────── */

    /** @var JsonFactory */
    private JsonFactory $jsonFactory;

    /** @var RequestFactory */
    private RequestFactory $requestFactory;

    /** @var ItemFactory */
    private ItemFactory $itemFactory;

    /* ──────────────────────────────────────────────────────────
     *  Constructor
     * ────────────────────────────────────────────────────────── */
    /**
     * @param Context        $context          Backend action context
     * @param JsonFactory    $jsonFactory      Result factory for JSON output
     * @param RequestFactory $requestFactory   Factory for RMA request models (not used here, but handy for future)
     * @param ItemFactory    $itemFactory      Factory for RMA item models
     */
    public function __construct(
        Context        $context,
        JsonFactory    $jsonFactory,
        RequestFactory $requestFactory,
        ItemFactory    $itemFactory
    ) {
        parent::__construct($context);
        $this->jsonFactory    = $jsonFactory;
        $this->requestFactory = $requestFactory;
        $this->itemFactory    = $itemFactory;
    }

    /* ──────────────────────────────────────────────────────────
     *  Main controller entry
     * ────────────────────────────────────────────────────────── */
    /**
     * Handle the AJAX request and return a JSON response.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->jsonFactory->create();

        // ── Request parameters ──────────────────────────────────────────
        $rmaId  = (int)$this->getRequest()->getParam('rma_id');
        $itemId = (int)$this->getRequest()->getParam('item_id');
        $status = (string)$this->getRequest()->getParam('status');

        if (!$rmaId) {
            return $result->setData([
                'success' => false,
                'message' => __('Invalid RMA ID.')
            ]);
        }

        try {
            // Load item and validate
            $item = $this->itemFactory->create()->load($itemId);
            if (!$item->getId()) {
                throw new NoSuchEntityException(__('RMA item not found.'));
            }

            // Update & save
            $item->setStatus($status)->save();

            $rmaId = $item->getRmaId();

            $itemCollection = $this->itemFactory->create()->getCollection()
                ->addFieldToFilter('rma_id', $rmaId);

            $allCancelled = true;
            $allRejected  = true;
            $allCompleted  = true;

            foreach ($itemCollection as $item) {
                $status = $item->getStatus();

                if ($status !== 'Cancelled') {
                    $allCancelled = false;
                }

                if ($status !== 'Return Rejected') {
                    $allRejected = false;
                }

                if ($status !== 'Completed') {
                    $allCompleted = false;
                }

                if (!$allCancelled && !$allRejected && !$allCompleted) {
                    break;
                }
            }

            $rma = $this->requestFactory->create()->load($rmaId);
            if ($rma->getId()) {
                if ($allCancelled) {
                    $rma->setStatus('Cancelled')->save();
                } elseif ($allRejected) {
                    $rma->setStatus('Rejected')->save();
                } elseif ($allCompleted) {
                    $rma->setStatus('Completed')->save();
                }
            }

            return $result->setData(['success' => true]);
        } catch (\Throwable $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /* ──────────────────────────────────────────────────────────
     *  ACL
     * ────────────────────────────────────────────────────────── */
    /**
     * Authorisation check.
     *
     * @return bool
     */
    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('ViraXpress_Rma::config_rma');
    }
}
