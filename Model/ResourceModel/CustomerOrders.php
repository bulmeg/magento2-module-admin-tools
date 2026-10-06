<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Model\ResourceModel;

use Bulmeg\AdminTools\Model\Phone\Normalizer;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;

class CustomerOrders
{
    public const MAX_MATCHES = 1000;

    private const CONNECTION_NAME = 'sales';
    private const ORDER_TABLE = 'sales_order';
    private const PHONE_CANDIDATES = 5000;

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly OrderPhone $orderPhone,
        private readonly Normalizer $normalizer
    ) {
    }

    public function getIdsByCustomerId(int $customerId, int $excludeOrderId): array
    {
        $select = $this->getOrderIdSelect()->where('customer_id = :bulmeg_customer_id');

        return $this->fetchIds($select, [
            'bulmeg_customer_id' => $customerId,
            'bulmeg_exclude_order_id' => $excludeOrderId,
        ]);
    }

    public function getIdsByEmail(string $email, int $excludeOrderId): array
    {
        $select = $this->getOrderIdSelect()->where('customer_email = :bulmeg_email');

        return $this->fetchIds($select, [
            'bulmeg_email' => $email,
            'bulmeg_exclude_order_id' => $excludeOrderId,
        ]);
    }

    public function getIdsByPhone(string $digits, int $excludeOrderId): array
    {
        $ids = [];
        foreach ($this->orderPhone->getCandidates($digits, $excludeOrderId, self::PHONE_CANDIDATES) as $row) {
            if ($this->normalizer->normalize((string)$row['telephone']) === $digits) {
                $ids[(int)$row['parent_id']] = (int)$row['parent_id'];
                if (count($ids) > self::MAX_MATCHES) {
                    break;
                }
            }
        }

        return array_values($ids);
    }

    public function getOrders(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if (!$orderIds) {
            return [];
        }

        $select = $this->getConnection()->select()
            ->from(
                $this->getOrderTable(),
                [
                    'entity_id',
                    'increment_id',
                    'created_at',
                    'status',
                    'grand_total',
                    'order_currency_code',
                    'store_id',
                    'store_name',
                    'customer_firstname',
                    'customer_lastname',
                ]
            )
            ->where('entity_id IN (?)', $orderIds)
            ->order(['created_at ' . Select::SQL_DESC, 'entity_id ' . Select::SQL_DESC]);

        return $this->getConnection()->fetchAll($select);
    }

    private function getOrderIdSelect(): Select
    {
        return $this->getConnection()->select()
            ->from($this->getOrderTable(), ['entity_id'])
            ->where('entity_id <> :bulmeg_exclude_order_id')
            ->order('entity_id ' . Select::SQL_DESC)
            ->limit(self::MAX_MATCHES + 1);
    }

    private function fetchIds(Select $select, array $bind): array
    {
        return array_map('intval', $this->getConnection()->fetchCol($select, $bind));
    }

    private function getOrderTable(): string
    {
        return $this->resourceConnection->getTableName(self::ORDER_TABLE, self::CONNECTION_NAME);
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection(self::CONNECTION_NAME);
    }
}
