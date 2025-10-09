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

namespace ViraXpress\Rma\Controller\RmaReturn;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use ViraXpress\Rma\Model\ItemFactory;
use ViraXpress\Rma\Model\ItemImageFactory;
use ViraXpress\Rma\Model\RequestFactory;


/**
 * Controller: Handle AJAX POST “Save RMA request” from the storefront.
 *
 * Responsibilities
 *  ─────────────────────────────────────────────────────────────────────────
 *  • Validate incoming JSON payload
 *  • Persist RMA request, items and any base‑64 images
 *  • Build & send notification e‑mails using dynamic system‑config
 *
 *  System‑config paths (see etc/adminhtml/system.xml):
 *    rma_email/new_request/enabled          : ⟨yes/no⟩ master switch
 *    rma_email/new_request/sender_identity  : Store Email Identity code
 *    rma_email/new_request/template         : Customer e‑mail template
 *    rma_email/new_request/copy_method      : cc | bcc | (empty = separate)
 *    rma_email/new_request/send_to_admin    : ⟨yes/no⟩ admin copy switch
 *    rma_email/new_request/admin_email      : Admin recipient address
 *    rma_email/new_request/admin_template   : Admin e‑mail template
 *
 *  NOTE: All look‑ups are store‑scoped, so each store view can differ.
 *
 */
class Save extends Action
{
    /* ---------- system‑config XPath constants ---------- */
    private const XML_PATH_ENABLED          = 'rma_email/new_request/enabled';
    private const XML_PATH_SENDER_IDENTITY  = 'rma_email/new_request/sender_identity';
    private const XML_PATH_TEMPLATE         = 'rma_email/new_request/template';
    private const XML_PATH_COPY_METHOD      = 'rma_email/new_request/copy_method';
    private const XML_PATH_SEND_TO_ADMIN    = 'rma_email/new_request/send_to_admin';
    private const XML_PATH_ADMIN_EMAIL      = 'rma_email/new_request/admin_email';
    private const XML_PATH_ADMIN_TEMPLATE   = 'rma_email/new_request/admin_template';

    /* ---------- DI properties ---------- */

    /** @var JsonFactory */
    private JsonFactory $jsonFactory;

    /** @var RequestFactory */
    private RequestFactory $requestFactory;

    /** @var ItemFactory */
    private ItemFactory $itemFactory;

    /** @var ItemImageFactory */
    private ItemImageFactory $itemImageFactory;

    /** @var ProductRepositoryInterface */
    private ProductRepositoryInterface $productRepository;

    /** @var Transaction */
    private Transaction $transaction;

    /** @var File */
    private File $file;

    /** @var TransportBuilder */
    private TransportBuilder $transportBuilder;

    /** @var StoreManagerInterface */
    private StoreManagerInterface $storeManager;

    /** @var ScopeConfigInterface */
    private ScopeConfigInterface $scopeConfig;

    /** @var SenderResolverInterface */
    private SenderResolverInterface $senderResolver;
    /** @var Filesystem */
    protected $filesystem;
    /**
     * DI constructor.
     *
     * @param Context                    $context
     * @param JsonFactory                $jsonFactory
     * @param RequestFactory             $requestFactory
     * @param ItemFactory                $itemFactory
     * @param Transaction                $transaction
     * @param TransportBuilder           $transportBuilder
     * @param StoreManagerInterface      $storeManager
     * @param ProductRepositoryInterface $productRepository
     * @param ItemImageFactory           $itemImageFactory
     * @param File                       $file
     * @param Filesystem                 $filesystem
     * @param ScopeConfigInterface       $scopeConfig
     * @param SenderResolverInterface    $senderResolver
     */
    public function __construct(
        Context                     $context,
        JsonFactory                 $jsonFactory,
        RequestFactory              $requestFactory,
        ItemFactory                 $itemFactory,
        Transaction                 $transaction,
        TransportBuilder            $transportBuilder,
        StoreManagerInterface       $storeManager,
        ProductRepositoryInterface  $productRepository,
        ItemImageFactory            $itemImageFactory,
        File                        $file,
        Filesystem                  $filesystem,
        ScopeConfigInterface        $scopeConfig,
        SenderResolverInterface     $senderResolver,
    ) {
        parent::__construct($context);
        $this->jsonFactory       = $jsonFactory;
        $this->requestFactory    = $requestFactory;
        $this->itemFactory       = $itemFactory;
        $this->transaction       = $transaction;
        $this->transportBuilder  = $transportBuilder;
        $this->storeManager      = $storeManager;
        $this->productRepository = $productRepository;
        $this->itemImageFactory  = $itemImageFactory;
        $this->file              = $file;
        $this->filesystem        = $filesystem;
        $this->scopeConfig       = $scopeConfig;
        $this->senderResolver    = $senderResolver;
    }

    /**
     * Main controller entry.
     *
     * Handles the POST JSON payload, persists data and (optionally) sends e‑mails.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            /* 1. Parse and validate body ------------------------------------------------ */
            $data = json_decode($this->getRequest()->getContent(), true);
            if (empty($data['request']) || empty($data['items'])) {
                throw new LocalizedException(__('Invalid request data.'));
            }

            /* 2. Persist RMA request, items and images --------------------------------- */
            $rmaRequest = $this->requestFactory->create();
            $rmaRequest->setData([
                'order_id'    => $data['request']['order_id'],
                'status'      => 'Pending',
                'customer_id' => $data['request']['customer_id']
            ])->save();

            $emailItems = $this->processItems($data['items'], (int)$rmaRequest->getId());

            /* 3. Conditionally send e‑mails -------------------------------------------- */
            $storeId = (int)$this->storeManager->getStore()->getId();
            if ($this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId)) {
                $this->sendEmails($data, $emailItems, $storeId);
            }

            return $result->setData([
                'success'  => true,
                'message'  => __('RMA submitted successfully.'),
                'order_id' => $data['request']['order_id'],
            ]);

        } catch (\Throwable $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Persist every item (and its images) and prepare e‑mail‑friendly array.
     *
     * @param array $items   Items from JSON payload
     * @param int   $rmaId   Newly created RMA request ID
     * @return array         Normalised data for e‑mail template
     */
    private function processItems(array $items, int $rmaId): array
    {
        $emailItems = [];

        foreach ($items as $itemData) {
            $productId   = (int)$itemData['product_id'];
            $productName = 'Unknown Product';

            try {
                $productName = $this->productRepository->getById($productId)->getName();
            } catch (\Throwable $e) {
                throw $e;
            }

            $rmaItem = $this->itemFactory->create();
            $rmaItem->setData([
                'rma_id'        => $rmaId,
                'product_id'    => $productId,
                'qty_requested' => $itemData['qty_requested'],
                'qty_received'  => 0,
                'reason'        => $itemData['reason'],
                'condition'     => $itemData['condition'],
                'resolution'    => $itemData['resolutions'],
                'status'        => 'Pending',
            ])->save();

            /* images (if any) */
            foreach ($itemData['images'] ?? [] as $img) {
                $this->saveImage($img, (int)$rmaItem->getId());
            }

            $emailItems[] = [
                'name'          => $productName,
                'qty_requested' => $itemData['qty_requested'],
                'reason'        => $itemData['reason'],
                'condition'     => $itemData['condition'],
                'resolutions'   => $itemData['resolutions'],
            ];
        }

        return $emailItems;
    }

    /**
     * Store a single base‑64 image under /pub/media/rma/uploads and create DB record.
     *
     * @param array $img    ['data' => base64string, 'name' => original filename]
     * @param int   $itemId RMA item ID owner
     */
    private function saveImage(array $img, int $itemId): void
    {
        $base64       = $img['data'] ?? '';
        $originalName = $img['name'] ?? 'image';
        // $extension    = pathinfo($originalName, PATHINFO_EXTENSION) ?: 'png';
        // $fileName     = uniqid('', true) . '.' . $extension;
        $pathInfo    = $this->file->getPathInfo($img['name'] ?? 'file.png');
        $fileName    = uniqid('', true) . '.' . ($pathInfo['extension'] ?? 'png');
        /* strip meta header if present */
        if (preg_match('#^data:.*;base64,#', $base64)) {
            $base64 = substr($base64, strpos($base64, ',') + 1);
        }
        $binary = sodium_base642bin(
            $base64,
            SODIUM_BASE64_VARIANT_ORIGINAL
        );

        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $media->create('rma/uploads');
        $media->writeFile('rma/uploads/' . $fileName, $binary);

        $this->itemImageFactory->create()->setData([
            'item_id'    => $itemId,
            'image_name' => $fileName
        ])->save();
    }

    /**
     * Build Transport objects and send customer / admin e‑mails per config.
     *
     * @param array $payload     Full JSON payload
     * @param array $emailItems  Items prepared by {@see processItems()}
     * @param int   $storeId     Current store ID
     */
    private function sendEmails(array $payload, array $emailItems, int $storeId): void
    {
        /* template variables -------------------------------------------------------- */
        $tplVars = [
            'customer_name'  => (string)($payload['request']['customer_name'] ?? __('Customer')),
            'order_id'       => (string)$payload['request']['order_id'],
            'status'         => __('Pending'),
            'items'          => $emailItems,
            'customer_email' => (string)($payload['request']['customer_email'] ?? ''),
        ];

        /* resolve sender ------------------------------------------------------------ */
        $identityCode = (string)$this->scopeConfig->getValue(
            self::XML_PATH_SENDER_IDENTITY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'general';
        $sender = $this->senderResolver->resolve($identityCode, $storeId);

        /* customer mail ------------------------------------------------------------- */
        $templateId = (string)$this->scopeConfig->getValue(
            self::XML_PATH_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if (!empty($tplVars['customer_email']) && $templateId) {
            $this->transportBuilder
                ->setTemplateIdentifier($templateId)
                ->setTemplateOptions([
                    'area'  => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $storeId
                ])
                ->setTemplateVars($tplVars)
                ->setFromByScope($sender, $storeId)
                ->addTo($tplVars['customer_email'], $tplVars['customer_name'])
                ->getTransport()
                ->sendMessage();
        }

        /* optional admin copy ------------------------------------------------------- */
        if ($this->scopeConfig->isSetFlag(self::XML_PATH_SEND_TO_ADMIN, ScopeInterface::SCOPE_STORE, $storeId)) {
            $adminEmail = (string)$this->scopeConfig->getValue(
                self::XML_PATH_ADMIN_EMAIL,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );

            if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $adminTpl = (string)$this->scopeConfig->getValue(
                    self::XML_PATH_ADMIN_TEMPLATE,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                ) ?: $templateId;

                $method = strtolower((string)$this->scopeConfig->getValue(
                    self::XML_PATH_COPY_METHOD,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                ));

                $adminTransport = $this->transportBuilder
                    ->setTemplateIdentifier($adminTpl)
                    ->setTemplateOptions([
                        'area'  => \Magento\Framework\App\Area::AREA_FRONTEND,
                        'store' => $storeId
                    ])
                    ->setTemplateVars($tplVars)
                    ->setFromByScope($sender, $storeId);

                /* CC / BCC / TO */
                switch ($method) {
                    case 'cc':
                        $adminTransport->addCc($adminEmail);
                        break;
                    case 'bcc':
                        $adminTransport->addBcc($adminEmail);
                        break;
                    default:
                        $adminTransport->addTo($adminEmail, __('Store Admin'));
                }

                $adminTransport->getTransport()->sendMessage();
            }
        }
    }
}
