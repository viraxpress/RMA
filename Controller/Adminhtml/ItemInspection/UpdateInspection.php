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
namespace ViraXpress\Rma\Controller\Adminhtml\ItemInspection;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use ViraXpress\Rma\Model\ItemInspectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Controller for updating an item inspection record via AJAX in the admin panel.
 *
 * Handles loading an existing inspection by ID, updating its fields from the request,
 * saving the changes, and returning a JSON response indicating success or failure.
 */
class UpdateInspection extends Action
{
    /**
     * @var JsonFactory
     */
    protected $jsonFactory;

    /**
     * @var ItemInspectionFactory
     */
    protected $itemInspectionFactory;

    /**
     * UpdateInspection constructor.
     *
     * @param Action\Context         $context
     * @param JsonFactory            $jsonFactory
     * @param ItemInspectionFactory  $itemInspectionFactory
     */
    public function __construct(
        Action\Context               $context,
        JsonFactory                  $jsonFactory,
        ItemInspectionFactory        $itemInspectionFactory
    ) {
        parent::__construct($context);
        $this->jsonFactory           = $jsonFactory;
        $this->itemInspectionFactory = $itemInspectionFactory;
    }

    /**
     * Execute method to update an inspection.
     *
     * @return \Magento\Framework\Controller\Result\Json JSON with keys:
     *  - success (bool): true on success, false on error
     *  - message (string): error message on failure
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();
        $request = $this->getRequest();

        $itemId = (int)$request->getParam('inspection_id');

        if (!$itemId) {
            return $result->setData(['success' => false, 'message' => 'Missing Inspection ID']);
        }

        try {
            $inspection = $this->itemInspectionFactory->create()->load($itemId);
            if (!$inspection->getId()) {
                throw new NoSuchEntityException(__('Inspection not found'));
            }

            $inspection->setInspectedBy($request->getParam('inspected_by'));
            $inspection->setInspectedAt($request->getParam('inspected_at'));
            $inspection->setTestResults($request->getParam('test_results'));
            $inspection->setActionTaken($request->getParam('action_taken'));
            $inspection->setRestockable($request->getParam('restock'));
            $inspection->setInspectionStatus($request->getParam('inspection_status'));
            $inspection->setRefurbishedNotes($request->getParam('refurbished_notes'));
            $inspection->setScrapReason($request->getParam('scrap_reason'));
            $inspection->save();
            return $result->setData(['success' => true]);
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Check if the current admin user has permission to access this controller.
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('ViraXpress_Rma::config_rma');
    }
}
