# ViraXpress RMA Extension (ViraXpress_Rma)

## Description

ViraXpress RMA is a Magento 2 return merchandise authorization extension that enables admin and customers to manage returns, refunds, replacements, inspection, and status tracking using a flexible workflow. It provides both logged-in and guest order lookup, OTP validation, configurable return policy, and email notification support.

## Features

- Admin dashboard for RMA requests, inspection status, and item-level return actions.
- Frontend customer RMA request flow for existing customers and guest order lookup.
- OTP-based guest verification (Order ID + Last Name + Email + OTP).
- Configurable return policy:
  - Enable/disable RMA module
  - Return window (days after shipped)
  - Per-order and per-item eligibility (order statuses, product types, categories)
  - Allow file uploads with file-types and size control
- Configurable reference lists (in admin):
  - RMA statuses, item statuses, return reasons, item conditions, resolutions, inspection statuses, test results, actions taken
- RMA item actions: refund, replacement, cancel, update status, move to inspection, and accept/reject logic
- Email templates and notifications for new request, status updates, refund, replacement, with optional admin copies
- Integrated admin menu:
  - RMA > RMA Details
  - RMA > Item Inspection
  - RMA > RMA Configurations
  - RMA config sections under `Stores > Configuration > RMA` and `RMA Email`.
- Data model includes `Request`, `Item`, `ItemInspection`, `ItemImage` with resource models and collections
- Extensible via standard Magento 2 conventions (`etc/di.xml`, `etc/events.xml`, UI components, controllers, blocks, templates)

## Compatibility

- Magento 2.4.x

## Installation

1. Install the extension via Composer:
   ```bash
   composer require viraxpress/rma
   ```

2. Run the following commands from your Magento root directory:

    ```bash
    php bin/magento module:enable ViraXpress_Rma
    php bin/magento setup:upgrade
    php bin/magento setup:di:compile
    php bin/magento setup:static-content:deploy
    php bin/magento cache:flush
    ```

## Configuration

1. In Admin go to `Stores > Configuration > RMA > RMA Configuration`.
2. Enable module and configure:
   - Return window
   - Allowed statuses, categories, product types
   - Register status and item reference values
   - File upload settings
3. In `Stores > Configuration > RMA > RMA Email Configurations`, configure each email event and sender templates.

## Support

For support, please contact ViraXpress at [https://www.viraxpress.com](https://www.viraxpress.com) or refer to the license agreement.

## License

This extension is licensed under the ViraXpress license agreement. See [https://www.viraxpress.com/license](https://www.viraxpress.com/license) for details.