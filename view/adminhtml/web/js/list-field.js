define(['jquery'], function ($) {
    'use strict';

    return function (config) {
        var wrapper = $('#' + config.elementId + '_wrapper');

        wrapper.on('click', '.remove-field', function () {
            $(this).closest('li').remove();
        });

        wrapper.find('.add-field').on('click', function () {
            var input = $('#' + config.elementId + '_new_value');
            var value = $.trim(input.val());

            if (value.length) {
                $('#' + config.elementId + '_list').append(
                    '<li class="list-add-input">' +
                        '<input type="text" name="' + config.elementName + '[]" value="' + value + '" > ' +
                        '<button type="button" class="remove-field">Remove</button>' +
                    '</li>'
                );
                input.val('');
            }
        });
    };
});
