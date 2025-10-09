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
namespace ViraXpress\Rma\Controller\Guest;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class OtpVerify
 *
 * Handles OTP verification and email dispatch for guest RMA access.
 */
class OtpVerify extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var TransportBuilder
     */
    protected $transportBuilder;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var SessionManagerInterface
     */
    protected $sessionFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param Context                   $context
     * @param JsonFactory               $resultJsonFactory
     * @param OrderFactory              $orderFactory
     * @param TransportBuilder          $transportBuilder
     * @param StoreManagerInterface     $storeManager
     * @param SessionManagerInterface   $sessionFactory
     * @param ScopeConfigInterface      $scopeConfig
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        OrderFactory $orderFactory,
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
        SessionManagerInterface $sessionFactory,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->orderFactory = $orderFactory;
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->sessionFactory = $sessionFactory;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Execute controller to verify OTP and send email to guest user.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $orderIncrementId = $this->getRequest()->getParam('order_id');
        $email = $this->getRequest()->getParam('email');
        $lastname = $this->getRequest()->getParam('lastname');
        $otp = $this->generateOtp();

        $order = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);
        if (!$order->getId()) {
            return $result->setData(['success' => false, 'message' => "Invalid Order Id"]);
        }

        $billing = $order->getBillingAddress();
        if (strtolower($billing->getEmail()) !== strtolower($email) ||
            strtolower($billing->getLastname()) !== strtolower($lastname)) {
            return $result->setData(['success' => false, 'message' => 'Invalid email or last name.']);
        }

        $statusesCfg = $this->scopeConfig->getValue(
            'rma/eligibility/allowed_statuses',
            ScopeInterface::SCOPE_STORE
        );
        $statuses = $statusesCfg ? array_map('trim', explode(',', $statusesCfg)) : [];

        if ($statuses && !in_array($order->getStatus(), $statuses, true)) {
            return $result->setData([
                'success' => false,
                'message' => __('Cannot create RMA for this order.')
            ]);
        }

        $session = $this->sessionFactory;
        $session->setOtpCode($otp);

        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier('rma_guest_otp_email_template')
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $this->storeManager->getStore()->getId()
                ])
                ->setTemplateVars(['otp' => $otp])
                ->setFrom(['email' => 'xavierakash.m@ewallsolutions.com', 'name' => 'RMA Support'])
                ->addTo($email)
                ->getTransport();
            $transport->sendMessage();
        } catch (\Exception $e) {
            return $result->setData(['success' => true, 'message' => 'Failed to send OTP.']);
        }

        return $result->setData(['success' => true]);
    }

    /**
     * Generate a random OTP (One-Time Password) of specified length.
     *
     * Generates a secure, uppercase alphanumeric OTP using random_bytes and bin2hex.
     *
     * @param int $length The length of the OTP to generate. Default is 6.
     * @return string The generated OTP.
     * @throws \Exception If it was not possible to gather sufficient entropy.
     */
    function generateOtp($length = 6) {
        $otp = strtoupper(bin2hex(random_bytes($length)));
        return substr($otp, 0, $length);
    }
}
