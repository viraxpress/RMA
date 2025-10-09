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
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use ViraXpress\Rma\Model\RequestFactory;
use Magento\Email\Model\Template\SenderResolver;
use Laminas\Mail\Address;
use Laminas\Mail\AddressList;

/**
 * Controller: Update an RMA request’s status from the admin panel
 * and (optionally) send a customer notification e‑mail.
 *
 * System‑config fields used:
 *   rma_email/status_update/enabled   yes|no
 *   rma_email/status_update/template  e‑mail template identifier
 *   rma_email/new_request/sender_identity   sender identity (re‑used)
 *
 */
class UpdateStatus extends Action
{
    /* ────────────────────────────────────────────────────────────
     *  Config XPath constants
     * ──────────────────────────────────────────────────────────── */
    private const XML_ENABLED         = 'rma_email/status_update/enabled';
    private const XML_TEMPLATE        = 'rma_email/status_update/template';
    private const XML_SENDER_IDENTITY = 'rma_email/new_request/sender_identity';

    /* ────────────────────────────────────────────────────────────
     *  Injected dependencies
     * ──────────────────────────────────────────────────────────── */
    /** @var JsonFactory */
    protected $jsonFactory;

    /** @var RequestFactory */
    protected $requestFactory;

    /** @var TransportBuilder */
    protected $transportBuilder;

    /** @var StoreManagerInterface */
    protected $storeManager;

    /** @var ScopeConfigInterface */
    protected $scopeConfig;

    /** @var SenderResolver */
    protected $senderResolver;

    /**
     * DI constructor.
     *
     * @param Context                 $context
     * @param JsonFactory             $jsonFactory
     * @param RequestFactory          $requestFactory
     * @param TransportBuilder        $transportBuilder
     * @param StoreManagerInterface   $storeManager
     * @param ScopeConfigInterface    $scopeConfig
     * @param SenderResolver          $senderResolver
     */
    public function __construct(
        Context                 $context,
        JsonFactory             $jsonFactory,
        RequestFactory          $requestFactory,
        TransportBuilder        $transportBuilder,
        StoreManagerInterface   $storeManager,
        ScopeConfigInterface    $scopeConfig,
        SenderResolver          $senderResolver
    ) {
        parent::__construct($context);
        $this->jsonFactory      = $jsonFactory;
        $this->requestFactory   = $requestFactory;
        $this->transportBuilder = $transportBuilder;
        $this->storeManager     = $storeManager;
        $this->scopeConfig      = $scopeConfig;
        $this->senderResolver   = $senderResolver;
        $this->transportBuilder = $transportBuilder;
    }

    /* ────────────────────────────────────────────────────────────
     *  Main controller entry
     * ──────────────────────────────────────────────────────────── */

    /**
     * Update the RMA request status and send a notification e‑mail
     *
     * Expected request parameters:
     *   rma_id         (int)    – RMA entity ID
     *   status         (string) – new status code/label
     *   customer_email (string) – e‑mail address for notification
     *   customer_name  (string) – display name
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        $rmaId        = (int)$this->getRequest()->getParam('rma_id');
        $status       = (string)$this->getRequest()->getParam('status');
        $customerMail = (string)$this->getRequest()->getParam('customer_email');
        $customerName = (string)$this->getRequest()->getParam('customer_name');

        if (!$rmaId || $status === '') {
            return $result->setData(['success' => false, 'message' => __('Invalid input.')]);
        }

        try {
            $rma = $this->requestFactory->create()->load($rmaId);
            if (!$rma->getId()) {
                throw new LocalizedException(__('RMA not found.'));
            }

            $rma->setStatus($status)->save();

            /* Send mail only if module switch is ON and template exists */
            $storeId = (int)$this->storeManager->getStore()->getId();
            if ($this->scopeConfig->isSetFlag(self::XML_ENABLED, ScopeInterface::SCOPE_STORE, $storeId)) {
                $tpl = (string)$this->scopeConfig->getValue(self::XML_TEMPLATE, ScopeInterface::SCOPE_STORE, $storeId);
                if ($tpl && $customerMail) {
                    $this->sendStatusUpdateEmail($rma, $customerMail, $customerName, $tpl, $storeId);
                } else {
                     return $result->setData(['success' => false , 'message' => "template and customer mail not set"]);
                }
            } else {
                     return $result->setData(['success' => true , 'message' => "Status Updated successfully"]);
            }

            return $result->setData(['success' => true , 'message' => "Status Updated and Mail sent successfully"]);
        } catch (\Throwable $e) {
            return $result->setData([
                'success' => false,
                'message' => __('Error1: %1', $e->getMessage())
            ]);
        }
    }

    /* ────────────────────────────────────────────────────────────
     *  E‑mail helper
     * ──────────────────────────────────────────────────────────── */

    /**
     * Build and dispatch the status‑update e‑mail.
     *
     * @param \ViraXpress\Rma\Model\Request $rma          RMA model
     * @param string                        $email        Recipient address
     * @param string                        $name         Recipient name
     * @param string                        $templateId   Configured template identifier
     * @param int                           $storeId      Store scope
     */
    protected function sendStatusUpdateEmail(
        \ViraXpress\Rma\Model\Request $rma,
        string $email,
        string $name,
        string $templateId,
        int $storeId
    ): void {
        /* Template variables */
        $vars = [
            'customer_name' => $name ?: __('Customer'),
            'order_id'      => (string)$rma->getOrderId(),
            'status'        => $rma->getStatus(),
            'rma_id'        => $rma->getId(),
        ];
         
        /* Sender identity (re‑used from “new request” group) */
        $identityCode = (string)$this->scopeConfig->getValue(
            self::XML_SENDER_IDENTITY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'general';
        $identity = $this->senderResolver->resolve($identityCode, $storeId);
        $sender = [
            'name'  => $identity['name'],
            'email' => $identity['email'],
        ];
        $fromList = new AddressList();
        $fromList->add(new Address($identity['email'], $identity['name']));
        /* Build transport and send */
        $this->transportBuilder
            ->setTemplateIdentifier($templateId)
            ->setTemplateOptions(['area' => \Magento\Framework\App\Area::AREA_FRONTEND, 'store' => $storeId])
            ->setTemplateVars($vars)
            ->setFrom($sender)
            ->addTo($email, $name ?: __('Customer'))
            ->getTransport()
            ->sendMessage();
    }

    /* ────────────────────────────────────────────────────────────
     *  ACL check
     * ──────────────────────────────────────────────────────────── */

    /**
     * ACL resource check – users must have the “RMA config” permission.
     *
     * @return bool
     */
    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('ViraXpress_Rma::config_rma');
    }
}
