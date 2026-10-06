<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Plugin\OrderGrid;

use Bulmeg\AdminTools\Model\Config;
use Bulmeg\AdminTools\Model\Phone\Normalizer;
use Bulmeg\AdminTools\Model\ResourceModel\OrderPhone;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Sql\Expression;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection;

class PhoneColumnPlugin
{
    public const FIELD = 'bulmeg_telephone';

    public function __construct(
        private readonly Config $config,
        private readonly Normalizer $normalizer,
        private readonly OrderPhone $orderPhone
    ) {
    }

    public function beforeAddFieldToFilter(Collection $subject, mixed $field, mixed $condition = null): ?array
    {
        if ($field !== self::FIELD) {
            return null;
        }
        if (!$this->config->isPhoneSearchEnabled()) {
            return ['main_table.entity_id', ['notnull' => true]];
        }

        $text = $this->getSearchText($condition);
        $digits = $this->normalizer->normalize($text);
        $select = $digits !== ''
            ? $this->orderPhone->getOrderIdsSelect($digits)
            : $this->orderPhone->getOrderIdsSelectByText($text);

        return ['main_table.entity_id', ['in' => new Expression((string)$select->assemble())]];
    }

    public function afterGetItems(Collection $subject, mixed $result): mixed
    {
        if (!is_array($result) || !$subject->getPageSize() || !$this->config->isPhoneSearchEnabled()) {
            return $result;
        }

        $items = $this->getItemsWithoutPhone($result);
        if ($items) {
            $phones = $this->orderPhone->getPhones(array_keys($items));
            foreach ($items as $orderId => $item) {
                $item->setData(self::FIELD, $phones[$orderId] ?? '');
            }
        }

        return $result;
    }

    private function getItemsWithoutPhone(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if ($item instanceof DataObject && !$item->hasData(self::FIELD)) {
                $result[(int)$item->getData('entity_id')] = $item;
            }
        }

        return $result;
    }

    private function getSearchText(mixed $condition): string
    {
        if (is_array($condition)) {
            $condition = $condition['like'] ?? $condition['eq'] ?? reset($condition);
        }
        if (!is_scalar($condition)) {
            return '';
        }

        $text = trim((string)$condition);
        if (strlen($text) >= 2 && str_starts_with($text, '%') && str_ends_with($text, '%')) {
            $text = substr($text, 1, -1);
        }

        return trim(str_replace(['\\%', '\\_'], ['%', '_'], $text));
    }
}
