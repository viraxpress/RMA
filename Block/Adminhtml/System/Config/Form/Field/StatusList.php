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
 * Class StatusList
 *
 * Renders a list of RMA statuses in the system configuration section.
 */
class StatusList extends Field
{
    /**
     * Generate the HTML for the RMA status list field.
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $elementId = $element->getHtmlId();
        $elementName = $element->getName();
        $rawValues   = array_filter(array_map('trim', explode(',', (string) $element->getValue())));
        $readonlyStatuses = ['Pending', 'Approved', 'Received', 'Not Received', 'Rejected', 'Completed', 'Cancelled'];
        if (empty($rawValues)) {
            $rawValues = $readonlyStatuses;
        }
        $html = '<div id="' . $elementId . '_wrapper">';
        $html .= '<ul class="status-list" id="' . $elementId . '_list">';
        foreach ($rawValues as $value) {
            $escapedValue = $value;
            $isReadonly = in_array($value, $readonlyStatuses, true);

            $html .= '<li>';
            $html .= '<input type="text" name="' . $elementName . '[]" value="' . $escapedValue . '" '
                . ($isReadonly ? 'readonly' : '') . ' />';
            if (!$isReadonly) {
                $html .= ' <button type="button" class="remove-field">Remove</button>';
            }
            $html .= '</li>';
        }
        $html .= '</ul>';
        $html .= '<div class="list-add-input"><input type="text" id="' . $elementId . '_new_value" /> ';
        $html .= '<button type="button" class="add-field">Add</button></div>';
        $html .= '</div>';

        $html .= <<<HTML
<script>
    require(['jquery'], function ($) {
        $(function () {
            var elementId   = '{$elementId}';
            var elementName = '{$elementName}';

            var wrapper = $('#' + elementId + '_wrapper');

            // remove an existing value
            wrapper.on('click', '.remove-field', function () {
                $(this).closest('li').remove();
            });

            // add a new value
            wrapper.find('.add-field').on('click', function () {
                var input = $('#' + elementId + '_new_value');
                var value = $.trim(input.val());

                if (value.length) {
                    $('#' + elementId + '_list').append(
                        '<li>' +
                            '<input type="text" name="' + elementName + '[]" value="' + value + '" /> ' +
                            '<button type="button" class="remove-field">Remove</button>' +
                        '</li>'
                    );
                    input.val('');
                }
            });
        });
    });
</script>
<style>
.status-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.status-list li {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
}
.list-add-input{
    display: flex;
    align-items: center;
}

.status-list li input[type="text"],.list-add-input input[type="text"] {
    flex: 1;
    padding: 6px;
    margin-right: 8px;
    box-sizing: border-box;
}

.status-list li .remove-field,.list-add-input .add-field {
    padding: 6px 10px;
    cursor: pointer !important;
    width: 50px;
}

.status-list li .remove-field{
    background-color:rgb(255, 10, 10) !important;
    color:white;
}
.list-add-input .add-field{
    background-color:rgb(237, 85, 9) !important;
    color:white;
}
<?php echo $elementId; ?>_new_value {
    margin-top: 10px;
    padding: 6px;
    margin-right: 8px;
}

<?php echo $elementId; ?>_wrapper .add-field {
    padding: 6px 10px;
    cursor: pointer;
}
</style>
HTML;

        return $html;
    }
}
