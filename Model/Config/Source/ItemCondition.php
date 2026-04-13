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

namespace ViraXpress\Rma\Model\Config\Source;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class ItemCondition
 *
 * Dynamically returns inspection statuses from admin configuration.
 */
class ItemCondition implements OptionSourceInterface
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Constructor
     *
     * @param ScopeConfigInterface  $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface        $scopeConfig
    ) {
        $this->scopeConfig          = $scopeConfig;
    }

    /**
     * Return array of options from admin config
     *
     * @return array[]
     */
    public function toOptionArray()
    {
        $raw = $this->scopeConfig->getValue('rma/reasons/item_conditions');
        $configValue = $raw ? array_map('trim', explode(',', $raw)) : [];

        if (is_array($configValue)) {
            return array_map(function ($value) {
                return [
                    'value' => $value,
                    'label' => $value
                ];
            }, $configValue);
        }

        // Fallback
        return [];
    }
}
