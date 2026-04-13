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

namespace ViraXpress\Rma\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Custom config field rendering a list of item‑level RMA statuses.
 */
class ItemStatusList extends Field
{
    /**
     * Render the HTML for the configuration field.
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $elementId   = $element->getHtmlId();
        $elementName = $element->getName();
        $rawValues   = array_filter(array_map('trim', explode(',', (string) $element->getValue())));

        // Hardcoded readonly status values
        $readonlyStatuses = ['Pending', 'Inspection In Progress', 'Return Accepted',
                            'Return Rejected', 'Completed', 'Cancelled'];

        if (empty($rawValues)) {
            $rawValues = $readonlyStatuses;
        }
        $html  = '<div id="' . $elementId . '_wrapper">';
        $html .= '<ul class="item-status-list" id="' . $elementId . '_list">';

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

        $html .= '<div class="list-add-input" style="margin-top:10px;">';
        $html .= '<input type="text" id="' . $elementId . '_new_value" style="width: 200px;" /> ';
        $html .= '<button type="button" class="add-field">Add</button>';
        $html .= '</div>';

        $html .= '</div>';

        $html .= <<<HTML
<script>
require(['jquery'], function ($) {
    $(function () {
        var elementId   = '{$elementId}';
        var elementName = '{$elementName}';
        var wrapper     = $('#' + elementId + '_wrapper');
        var list        = $('#' + elementId + '_list');

        // Remove field (only applies to new, editable items)
        wrapper.on('click', '.remove-field', function () {
            $(this).closest('li').remove();
        });

        // Add new field (editable with remove button)
        wrapper.find('.add-field').on('click', function () {
            var input = $('#' + elementId + '_new_value');
            var value = $.trim(input.val());

            if (value.length) {
                var escapedValue = $('<div>').text(value).html(); // Escape HTML

                list.append(
                    '<li>' +
                        '<input type="text" name="' + elementName + '[]" value="' + escapedValue + '" /> ' +
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
.item-status-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.item-status-list li,
.list-add-input {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
}
.item-status-list li input[type="text"],
.list-add-input input[type="text"] {
    flex: 1;
    padding: 6px;
    margin-right: 8px;
    box-sizing: border-box;
}
.item-status-list li .remove-field,
.list-add-input .add-field {
    padding: 6px 10px;
    width: 60px;
    cursor: pointer;
    color: #fff;
}
.list-add-input{
    display: flex;
    align-items: center;
}
.item-status-list li .remove-field { background: #ff0a0a; }
.list-add-input .add-field        { background: #ed5509; }
#{$elementId}_new_value { margin-top: 10px; }
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
