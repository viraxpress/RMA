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
namespace ViraXpress\Rma\Ui\Component\Listing\Strategy;

use Magento\Framework\Data\Collection;
use Magento\Framework\Api\Filter;
use Magento\Framework\View\Element\UiComponent\DataProvider\FilterApplierInterface;

/**
 * Custom filtering strategy to allow filtering by order increment ID in UI grid.
 */
class OrderIncrementStrategy implements FilterApplierInterface
{
    /**
     * Apply the filter to the collection based on order increment ID.
     *
     * @param Collection    $collection
     * @param Filter        $filter
     * @return void
     */
    public function apply(Collection $collection, Filter $filter)
    {
        $value = $filter->getValue();
        $condition = $filter->getConditionType() ?: 'eq';

        $collection->addFieldToFilter('so.increment_id', [$condition => $value]);
    }
}
