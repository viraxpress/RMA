<?php
declare(strict_types=1);

namespace ViraXpress\Rma\Mail;

use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\ObjectManagerInterface;

class TransportBuilderFactory
{
    public function __construct(
        private readonly ObjectManagerInterface $objectManager
    ) {}

    public function create(): TransportBuilder
    {
        return $this->objectManager->create(TransportBuilder::class);
    }
}