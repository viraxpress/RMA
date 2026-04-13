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

namespace ViraXpress\Rma\Controller\Guest;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Controller\Result\Json;

/**
 * Class VerifyOtp
 *
 * Controller for verifying OTP (One-Time Password) submitted by a guest user.
 */
class VerifyOtp extends Action
{
    /**
     * @var JsonFactory
     */
    protected JsonFactory $resultJsonFactory;

    /**
     * @var SessionManagerInterface
     */
    protected SessionManagerInterface $session;

    /**
     * VerifyOtp constructor.
     *
     * @param Context $context Application context object.
     * @param JsonFactory $resultJsonFactory Factory to create JSON response.
     * @param SessionManagerInterface $session Session manager to store/retrieve OTP.
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        SessionManagerInterface $session
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->session = $session;
    }

    /**
     * Execute action.
     *
     * Validates the OTP provided by the user against the session-stored OTP.
     * Clears the session OTP on success.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();
        $userOtp = (string) $this->getRequest()->getParam('user_otp');
        $sessionOtp = (string) $this->session->getOtpCode();

        if (!$sessionOtp) {
            return $result->setData([
                'success' => false,
                'message' => 'OTP expired or not found.'
            ]);
        }

        if ($userOtp === $sessionOtp) {
            $this->session->unsOtpCode();
            return $result->setData([
                'success' => true,
                'message' => 'OTP verified successfully.'
            ]);
        }

        return $result->setData([
            'success' => false,
            'message' => 'Invalid OTP.'
        ]);
    }
}
