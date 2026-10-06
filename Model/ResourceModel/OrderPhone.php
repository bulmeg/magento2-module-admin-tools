<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;

class OrderPhone
{
    private const CONNECTION_NAME = 'sales';
    private const ADDRESS_TABLE = 'sales_order_address';
    private const ADDRESS_ALIAS = 'bulmeg_phone_address';
    private const SEPARATORS = [' ', '-', '(', ')', '.', '/', '+', "\u{00A0}", "\t"];
    private const CHUNK_SIZE = 1000;

    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function getOrderIdsSelect(string $digits): Select
    {
        $this->assertDigits($digits);
        $column = self::ADDRESS_ALIAS . '.telephone';

        return $this->getAddressSelect()
            ->where($column . ' LIKE ?', $this->getDigitsPattern($digits))
            ->where($this->getDigitsExpression($column) . ' LIKE ?', '%' . $digits . '%');
    }

    public function getOrderIdsSelectByText(string $text): Select
    {
        return $this->getAddressSelect()->where(
            self::ADDRESS_ALIAS . '.telephone LIKE ?',
            '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text) . '%'
        );
    }

    public function getCandidates(string $digits, int $excludeOrderId, int $limit): array
    {
        $this->assertDigits($digits);
        $select = $this->getConnection()->select()
            ->from($this->getTableName(), ['parent_id', 'telephone'])
            ->where('telephone LIKE :bulmeg_phone_pattern')
            ->where('parent_id <> :bulmeg_exclude_order_id')
            ->order('entity_id ' . Select::SQL_DESC)
            ->limit($limit);

        return $this->getConnection()->fetchAll($select, [
            'bulmeg_phone_pattern' => $this->getDigitsPattern($digits),
            'bulmeg_exclude_order_id' => $excludeOrderId,
        ]);
    }

    public function getPhones(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        $billing = [];
        $shipping = [];
        foreach (array_chunk($orderIds, self::CHUNK_SIZE) as $chunk) {
            $select = $this->getConnection()->select()
                ->from($this->getTableName(), ['parent_id', 'address_type', 'telephone'])
                ->where('parent_id IN (?)', $chunk)
                ->where('address_type IN (?)', ['billing', 'shipping']);
            foreach ($this->getConnection()->fetchAll($select) as $row) {
                $phone = trim((string)$row['telephone']);
                if ($phone === '') {
                    continue;
                }
                if ($row['address_type'] === 'billing') {
                    $billing[(int)$row['parent_id']] = $phone;
                } else {
                    $shipping[(int)$row['parent_id']] = $phone;
                }
            }
        }

        return $billing + $shipping;
    }

    private function getDigitsPattern(string $digits): string
    {
        return '%' . implode('%', str_split($digits)) . '%';
    }

    private function getDigitsExpression(string $column): string
    {
        $connection = $this->getConnection();
        $expression = $connection->quoteIdentifier($column);
        foreach (self::SEPARATORS as $separator) {
            $expression = sprintf('REPLACE(%s, %s, \'\')', $expression, $connection->quote($separator));
        }

        return $expression;
    }

    private function assertDigits(string $digits): void
    {
        if (!preg_match('/^\d+$/', $digits)) {
            throw new \InvalidArgumentException('Only digits can be searched for.');
        }
    }

    private function getAddressSelect(): Select
    {
        return $this->getConnection()->select()
            ->from([self::ADDRESS_ALIAS => $this->getTableName()], ['parent_id'])
            ->where(self::ADDRESS_ALIAS . '.parent_id IS NOT NULL');
    }

    private function getTableName(): string
    {
        return $this->resourceConnection->getTableName(self::ADDRESS_TABLE, self::CONNECTION_NAME);
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection(self::CONNECTION_NAME);
    }
}
