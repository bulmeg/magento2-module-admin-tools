<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;

class OrderStatus implements OptionSourceInterface
{
    private ?array $labels = null;

    public function __construct(
        private readonly CollectionFactory $statusCollectionFactory
    ) {
    }

    public function getLabels(): array
    {
        if ($this->labels === null) {
            $this->labels = [];
            foreach ($this->statusCollectionFactory->create()->toOptionHash() as $code => $label) {
                $this->labels[(string)$code] = (string)$label;
            }
        }

        return $this->labels;
    }

    public function getLabel(string $status): string
    {
        return $this->getLabels()[$status] ?? $status;
    }

    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->getLabels() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }
}
