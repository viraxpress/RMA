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

namespace ViraXpress\Rma\Ui\Component\Listing\Strategy;

use Magento\Framework\Data\Collection;
use Magento\Framework\Api\Filter;
use Magento\Framework\View\Element\UiComponent\DataProvider\FilterApplierInterface;

/**
 * Custom filtering strategy to allow filtering by customer full name in UI grid.
 */
class CustomerNameStrategy implements FilterApplierInterface
{
    /**
     * Apply the filter to the collection based on customer full name.
     *
     * @param Collection    $collection
     * @param Filter        $filter
     * @return void
     */
    public function apply(Collection $collection, Filter $filter)
    {
        $value = $filter->getValue();
        $condition = $filter->getConditionType() ?: 'eq';

        if ($condition === 'like') {
            $collection->getSelect()->where("CONCAT(ce.firstname, ' ', ce.lastname) LIKE ?", $value);
        } elseif ($condition === 'eq') {
            $collection->getSelect()->where("CONCAT(ce.firstname, ' ', ce.lastname) = ?", $value);
        }
    }
}
