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
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Service\CreditmemoService;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Store\Model\StoreManagerInterface;
use ViraXpress\Rma\Model\RequestFactory;
use ViraXpress\Rma\Model\ItemFactory;
use ViraXpress\Rma\Model\ItemInspectionFactory;
use ViraXpress\Rma\Model\ItemInspection;

/**
 * Class Refund
 *
 * Handles RMA refund logic for a single item:
 * - Validates refund request
 * - Creates a credit memo
 * - Updates RMA status and item flags
 * - Sends refund notification e-mails to customer and optionally admin
 */
class Refund extends Action
{
    private const XML_REFUND_ENABLED         = 'rma_email/refund_mail/enabled';
    private const XML_REFUND_TEMPLATE        = 'rma_email/refund_mail/template';
    private const XML_SENDER_IDENTITY        = 'rma_email/new_request/sender_identity';
    private const XML_REFUND_SEND_TO_ADMIN   = 'rma_email/refund_mail/send_to_admin';
    private const XML_REFUND_ADMIN_EMAIL     = 'rma_email/refund_mail/admin_email';

    /** @var JsonFactory */
    private JsonFactory $resultJsonFactory;

    /** @var OrderRepositoryInterface */
    private OrderRepositoryInterface $orderRepository;

    /** @var CreditmemoService */
    private CreditmemoService $creditmemoService;

    /** @var CreditmemoFactory */
    private CreditmemoFactory $creditmemoFactory;

    /** @var Transaction */
    private Transaction $transaction;

    /** @var RequestFactory */
    private RequestFactory $requestFactory;

    /** @var ItemFactory */
    private ItemFactory $itemFactory;

    /** @var ItemInspectionFactory */
    private ItemInspectionFactory $itemInspectionFactory;

    /** @var TransportBuilder */
    private TransportBuilder $transportBuilder;

    /** @var StoreManagerInterface */
    private StoreManagerInterface $storeManager;

    /** @var ScopeConfigInterface */
    private ScopeConfigInterface $scopeConfig;

    /** @var SenderResolverInterface */
    private SenderResolverInterface $senderResolver;

    /**
     * @var ItemInspection|null
     */
    protected ?ItemInspection $inspection = null;

    /**
     * Constructor
     *
     * @param Context                   $context
     * @param OrderRepositoryInterface  $orderRepository
     * @param CreditmemoService         $creditmemoService
     * @param CreditmemoFactory         $creditmemoFactory
     * @param JsonFactory               $resultJsonFactory
     * @param Transaction               $transaction
     * @param RequestFactory            $requestFactory
     * @param TransportBuilder          $transportBuilder
     * @param StoreManagerInterface     $storeManager
     * @param ItemInspectionFactory     $itemInspectionFactory
     * @param ItemFactory               $itemFactory
     * @param ScopeConfigInterface      $scopeConfig
     * @param SenderResolverInterface   $senderResolver
     */
    public function __construct(
        Context                         $context,
        OrderRepositoryInterface        $orderRepository,
        CreditmemoService               $creditmemoService,
        CreditmemoFactory               $creditmemoFactory,
        JsonFactory                     $resultJsonFactory,
        Transaction                     $transaction,
        RequestFactory                  $requestFactory,
        TransportBuilder                $transportBuilder,
        StoreManagerInterface           $storeManager,
        ItemInspectionFactory           $itemInspectionFactory,
        ItemFactory                     $itemFactory,
        ScopeConfigInterface            $scopeConfig,
        SenderResolverInterface         $senderResolver
    ) {
        parent::__construct($context);
        $this->orderRepository          = $orderRepository;
        $this->creditmemoService        = $creditmemoService;
        $this->creditmemoFactory        = $creditmemoFactory;
        $this->resultJsonFactory        = $resultJsonFactory;
        $this->transaction              = $transaction;
        $this->requestFactory           = $requestFactory;
        $this->transportBuilder         = $transportBuilder;
        $this->storeManager             = $storeManager;
        $this->itemInspectionFactory    = $itemInspectionFactory;
        $this->itemFactory              = $itemFactory;
        $this->scopeConfig              = $scopeConfig;
        $this->senderResolver           = $senderResolver;
    }

    /**
     * Loads and caches the first ItemInspection row for the given RMA item ID.
     *
     * @param int $itemId
     * @return \ViraXpress\Rma\Model\ItemInspection
     */
    public function getInspectionData($itemId)
    {
        if ($this->inspection === null && $itemId) {
            $collection = $this->itemInspectionFactory->create()->getCollection()
                ->addFieldToFilter('item_id', $itemId);
            $this->inspection = $collection->getFirstItem();

            if (!$this->inspection->getId()) {
                return $this->inspection=null;
            }
        }

        return $this->inspection;
    }

    /**
     * Execute refund action from admin for single RMA item.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $p      = $this->getRequest()->getParams();

        try {
            $orderId   = (int)($p['order_id']   ?? 0);
            $productId = (int)($p['product_id'] ?? 0);
            $qty       = (float)($p['qty']      ?? 0);
            $rmaId     = (int)($p['rma_id']     ?? 0);
            $itemId    = (int)($p['item_id']    ?? 0);

            if (!$orderId || !$productId || !$qty || !$rmaId || !$itemId) {
                throw new LocalizedException(__('Missing required parameters.'));
            }

            $order      = $this->orderRepository->get($orderId);
            $inspection = $this->getInspectionData($itemId);
            $restock =false;

            if ($inspection) {
                $restock =$inspection->getRestockable()=="Yes";
            }
            if (!$order->canCreditmemo()) {
                throw new LocalizedException(__('Cannot create credit memo for this order.'));
            }

            $qtys = [];
            foreach ($order->getAllItems() as $orderItem) {
                if ((int)$orderItem->getProductId() === $productId) {
                    $qtys[$orderItem->getId()] = $qty;
                    break;
                }
            }

            if (!$qtys) {
                throw new LocalizedException(__('Matching order item not found.'));
            }

            $creditmemo = $this->creditmemoFactory->createByOrder($order, ['qtys' => $qtys]);
            if (!$creditmemo->getGrandTotal()) {
                throw new LocalizedException(__("The credit memo's total must be greater than zero."));
            }

            if ($restock) {
                foreach ($creditmemo->getAllItems() as $cmItem) {
                    $cmItem->setBackToStock(true);
                }
            }

            $creditmemo->setOfflineRequested(true);
            $this->creditmemoService->refund($creditmemo);
            $this->transaction->addObject($creditmemo)->save();

            $rma = $this->requestFactory->create()->load($rmaId);
            if (!$rma->getId()) {
                throw new LocalizedException(__('RMA not found.'));
            }

            $rmaItem = $this->itemFactory->create()->load($itemId);
            if (!$rmaItem->getId()) {
                throw new LocalizedException(__('RMA item not found.'));
            }
            $rmaItem->setIsReturned(1)->save();

            $storeId = (int)$this->storeManager->getStore()->getId();
            if ($this->scopeConfig->isSetFlag(self::XML_REFUND_ENABLED, ScopeInterface::SCOPE_STORE, $storeId)) {
                $this->sendRefundEmail($order, $rma, $creditmemo, $storeId);
            }

            return $result->setData(['success' => true, 'message' => __('Refund processed successfully.')]);
        } catch (\Throwable $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Send customer and optional admin refund notification e‑mails.
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \ViraXpress\Rma\Model\Request $rma
     * @param \Magento\Sales\Model\Order\Creditmemo $creditmemo
     * @param int $storeId
     * @return void
     */
    private function sendRefundEmail(
        \Magento\Sales\Model\Order $order,
        \ViraXpress\Rma\Model\Request $rma,
        \Magento\Sales\Model\Order\Creditmemo $creditmemo,
        int $storeId
    ): void {
        $vars = [
            'order'         => $order,
            'rma'           => $rma,
            'creditmemo'    => $creditmemo,
            'order_id'      => $order->getIncrementId(),
            'customer_name' => $order->getCustomerName(),
        ];

        $identityCode = (string)$this->scopeConfig->getValue(
            self::XML_SENDER_IDENTITY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'general';
        $sender = $this->senderResolver->resolve($identityCode, $storeId);

        $tplCustomer = (string)$this->scopeConfig->getValue(
            self::XML_REFUND_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $customerEmail = (string)$order->getCustomerEmail();

        if ($tplCustomer && $customerEmail) {
            $this->transportBuilder
                ->setTemplateIdentifier($tplCustomer)
                ->setTemplateOptions([
                    'area'  => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $storeId
                ])
                ->setTemplateVars($vars)
                ->setFromByScope($sender, $storeId)
                ->addTo($customerEmail, (string)$order->getCustomerName())
                ->getTransport()
                ->sendMessage();
        }

        if ($this->scopeConfig->isSetFlag(self::XML_REFUND_SEND_TO_ADMIN, ScopeInterface::SCOPE_STORE, $storeId)) {
            $adminEmail = (string)$this->scopeConfig
            ->getValue(self::XML_REFUND_ADMIN_EMAIL, ScopeInterface::SCOPE_STORE, $storeId);
            if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $tplAdmin = (string)$this->scopeConfig
                ->getValue(self::XML_REFUND_TEMPLATE, ScopeInterface::SCOPE_STORE, $storeId) ?: $tplCustomer;

                $adminTransport = $this->transportBuilder
                    ->setTemplateIdentifier($tplAdmin ?: 'rma_replacement_email_template')
                    ->setTemplateOptions([
                        'area'  => \Magento\Framework\App\Area::AREA_FRONTEND,
                        'store' => $storeId
                    ])
                    ->setTemplateVars($vars)
                    ->setFromByScope($sender, $storeId);

                $adminTransport->getTransport()->sendMessage();
            }
        }
    }
}
