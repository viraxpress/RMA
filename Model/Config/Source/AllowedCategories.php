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

use Magento\Framework\Option\ArrayInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Source model to provide allowed product categories as options in system configuration.
 */
class AllowedCategories implements ArrayInterface
{
    /**
     * @var CollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param CollectionFactory             $categoryCollectionFactory
     * @param StoreManagerInterface         $storeManager
     */
    public function __construct(
        CollectionFactory                   $categoryCollectionFactory,
        StoreManagerInterface               $storeManager
    ) {
        $this->categoryCollectionFactory    = $categoryCollectionFactory;
        $this->storeManager                 = $storeManager;
    }

    /**
     * Get all categories as option array with hierarchy
     *
     * @return array<int, array{value: int, label: string}>
     */
    public function toOptionArray()
    {
        $rootCategoryId = $this->storeManager->getStore()->getRootCategoryId();

        $collection = $this->categoryCollectionFactory->create()
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('parent_id')
            ->addAttributeToSelect('path')
            ->addIsActiveFilter()
            ->setOrder('path', 'ASC');

        $categories = [];

        foreach ($collection as $category) {
            if (!$category->getName()) {
                continue;
            }

            $level = count(explode('/', $category->getPath())) - 2;
            $label = str_repeat('--', $level) . ' ' . $category->getName();

            $categories[] = [
                'value' => $category->getId(),
                'label' => $label
            ];
        }

        return $categories;
    }
}
