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
namespace ViraXpress\Rma\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Class TestResultsList
 *
 * Renders the HTML for the Test Results field in the system configuration.
 */
class TestResultsList extends Field
{
    /**
     * Render the HTML for the custom system configuration field.
     *
     * If no value is set for the element, it initializes it with the default vaues.
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *   The form element being rendered.
     *
     * @return string The rendered HTML for the configuration field.
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $defaultValues = ['Pending', 'Pass', 'Fail','Need Re-work'];
        if (!$element->getValue()) {
            $element->setValue(implode(',', $defaultValues));
        }
        $this->setElement($element);
        $this->setData('list_class', 'test-result-list');
        return $this->fetchView($this->getTemplateFile('ViraXpress_Rma::system/config/list_field.phtml'));
    }
}
