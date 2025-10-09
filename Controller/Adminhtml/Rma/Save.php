<?php
declare(strict_types=1);
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
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\File\UploaderFactory;
use ViraXpress\Rma\Model\RequestFactory;
use ViraXpress\Rma\Model\ItemFactory;
use ViraXpress\Rma\Model\ItemImageFactory;
use Magento\Framework\Filesystem\Io\File;

/**
 * Class Save
 *
 * Admin‑side controller: create/save an RMA request (and its items) from
 * the Magento backend and send out the configured e‑mail notifications.
 *
 */
class Save extends Action
{
    /* ---------- Config XPath constants ---------- */
    protected const XML_PATH_ENABLED          = 'rma_email/new_request/enabled';
    protected const XML_PATH_SENDER_IDENTITY  = 'rma_email/new_request/sender_identity';
    protected const XML_PATH_TEMPLATE         = 'rma_email/new_request/template';
    protected const XML_PATH_SEND_TO_ADMIN    = 'rma_email/new_request/send_to_admin';
    protected const XML_PATH_ADMIN_EMAIL      = 'rma_email/new_request/admin_email';
    protected const XML_PATH_ADMIN_TEMPLATE   = 'rma_email/new_request/admin_template';

    /**
     * @var JsonFactory
     */
    protected JsonFactory $jsonFactory;
    /**
     * @var RequestFactory
     */
    protected RequestFactory $requestFactory;
    /**
     * @var ItemFactory
     */
    protected ItemFactory $itemFactory;
    /**
     * @var ItemImageFactory
     */
    protected ItemImageFactory $itemImageFactory;
    /**
     * @var ProductRepositoryInterface
     */
    protected ProductRepositoryInterface $productRepository;
    /**
     * @var Transaction
     */
    protected Transaction $transaction;
    /**
     * @var Filesystem
     */
    protected Filesystem $filesystem;
    /**
     * @var UploaderFactory
     */
    protected UploaderFactory $uploaderFactory;
    /**
     * @var TransportBuilder
     */
    protected TransportBuilder $transportBuilder;
    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;
    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;
    /**
     * @var SenderResolverInterface
     */
    protected SenderResolverInterface $senderResolver;
    /**
     * @var File
     */
    protected $file;
    /**
     * DI constructor.
     *
     * @param Context                     $context             Backend action context
     * @param JsonFactory                 $jsonFactory         JSON result factory
     * @param RequestFactory              $requestFactory      Factory for RMA requests
     * @param ItemFactory                 $itemFactory         Factory for RMA items
     * @param Transaction                 $transaction         DB transaction model
     * @param TransportBuilder            $transportBuilder    Email transport builder
     * @param StoreManagerInterface       $storeManager        Store manager
     * @param ProductRepositoryInterface  $productRepository   Product repository
     * @param ItemImageFactory            $itemImageFactory    Factory for RMA item images
     * @param Filesystem                  $filesystem          Filesystem object
     * @param UploaderFactory             $uploaderFactory     Uploader factory
     * @param ScopeConfigInterface        $scopeConfig         Configuration reader
     * @param SenderResolverInterface     $senderResolver      Email sender resolver
     * @param File                        $file
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
        Filesystem                  $filesystem,
        UploaderFactory             $uploaderFactory,
        ScopeConfigInterface        $scopeConfig,
        SenderResolverInterface     $senderResolver,
        File                        $file
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
        $this->filesystem        = $filesystem;
        $this->uploaderFactory   = $uploaderFactory;
        $this->scopeConfig       = $scopeConfig;
        $this->senderResolver    = $senderResolver;
        $this->file              = $file;
    }

    /**
     * Main controller entry.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            /* -----------------------------------------------------------------
             * 1.  Decode POST body (remove form_key noise)
             * ----------------------------------------------------------------- */
            $raw = $this->getRequest()->getContent();
            if ($raw && ($pos = strpos($raw, '&form_key=')) !== false) {
                $raw = substr($raw, 0, $pos);
            }

            $data = $raw ? json_decode($raw, true) : null;
            if (empty($data['request']) || empty($data['items'])) {
                throw new LocalizedException(__('Invalid RMA request data.'));
            }

            /* -----------------------------------------------------------------
             * 2.  Persist request, items & images
             * ----------------------------------------------------------------- */
            $rmaRequest = $this->requestFactory->create();
            $rmaRequest->setData([
                'order_id'    => $data['request']['order_id'],
                'status'      => $data['request']['status'] ?? 'Pending',
                'customer_id' => $data['request']['customer_id']
            ])->save();

            $emailItems = $this->processItems($data['items'], (int)$rmaRequest->getId());

            /* -----------------------------------------------------------------
             * 3.  Dynamic e‑mail notifications
             * ----------------------------------------------------------------- */
            $storeId = (int)$this->storeManager->getStore()->getId();
            if ($this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId)) {
                $this->sendEmails($data, $emailItems, $storeId);
            }

            return $result->setData([
                'success' => true,
                'message' => __('RMA saved successfully.')
            ]);
        } catch (\Throwable $e) {
            return $result->setData([
                'success' => false,
                'message' => __('Error: %1', $e->getMessage())
            ]);
        }
    }

    /* =====================================================================
     *  Helpers
     * ===================================================================== */

    /**
     * Persist items & base‑64 images, return array for template usage.
     *
     * @param   array   $items      Array of item data, including image content and metadata.
     * @param   int     $rmaId      ID of the parent RMA request to associate items with.
     * @return  array               Processed items with formatted data for email/template rendering.
     */
    private function processItems(array $items, int $rmaId): array
    {
        $result = [];

        foreach ($items as $row) {
            $productId   = (int)$row['product_id'];
            $productName = 'Unknown Product';

            try {
                $productName = $this->productRepository->getById($productId)->getName();
            } catch (\Throwable $e) {
                $this->logger->error(sprintf(
                    'Failed to get product name for ID %d: %s',
                    $productId,
                    $e->getMessage()
                ));
                $productName = __('N/A');
            }

            $item = $this->itemFactory->create();
            $item->setData([
                'rma_id'        => $rmaId,
                'product_id'    => $productId,
                'qty_requested' => $row['qty_requested'],
                'qty_received'  => 0,
                'reason'        => $row['reason'],
                'condition'     => $row['condition'],
                'resolution'    => $row['resolutions'],
                'status'        => 'Pending',
            ])->save();

            foreach ($row['images'] ?? [] as $img) {
                $this->saveImage($img, (int)$item->getId());
            }

            $result[] = [
                'name'          => $productName,
                'qty_requested' => $row['qty_requested'],
                'reason'        => $row['reason'],
                'condition'     => $row['condition'],
                'resolutions'   => $row['resolutions'],
            ];
        }

        return $result;
    }

    /**
     * Store a base‑64 image in /pub/media/rma/uploads and create DB record.
     *
     * @param array $img     Image data array containing base64 string and metadata.
     * @param int   $itemId  ID of the RMA item this image is associated with.
     *
     * @return void
     */
    private function saveImage(array $img, int $itemId): void
    {
        $content  = $img['data'] ?? '';
        //$filename = uniqid('', true) . '.' . (pathinfo($img['name'] ?? 'file.png', PATHINFO_EXTENSION) ?: 'png');
        $pathInfo = $this->file->getPathInfo($img['name'] ?? 'file.png');
        $filename = uniqid('', true) . '.' . ($pathInfo['extension'] ?? 'png');

        if (preg_match('#^data:.*;base64,#', $content)) {
            $content = substr($content, strpos($content, ',') + 1);
        }

        $binary = sodium_base642bin(
            $content,
            SODIUM_BASE64_VARIANT_ORIGINAL
        );
        $media  = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $media->create('rma/uploads');
        $media->writeFile('rma/uploads/' . $filename, $binary);

        $this->itemImageFactory->create()->setData([
            'item_id'    => $itemId,
            'image_name' => $filename
        ])->save();
    }

    /**
     * Build & dispatch customer/admin e‑mails using system config.
     *
     * @param array $payload  Email variables such as customer name, RMA ID, etc.
     * @param array $items    List of RMA items to include in the email content.
     * @param int   $storeId  Store ID used to resolve templates and config values.
     *
     * @return void
     */
    private function sendEmails(array $payload, array $items, int $storeId): void
    {
        $vars = [
            'customer_name'  => $payload['request']['customer_name'] ?? __('Customer'),
            'order_id'       => $payload['request']['order_id'],
            'status'         => $payload['request']['status'] ?? __('Pending'),
            'customer_email' => $payload['request']['customer_email'] ?? '',
            'items'          => $items,
        ];

        /* Resolve sender identity */
        $identity = (string)$this->scopeConfig->getValue(
            self::XML_PATH_SENDER_IDENTITY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'general';
        $sender = $this->senderResolver->resolve($identity, $storeId);

        /* Customer mail */
        $tplCustomer = (string)$this->scopeConfig->getValue(
            self::XML_PATH_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($tplCustomer && $vars['customer_email']) {
            $this->transportBuilder
                ->setTemplateIdentifier($tplCustomer)
                ->setTemplateOptions([
                    'area'  => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $storeId
                ])
                ->setTemplateVars($vars)
                ->setFromByScope($sender, $storeId)
                ->addTo($vars['customer_email'], (string)$vars['customer_name'])
                ->getTransport()
                ->sendMessage();
        }

        /* Admin copy */
        if ($this->scopeConfig->isSetFlag(self::XML_PATH_SEND_TO_ADMIN, ScopeInterface::SCOPE_STORE, $storeId)) {
            $adminEmail = (string)$this->scopeConfig
            ->getValue(self::XML_PATH_ADMIN_EMAIL, ScopeInterface::SCOPE_STORE, $storeId);

            if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $tplAdmin = (string)$this->scopeConfig
                ->getValue(self::XML_PATH_ADMIN_TEMPLATE, ScopeInterface::SCOPE_STORE, $storeId)
                            ?: $tplCustomer;

                $adminTransport = $this->transportBuilder
                    ->setTemplateIdentifier($tplAdmin)
                    ->setTemplateOptions([
                        'area'  => \Magento\Framework\App\Area::AREA_ADMINHTML,
                        'store' => $storeId
                    ])
                    ->setTemplateVars($vars)
                    ->setFromByScope($sender, $storeId);
                $adminTransport->getTransport()->sendMessage();
            }
        }
    }
}
