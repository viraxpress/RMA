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
namespace ViraXpress\Rma\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\Data\Form\FormKey;

class Lookup extends Template
{
    /**
     * @var FromKey
     */
    protected $formKey;
    /**
     * Constructor
     *
     * @param Template\Context  $context
     * @param FormKey           $formKey
     * @param array             $data
     */
    public function __construct(
        Template\Context        $context,
        FormKey                 $formKey,
        array                   $data = []
    ) {
        $this->formKey          = $formKey;
        parent::__construct($context, $data);
    }

    /**
     * Get the Form Key.
     *
     * @return string
     */
    public function getFormKey()
    {
        return $this->formKey->getFormKey();
    }
}
