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

namespace ViraXpress\Rma\Controller\Adminhtml\Rma;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\DB\Transaction;
use Magento\Framework\DataObject;
use ViraXpress\Rma\Mail\TransportBuilderFactory as TransportBuilder;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Service\CreditmemoService;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use ViraXpress\Rma\Model\RequestFactory;
use ViraXpress\Rma\Model\ItemInspectionFactory;
use ViraXpress\Rma\Model\ItemFactory;
use ViraXpress\Rma\Model\ItemInspection;

/**
 * Controller that:
 *  • Creates a *replacement* order for a returned RMA item
 *  • Generates a credit‑memo on the original order
 *  • Marks RMA request & item as completed / replaced
 *  • Sends a configurable “replacement” e‑mail (customer + optional admin copy)
 *
 * E‑mail settings (system.xml):
 *   rma_email/replacement_mail/enabled         yes|no
 *   rma_email/replacement_mail/template        email template identifier
 *   rma_email/replacement_mail/send_to_admin   yes|no           (optional)
 *   rma_email/replacement_mail/admin_email     address          (optional)
 *   rma_email/replacement_mail/copy_method     cc | bcc | ""    (optional)
 *   rma_email/new_request/sender_identity      sender identity (re‑used)
 *
 */
class Replacement extends Action
{
    /* ═════════════════════════════════════════════════════════════
     *  Config XPath constants
     * ═════════════════════════════════════════════════════════════ */
    private const XML_REPLACEMENT_ENABLED        = 'rma_email/replacement_mail/enabled';
    private const XML_REPLACEMENT_TEMPLATE       = 'rma_email/replacement_mail/template';
    private const XML_REPLACEMENT_SEND_TO_ADMIN  = 'rma_email/replacement_mail/send_to_admin';
    private const XML_REPLACEMENT_ADMIN_EMAIL    = 'rma_email/replacement_mail/admin_email';
    private const XML_REPLACEMENT_COPY_METHOD    = 'rma_email/replacement_mail/copy_method';
    private const XML_SENDER_IDENTITY            = 'rma_email/new_request/sender_identity';

    /* ═════════════════════════════════════════════════════════════
     *  Injected services & factories
     * ═════════════════════════════════════════════════════════════ */
    /** @var JsonFactory */
    private JsonFactory $jsonFactory;

    /** @var OrderRepositoryInterface */
    private OrderRepositoryInterface $orderRepository;

    /** @var CreditmemoService */
    private CreditmemoService $creditmemoService;

    /** @var CreditmemoFactory */
    private CreditmemoFactory $creditmemoFactory;

    /** @var Transaction */
    private Transaction $transaction;

    /** @var UrlInterface */
    private UrlInterface $urlBuilder;

    /** @var QuoteFactory */
    private QuoteFactory $quoteFactory;

    /** @var CartManagementInterface */
    private CartManagementInterface $cartManagement;

    /** @var CartRepositoryInterface */
    private CartRepositoryInterface $cartRepository;

    /** @var CustomerRepositoryInterface */
    private CustomerRepositoryInterface $customerRepository;

    /** @var ProductRepositoryInterface */
    private ProductRepositoryInterface $productRepository;

    /** @var StoreManagerInterface */
    private StoreManagerInterface $storeManager;

    /** @var RequestFactory */
    private RequestFactory $requestFactory;

    /** @var ItemInspectionFactory */
    private ItemInspectionFactory $itemInspectionFactory;

    /** @var ItemFactory */
    private ItemFactory $itemFactory;

    /** @var TransportBuilder */
    private TransportBuilder $transportBuilder;
    
    /** @var ScopeConfigInterface */
    private ScopeConfigInterface $scopeConfig;

    /** @var SenderResolverInterface */
    private SenderResolverInterface $senderResolver;

    /**
     * @var ItemInspection|null
     */
    protected ?ItemInspection $inspection = null;

    /**
     * DI constructor.
     *
     * @param Context                       $context
     * @param OrderRepositoryInterface      $orderRepository
     * @param CreditmemoService             $creditmemoService
     * @param CreditmemoFactory             $creditmemoFactory
     * @param JsonFactory                   $jsonFactory
     * @param Transaction                   $transaction
     * @param UrlInterface                  $urlBuilder
     * @param QuoteFactory                  $quoteFactory
     * @param CartManagementInterface       $cartManagement
     * @param CartRepositoryInterface       $cartRepository
     * @param CustomerRepositoryInterface   $customerRepository
     * @param ProductRepositoryInterface    $productRepository
     * @param StoreManagerInterface         $storeManager
     * @param RequestFactory                $requestFactory
     * @param TransportBuilder              $transportBuilder
     * @param ItemInspectionFactory         $itemInspectionFactory
     * @param ItemFactory                   $itemFactory
     * @param ScopeConfigInterface          $scopeConfig
     * @param SenderResolverInterface       $senderResolver
     */
    public function __construct(
        Context                             $context,
        OrderRepositoryInterface            $orderRepository,
        CreditmemoService                   $creditmemoService,
        CreditmemoFactory                   $creditmemoFactory,
        JsonFactory                         $jsonFactory,
        Transaction                         $transaction,
        UrlInterface                        $urlBuilder,
        QuoteFactory                        $quoteFactory,
        CartManagementInterface             $cartManagement,
        CartRepositoryInterface             $cartRepository,
        CustomerRepositoryInterface         $customerRepository,
        ProductRepositoryInterface          $productRepository,
        StoreManagerInterface               $storeManager,
        RequestFactory                      $requestFactory,
        TransportBuilder                    $transportBuilder,
        ItemInspectionFactory               $itemInspectionFactory,
        ItemFactory                         $itemFactory,
        ScopeConfigInterface                $scopeConfig,
        SenderResolverInterface             $senderResolver
    ) {
        parent::__construct($context);

        // set DI props
        $this->orderRepository              = $orderRepository;
        $this->creditmemoService            = $creditmemoService;
        $this->creditmemoFactory            = $creditmemoFactory;
        $this->jsonFactory                  = $jsonFactory;
        $this->transaction                  = $transaction;
        $this->urlBuilder                   = $urlBuilder;
        $this->quoteFactory                 = $quoteFactory;
        $this->cartManagement               = $cartManagement;
        $this->cartRepository               = $cartRepository;
        $this->customerRepository           = $customerRepository;
        $this->productRepository            = $productRepository;
        $this->storeManager                 = $storeManager;
        $this->requestFactory               = $requestFactory;
        $this->transportBuilder             = $transportBuilder;
        $this->itemInspectionFactory        = $itemInspectionFactory;
        $this->itemFactory                  = $itemFactory;
        $this->scopeConfig                  = $scopeConfig;
        $this->senderResolver               = $senderResolver;
    }

    /* ═════════════════════════════════════════════════════════════
     *  Private helpers
     * ═════════════════════════════════════════════════════════════ */

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

    /* ═════════════════════════════════════════════════════════════
     *  Public controller entry
     * ═════════════════════════════════════════════════════════════ */

    /**
     * Process a replacement request coming from the admin UI.
     *
     * Workflow:
     *   1.  Validate parameters
     *   2.  Build quote mirroring the original order item
     *   3.  Submit quote → replacement order
     *   4.  Create credit‑memo on original order
     *   5.  Update RMA status / item flags
     *   6.  Send replacement e‑mail if enabled
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result   = $this->jsonFactory->create();
        $debugLog = '[RMA Replacement] start' . PHP_EOL;

        try {
            // ── 1. parameters ───────────────────────────────────────────────
            $p          = $this->getRequest()->getParams();
            $orderId    = (int)($p['order_id']   ?? 0);
            $productId  = (int)($p['product_id'] ?? 0);
            $qty        = (float)($p['qty']      ?? 0);
            $rmaId      = (int)($p['rma_id']     ?? 0);
            $itemId     = (int)($p['item_id']    ?? 0);

            if (!$orderId || !$productId || !$qty || !$rmaId) {
                throw new LocalizedException(__('Missing required parameters.'));
            }

            // ── 2. load entities ────────────────────────────────────────────
            $order   = $this->orderRepository->get($orderId);
            $inspection = $this->getInspectionData($itemId);
            $restock =false;

            if ($inspection) {
                $restock =$inspection->getRestockable()=="Yes";
            }

            if (!$order->canCreditmemo()) {
                throw new LocalizedException(__('Cannot create credit memo for this order.'));
            }

            // find original parent item (exclude children of configurables)
            $origItem = null;
            foreach ($order->getAllItems() as $it) {
                if ((int)$it->getProductId() === $productId && !$it->getParentItemId()) {
                    $origItem = $it;
                    break;
                }
            }
            if (!$origItem) {
                throw new LocalizedException(__('Matching order item not found.'));
            }

            // ── 3. build quote for replacement ─────────────────────────────
            $storeId = $order->getStoreId();
            $store = $this->storeManager->getStore($storeId);

            $quote = $this->quoteFactory->create();
            $quote->setStore($store);
            $quote->setStoreId($storeId);
            $quote->setCustomerId($order->getCustomerId());

            // customer
            $customerId = $order->getCustomerId();
            if ($customerId) {
                $customer = $this->customerRepository->getById($customerId);
                $quote->assignCustomer($customer);
            } else {
                $quote->setCustomerIsGuest(true)
                    ->setCustomerEmail($order->getCustomerEmail())
                    ->setCustomerFirstname($order->getCustomerFirstname())
                    ->setCustomerLastname($order->getCustomerLastname());
            }

            // product + options
            $product    = $this->productRepository->getById($productId);
            $buyRequest = ['qty' => $qty];
            $buyOrig    = $origItem->getProductOptions()['info_buyRequest'] ?? [];

            if ($product->getTypeId() === 'configurable' && !empty($buyOrig['super_attribute'])) {
                $buyRequest['super_attribute'] = $buyOrig['super_attribute'];
            }
            if (!empty($buyOrig['options'])) {
                $buyRequest['options'] = $buyOrig['options'];
            }

            $quote->addProduct($product, new DataObject($buyRequest));

            // addresses
            $copy = static function ($addr) {
                $d=$addr->getData();
                unset($d['entity_id'], $d['parent_id'], $d['customer_address_id'], $d['address_type']);
                return $d;
            };
            if ($ba = $order->getBillingAddress()) {
                $quote->getBillingAddress()->addData($copy($ba));
            }
            if ($sa = $order->getShippingAddress()) {
                $quote->getShippingAddress()->addData($copy($sa));
            }

            // shipping
            $shipMethod = $order->getShippingMethod() ?: 'flatrate_flatrate';
            $shipAddr   = $quote->getShippingAddress()->setCollectShippingRates(true)->collectShippingRates();
            $codes      = array_map(fn($r)=>$r->getCode(), $shipAddr->getAllShippingRates());
            $shipAddr->setShippingMethod(in_array($shipMethod, $codes)?$shipMethod:'flatrate_flatrate');

            // payment
            $quote->getPayment()->setMethod('free');
            $quote->collectTotals();
            $this->cartRepository->save($quote);

            // ── 4. place replacement order ─────────────────────────────────
            $replacement = $this->cartManagement->submit($quote);
            if (!$replacement) {
                throw new LocalizedException(__('Failed to create replacement order.'));
            }

            // ── 5. link orders / comments / RMA flags ──────────────────────
            $origUrl = $this->urlBuilder->getUrl('sales/order/view', ['order_id'=>$orderId]);
            $replUrl = $this->urlBuilder->getUrl('sales/order/view', ['order_id'=>$replacement->getId()]);
            $rmaUrl  = $this->urlBuilder->getUrl('vx_rma/rma/view', ['rma_id'=>$rmaId]);

            $replacement->addStatusHistoryComment(__(
                'Replacement Order for <a href="%1">#%2</a> | RMA <a href="%3">#%4</a>',
                $origUrl,
                $order->getIncrementId(),
                $rmaUrl,
                $rmaId
            ))->setIsVisibleOnFront(false)->save();

            $order->addStatusHistoryComment(__(
                'Created replacement Order <a href="%1">#%2</a> | RMA <a href="%3">#%4</a>',
                $replUrl,
                $replacement->getIncrementId(),
                $rmaUrl,
                $rmaId
            ))->setIsVisibleOnFront(false)->save();

            $rma = $this->requestFactory->create()->load($rmaId);
            if (!$rma->getId()) {
                throw new NoSuchEntityException(__('RMA not found'));
            }

            $rmaItem = $this->itemFactory->create()->load($itemId);
            if (!$rmaItem->getId()) {
                throw new LocalizedException(__('RMA item not found.'));
            }
            $rmaItem->setReplacementOrderId($replacement->getId())->setIsReplaced(1)->save();

            // ── 6. credit‑memo original item ──────────────────────────────
            $cm = $this->creditmemoFactory->createByOrder($order, ['qtys'=>[$origItem->getId()=>$qty]]);
            if (!$cm->getGrandTotal()) {
                throw new LocalizedException(__("The credit memo's total must be greater than zero."));
            }
            $cm->setData('replacement_order_id', $replacement->getId());
            $cm->setData('replacement_order_increment_id', $replacement->getIncrementId());

            if ($restock) {
                foreach ($cm->getAllItems() as $i) {
                    $i->setBackToStock(true);
                }
            }
            $cm->setOfflineRequested(true);
            $this->creditmemoService->refund($cm);

            foreach ($cm->getAllItems() as $cmItem) {
                /** @var \Magento\Sales\Model\Order\Item $orderItem */
                $orderItem = $cmItem->getOrderItem();

                // Decrease qty_refunded to avoid showing "Refunded"
                $orderItem->setQtyRefunded(
                    max(0, $orderItem->getQtyRefunded() - $cmItem->getQty())
                );

                // Track as "returned" or "replaced"
                $orderItem->setQtyReturned(
                    $orderItem->getQtyReturned() + $cmItem->getQty()
                );

                // Save updated order item
                $this->orderRepository->save($order);
            }

            // ── 7. send e‑mail if enabled ─────────────────────────────────
            $sid = (int)$this->storeManager->getStore()->getId();
            if ($this->scopeConfig->isSetFlag(self::XML_REPLACEMENT_ENABLED, ScopeInterface::SCOPE_STORE, $sid)) {
                $this->sendReplacementEmail($order, $replacement, $rma, $sid);
            }

            return $result->setData([
                'success' => true,
                'replacement_order_id' => $replacement->getIncrementId(),
                'message' => __('Replacement order created successfully.')
            ]);

        } catch (\Throwable $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /* ═════════════════════════════════════════════════════════════
     *  E‑mail helper
     * ═════════════════════════════════════════════════════════════ */

    /**
     * Build and send customer/admin replacement e‑mails driven by system config.
     *
     * @param \Magento\Sales\Model\Order    $parent       Original order
     * @param \Magento\Sales\Model\Order    $replacement  Newly created replacement order
     * @param \ViraXpress\Rma\Model\Request $rma          RMA entity
     * @param int                           $storeId      Current store ID
     * @return void
     */
    private function sendReplacementEmail(
        \Magento\Sales\Model\Order    $parent,
        \Magento\Sales\Model\Order    $replacement,
        \ViraXpress\Rma\Model\Request $rma,
        int $storeId
    ): void {
        $vars = [
            'parent_order'          => $parent,
            'replacement_order'     => $replacement,
            'rma'                   => $rma,
            'customer_name'         => $parent->getCustomerName(),
            'parent_order_id'       => $parent->getIncrementId(),
            'replacement_order_id'  => $replacement->getIncrementId(),
            'parent_order_url'      => $this->urlBuilder->getUrl('sales/order/view', [
                'order_id' => $parent->getEntityId()
            ]),
            'replacement_order_url' => $this->urlBuilder->getUrl('sales/order/view', [
                'order_id' => $replacement->getEntityId()
            ]),
        ];
    
        $identity = (string)$this->scopeConfig->getValue(
            self::XML_SENDER_IDENTITY, ScopeInterface::SCOPE_STORE, $storeId
        ) ?: 'general';
        $sender = $this->senderResolver->resolve($identity, $storeId);
    
        $tpl       = (string)$this->scopeConfig->getValue(
            self::XML_REPLACEMENT_TEMPLATE, ScopeInterface::SCOPE_STORE, $storeId
        );
        $custEmail = (string)$parent->getCustomerEmail();
    
        // ── Customer email ────────────────────────────────────────────────────
        if ($tpl && $custEmail) {
            $this->transportBuilder->create()   // fresh instance
                ->setTemplateIdentifier($tpl)
                ->setTemplateOptions([
                    'area'  => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $storeId,
                ])
                ->setTemplateVars($vars)
                ->setFromByScope($sender, $storeId)
                ->addTo($custEmail, (string)$parent->getCustomerName())
                ->getTransport()
                ->sendMessage();
        }
    
        // ── Admin copy (optional) ─────────────────────────────────────────────
        if ($this->scopeConfig->isSetFlag(
            self::XML_REPLACEMENT_SEND_TO_ADMIN, ScopeInterface::SCOPE_STORE, $storeId
        )) {
            $adminEmail = (string)$this->scopeConfig->getValue(
                self::XML_REPLACEMENT_ADMIN_EMAIL, ScopeInterface::SCOPE_STORE, $storeId
            );
    
            if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $this->transportBuilder->create()   // fresh instance — no stale $to
                    ->setTemplateIdentifier($tpl ?: 'rma_replacement_email_template')
                    ->setTemplateOptions([
                        'area'  => \Magento\Framework\App\Area::AREA_FRONTEND,
                        'store' => $storeId,
                    ])
                    ->setTemplateVars($vars)
                    ->setFromByScope($sender, $storeId)
                    ->addTo($adminEmail)               // ← was missing
                    ->getTransport()
                    ->sendMessage();
            }
        }
    }
}
