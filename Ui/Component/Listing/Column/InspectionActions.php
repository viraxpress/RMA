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

namespace ViraXpress\Rma\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Ui\Component\Listing\Columns\Column;

class InspectionActions extends Column
{
     /** @var UrlInterface */
     protected $urlBuilder;
     /**
      * Constructor.
      *
      * @param \Magento\Framework\View\Element\UiComponent\ContextInterface $context
      * @param \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory
      * @param \Magento\Framework\UrlInterface $urlBuilder
      * @param array $components
      * @param array $data
      */
    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
         $this->urlBuilder = $urlBuilder;
         parent::__construct($context, $uiComponentFactory, $components, $data);
    }
     /**
      * Prepare Data Source for the grid.
      *
      * @param array $dataSource
      * @return array
      */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (isset($item['inspection_id'])) {
                    $item[$this->getData('name')] = [
                        'edit' => [
                            'href' => $this->urlBuilder->getUrl(
                                'vx_rma/iteminspection/view',
                                ['inspection_id' => $item['inspection_id']]
                            ),
                            'label' => __('View'),
                        ]
                    ];
                }
            }
        }
        return $dataSource;
    }
}
