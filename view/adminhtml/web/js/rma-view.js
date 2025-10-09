define([
    'jquery',
    'Magento_Ui/js/modal/alert',
    'Magento_Ui/js/modal/confirm',
    'Magento_Ui/js/modal/modal'
], function ($, alert, confirm, modal) {
    'use strict';

    return function (config) {
        const criticalStatuses = ['Cancelled', 'Completed', 'Rejected'];

        function showLoaderAndReload() {
            $('body').trigger('processStart');
            setTimeout(function () {
                location.reload();
            }, 300);
        }

        function showAlert(title, content, callback) {
            alert({
                title: $.mage.__(title),
                content: $.mage.__(content),
                actions: {
                    always: callback || function () {}
                }
            });
        }

        function showConfirmationIfNeeded(status, onConfirm) {
            if (criticalStatuses.includes(status)) {
                confirm({
                    title: $.mage.__('Confirmation'),
                    content: $.mage.__('Are you sure you want to change the status to "' + status + '"?'),
                    actions: {
                        confirm: onConfirm,
                        cancel: function () {}
                    }
                });
            } else {
                onConfirm();
            }
        }

        function updateRmaStatus(rmaId, status) {
            $('body').trigger('processStart');
            $.ajax({
                url: config.updateStatusUrl,
                type: 'POST',
                data: {
                    rma_id: rmaId,
                    status: status,
                    customer_email: config.customerEmail,
                    customer_name: config.customerName,
                    form_key: window.FORM_KEY
                },
                success: function (response) {
                    $('body').trigger('processStop');
                    if (response.success) {
                        showAlert('Success', response.message || 'Status updated successfully.', showLoaderAndReload);
                    } else {
                        showAlert('Error', response.message);
                    }
                },
                error: function () {
                    $('body').trigger('processStop');
                    showAlert('Error', 'Failed to update status. Try again.');
                }
            });
        }

        function updateRmaItemStatus(itemId, rmaId, status) {
            $('body').trigger('processStart');
            $.ajax({
                url: config.updateItemStatusUrl,
                type: 'POST',
                data: {
                    item_id: itemId,
                    rma_id: rmaId,
                    status: status,
                    form_key: window.FORM_KEY
                },
                success: function (response) {
                    $('body').trigger('processStop');
                    if (response.success) {
                        showAlert('Success', 'Item status updated successfully.', showLoaderAndReload);
                    } else {
                        showAlert('Error', response.message);
                    }
                },
                error: function () {
                    $('body').trigger('processStop');
                    showAlert('Error', 'Failed to update item status. Try again.');
                }
            });
        }

        // Refund
        function processRefund(button) {
            confirm({
                title: $.mage.__('Confirm Refund'),
                content: $.mage.__('Are you sure you want to process a refund for this item?'),
                actions: {
                    confirm: function () {
                        $('body').trigger('processStart');
                        $.ajax({
                            url: config.refundUrl,
                            type: 'POST',
                            data: {
                                order_id: button.data('order-id'),
                                product_id: button.data('product-id'),
                                qty: button.data('qty'),
                                rma_id: button.data('rma-id'),
                                item_id: button.data('item-id'),
                                form_key: window.FORM_KEY
                            },
                            success: function (response) {
                                $('body').trigger('processStop');
                                if (response.success) {
                                    alert({
                                        title: $.mage.__('Success'),
                                        content: $.mage.__('Refund processed successfully.'),
                                        actions: { always: showLoaderAndReload }
                                    });
                                } else {
                                    alert({
                                        title: $.mage.__('Error'),
                                        content: $.mage.__(response.message || 'Failed to process refund.')
                                    });
                                }
                            },
                            error: function () {
                                $('body').trigger('processStop');
                                alert({
                                    title: $.mage.__('Error'),
                                    content: $.mage.__('An error occurred while processing the refund.')
                                });
                            }
                        });
                    }
                }
            });
        }

        // Replacement
        function processReplacement(button) {
            confirm({
                title: $.mage.__('Confirm Replacement'),
                content: $.mage.__('Are you sure you want to create a replacement for this item?'),
                actions: {
                    confirm: function () {
                        $('body').trigger('processStart');
                        $.ajax({
                            url: config.replacementUrl,
                            type: 'POST',
                            data: {
                                order_id: button.data('order-id'),
                                product_id: button.data('product-id'),
                                qty: button.data('qty'),
                                rma_id: button.data('rma-id'),
                                item_id: button.data('item-id'),
                                form_key: window.FORM_KEY
                            },
                            success: function (response) {
                                $('body').trigger('processStop');
                                if (response.success) {
                                    alert({
                                        title: $.mage.__('Success'),
                                        content: $.mage.__(response.message),
                                        actions: { always: showLoaderAndReload }
                                    });
                                } else {
                                    alert({
                                        title: $.mage.__('Error'),
                                        content: $.mage.__(response.message)
                                    });
                                }
                            },
                            error: function () {
                                $('body').trigger('processStop');
                                alert({
                                    title: $.mage.__('Error'),
                                    content: $.mage.__('An error occurred while processing the replacement.')
                                });
                            }
                        });
                    }
                }
            });
        }

        // Inspection
        function processInspection(button) {
            confirm({
                title: $.mage.__('Confirm Inspection'),
                content: $.mage.__('Are you sure you want to move this item to inspection?'),
                actions: {
                    confirm: function () {
                        $('body').trigger('processStart');
                        $.ajax({
                            url: config.inspectionUrl,
                            type: 'POST',
                            data: {
                                item_id: button.data('item-id'),
                                product_id: button.data('product-id'),
                                sku: button.data('sku'),
                                qty: button.data('qty'),
                                rma_id: button.data('rma-id'),
                                condition: button.data('condition'),
                                form_key: window.FORM_KEY
                            },
                            success: function (response) {
                                $('body').trigger('processStop');
                                if (response.success) {
                                    button.prop('disabled', true);
                                    alert({
                                        title: $.mage.__('Success'),
                                        content: $.mage.__('Item moved to inspection successfully.'),
                                        actions: { always: showLoaderAndReload }
                                    });
                                } else {
                                    alert({
                                        title: $.mage.__('Error'),
                                        content: $.mage.__(response.message || 'Failed to move item to inspection.')
                                    });
                                }
                            },
                            error: function () {
                                $('body').trigger('processStop');
                                alert({
                                    title: $.mage.__('Error'),
                                    content: $.mage.__('An error occurred while moving the item to inspection.')
                                });
                            }
                        });
                    }
                }
            });
        }

        // Image preview modal
        function initImagePreview() {
            var imageModalOptions = {
                type: 'popup',
                responsive: true,
                innerScroll: false,
                title: 'File Preview',
                modalClass: 'rma-image-popup',
                buttons: [{
                    text: $.mage.__('Close'),
                    class: 'action-secondary',
                    click: function () {
                        this.closeModal();
                    }
                }]
            };
            var imageModal = modal(imageModalOptions, $('#rma-image-modal'));

            $(document).on('click', '.rma-image-thumb', function () {
                var fileUrl = $(this).data('full');
                var extension = fileUrl.split('.').pop().toLowerCase();
                var $modalBody = $('#rma-image-modal-content');
                $modalBody.empty();

                if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(extension)) {
                    $('<img>', {
                        src: fileUrl,
                        class: 'preview-content',
                        css: { width: '100%', height: 'auto', maxHeight: '80vh', objectFit: 'contain' }
                    }).appendTo($modalBody);
                } else if (extension === 'pdf') {
                    $('<embed>', {
                        src: fileUrl,
                        type: 'application/pdf',
                        class: 'preview-content',
                        css: { width: '100%', height: '80vh' }
                    }).appendTo($modalBody);
                } else {
                    $('<div>', {
                        text: 'This file type cannot be previewed. Click to download.',
                        css: { padding: '20px', textAlign: 'center' }
                    }).appendTo($modalBody);
                    $('<a>', {
                        href: fileUrl,
                        text: 'Download File',
                        target: '_blank',
                        class: 'action-primary'
                    }).appendTo($modalBody);
                }

                $('#rma-image-modal').modal('openModal');
            });
        }

        // Event bindings
        $('#rma-back').on('click', function () {
            window.location.href = config.backUrl;
        });

        $('.rma_status').on('focus', function () {
            $(this).data('original-value', $(this).val());
        });

        $('.rma_status').on('change', function () {
            var rmaId = $(this).data('rma-id');
            var status = $(this).val();
            showConfirmationIfNeeded(status, function () {
                updateRmaStatus(rmaId, status);
            });
        });

        $('.item_status').on('change', function () {
            var itemId = $(this).data('item-id');
            var rmaId = $(this).data('rma-id');
            var status = $(this).val();
            showConfirmationIfNeeded(status, function () {
                updateRmaItemStatus(itemId, rmaId, status);
            });
        });

        $('.refund-action-button').on('click', function () {
            processRefund($(this));
        });

        $('.replacement-action-button').on('click', function () {
            processReplacement($(this));
        });

        $('.inspection-action-button').on('click', function () {
            processInspection($(this));
        });

        initImagePreview();
    };
});
