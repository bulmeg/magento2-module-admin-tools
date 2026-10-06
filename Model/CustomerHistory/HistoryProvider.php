<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Model\CustomerHistory;

use Bulmeg\AdminTools\Model\Config;
use Bulmeg\AdminTools\Model\Phone\Normalizer;
use Bulmeg\AdminTools\Model\ResourceModel\CustomerOrders;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;

class HistoryProvider
{
    private array $histories = [];

    public function __construct(
        private readonly Config $config,
        private readonly CustomerOrders $customerOrders,
        private readonly Normalizer $normalizer,
        private readonly Classifier $classifier
    ) {
    }

    public function getHistory(OrderInterface $order): History
    {
        $orderId = (int)$order->getEntityId();
        if ($orderId > 0 && isset($this->histories[$orderId])) {
            return $this->histories[$orderId];
        }
        $found = $this->findOrderIds($order, $orderId);
        $truncated = [];
        $matches = [];
        foreach ($found as $criterion => $ids) {
            if (count($ids) > CustomerOrders::MAX_MATCHES) {
                $truncated[] = $criterion;
                $ids = array_slice($ids, 0, CustomerOrders::MAX_MATCHES);
            }
            foreach ($ids as $id) {
                $matches[$id][] = $criterion;
            }
        }

        $history = $this->createHistory($this->customerOrders->getOrders(array_keys($matches)), $matches, $truncated);
        if ($orderId > 0) {
            $this->histories[$orderId] = $history;
        }

        return $history;
    }

    private function findOrderIds(OrderInterface $order, int $orderId): array
    {
        $criteria = $this->config->getHistoryMatchBy();
        $found = [];
        $customerId = (int)$order->getCustomerId();
        if ($customerId > 0 && in_array(MatchBy::CUSTOMER, $criteria, true)) {
            $found[MatchBy::CUSTOMER] = $this->customerOrders->getIdsByCustomerId($customerId, $orderId);
        }
        $email = $this->normalizeEmail((string)$order->getCustomerEmail());
        if ($email !== '' && in_array(MatchBy::EMAIL, $criteria, true) && !$this->isIgnoredEmail($email)) {
            $found[MatchBy::EMAIL] = $this->customerOrders->getIdsByEmail($email, $orderId);
        }
        if (in_array(MatchBy::PHONE, $criteria, true)) {
            $ids = $this->findIdsByPhone($order, $orderId);
            if ($ids) {
                $found[MatchBy::PHONE] = $ids;
            }
        }

        return $found;
    }

    private function findIdsByPhone(OrderInterface $order, int $orderId): array
    {
        $ids = [];
        foreach ($this->getPhoneDigits($order) as $digits) {
            if (!$this->isIgnoredPhone($digits)) {
                $ids[] = $this->customerOrders->getIdsByPhone($digits, $orderId);
            }
        }
        if (!$ids) {
            return [];
        }
        $ids = array_values(array_unique(array_merge(...$ids)));
        rsort($ids);

        return $ids;
    }

    private function createHistory(array $rows, array $matches, array $truncated): History
    {
        $reliableStatuses = $this->config->getReliableStatuses();
        $neutralStatuses = $this->config->getNeutralStatuses();
        $counts = [
            Classifier::OUTCOME_SUCCESSFUL => 0,
            Classifier::OUTCOME_PROBLEMATIC => 0,
            Classifier::OUTCOME_NOT_COUNTED => 0,
        ];
        $problematicByStatus = [];
        $successfulTotals = [];
        $orders = [];

        foreach ($rows as $row) {
            $status = (string)$row['status'];
            $outcome = $this->classifier->getOutcome($status, $reliableStatuses, $neutralStatuses);
            $counts[$outcome]++;
            if ($outcome === Classifier::OUTCOME_SUCCESSFUL) {
                $currency = (string)$row['order_currency_code'];
                $successfulTotals[$currency] = ($successfulTotals[$currency] ?? 0.0) + (float)$row['grand_total'];
            } elseif ($outcome === Classifier::OUTCOME_PROBLEMATIC) {
                $problematicByStatus[$status] = ($problematicByStatus[$status] ?? 0) + 1;
            }
            $orders[] = $this->createOrderRow($row, $outcome, $matches[(int)$row['entity_id']] ?? []);
        }
        arsort($problematicByStatus);

        return new History(
            $this->classifier->getVerdict(
                count($orders),
                $counts[Classifier::OUTCOME_SUCCESSFUL],
                $counts[Classifier::OUTCOME_PROBLEMATIC],
                $this->config->getRegularMinOrders()
            ),
            count($orders),
            $counts[Classifier::OUTCOME_SUCCESSFUL],
            $counts[Classifier::OUTCOME_PROBLEMATIC],
            $counts[Classifier::OUTCOME_NOT_COUNTED],
            $problematicByStatus,
            $successfulTotals,
            array_slice($orders, 0, $this->config->getHistoryLimit()),
            $truncated
        );
    }

    private function createOrderRow(array $row, string $outcome, array $matchedBy): array
    {
        return [
            'entity_id' => (int)$row['entity_id'],
            'increment_id' => (string)$row['increment_id'],
            'created_at' => (string)$row['created_at'],
            'status' => (string)$row['status'],
            'outcome' => $outcome,
            'grand_total' => (float)$row['grand_total'],
            'currency' => (string)$row['order_currency_code'],
            'store_id' => (int)$row['store_id'],
            'store_name' => $this->getStoreViewName((string)$row['store_name']),
            'customer_name' => trim($row['customer_firstname'] . ' ' . $row['customer_lastname']),
            'matched_by' => $matchedBy,
        ];
    }

    private function getPhoneDigits(OrderInterface $order): array
    {
        $addresses = $order instanceof Order ? $order->getAddresses() : [$order->getBillingAddress()];
        $phones = [];
        foreach ($addresses as $address) {
            $digits = $address ? $this->normalizer->getMatchDigits((string)$address->getTelephone()) : null;
            if ($digits !== null) {
                $phones[$digits] = $digits;
            }
        }

        return array_values($phones);
    }

    private function isIgnoredEmail(string $email): bool
    {
        foreach ($this->config->getIgnoredValues() as $value) {
            if (!str_contains($value, '@')) {
                continue;
            }
            $value = $this->normalizeEmail($value);
            if (str_starts_with($value, '@') ? str_ends_with($email, $value) : $value === $email) {
                return true;
            }
        }

        return false;
    }

    private function isIgnoredPhone(string $digits): bool
    {
        foreach ($this->config->getIgnoredValues() as $value) {
            if (!str_contains($value, '@') && $this->normalizer->normalize($value) === $digits) {
                return true;
            }
        }

        return false;
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function getStoreViewName(string $storeName): string
    {
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\R/', $storeName) ?: []),
            fn (string $line): bool => $line !== ''
        ));

        return $lines ? (string)end($lines) : '';
    }
}
