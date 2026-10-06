<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Test\Unit\Model\CustomerHistory;

use Bulmeg\AdminTools\Model\Config;
use Bulmeg\AdminTools\Model\CustomerHistory\Classifier;
use Bulmeg\AdminTools\Model\CustomerHistory\HistoryProvider;
use Bulmeg\AdminTools\Model\CustomerHistory\MatchBy;
use Bulmeg\AdminTools\Model\Phone\Normalizer;
use Bulmeg\AdminTools\Model\ResourceModel\CustomerOrders;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HistoryProviderTest extends TestCase
{
    private const ORDER_ID = 100;

    private Config&MockObject $config;
    private CustomerOrders&MockObject $customerOrders;
    private HistoryProvider $provider;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('getReliableStatuses')->willReturn(['processing', 'complete']);
        $this->config->method('getNeutralStatuses')->willReturn([]);
        $this->config->method('getRegularMinOrders')->willReturn(1);
        $this->config->method('getHistoryLimit')->willReturn(2);
        $this->config->method('getPhoneCountryCode')->willReturn('359');
        $this->customerOrders = $this->createMock(CustomerOrders::class);
        $this->provider = new HistoryProvider(
            $this->config,
            $this->customerOrders,
            new Normalizer($this->config),
            new Classifier()
        );
    }

    private function getOrder(?int $customerId, string $email, string $phone): OrderInterface&MockObject
    {
        $address = $this->createMock(OrderAddressInterface::class);
        $address->method('getTelephone')->willReturn($phone);
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(self::ORDER_ID);
        $order->method('getCustomerId')->willReturn($customerId);
        $order->method('getCustomerEmail')->willReturn($email);
        $order->method('getBillingAddress')->willReturn($address);

        return $order;
    }

    private function row(int $id, string $status, float $total = 10.0, string $currency = 'EUR'): array
    {
        return [
            'entity_id' => (string)$id,
            'increment_id' => sprintf('%09d', $id),
            'created_at' => '2026-10-0' . $id . ' 10:00:00',
            'status' => $status,
            'grand_total' => (string)$total,
            'order_currency_code' => $currency,
            'store_id' => '1',
            'store_name' => "Main Website\nMain Website Store\nDefault Store View",
            'customer_firstname' => 'Иван',
            'customer_lastname' => 'Петров',
        ];
    }

    public function testOrdersFoundByAllCriteriaAreMergedAndAssessed(): void
    {
        $this->config->method('getHistoryMatchBy')->willReturn([MatchBy::CUSTOMER, MatchBy::EMAIL, MatchBy::PHONE]);
        $this->config->method('getIgnoredValues')->willReturn([]);
        $this->customerOrders->expects($this->once())->method('getIdsByCustomerId')
            ->with(7, self::ORDER_ID)->willReturn([3]);
        $this->customerOrders->expects($this->once())->method('getIdsByEmail')
            ->with('ivan@example.com', self::ORDER_ID)->willReturn([3, 2]);
        $this->customerOrders->expects($this->once())->method('getIdsByPhone')
            ->with('888123456', self::ORDER_ID)->willReturn([2, 1]);
        $this->customerOrders->expects($this->once())->method('getOrders')
            ->with([3, 2, 1])
            ->willReturn([
                $this->row(3, 'complete', 20.0),
                $this->row(2, 'canceled'),
                $this->row(1, 'processing', 5.5, 'BGN'),
            ]);

        $history = $this->provider->getHistory($this->getOrder(7, ' Ivan@Example.com ', '+359 888 123 456'));

        $this->assertSame(Classifier::VERDICT_MIXED, $history->getVerdict());
        $this->assertSame(3, $history->getTotalCount());
        $this->assertSame(2, $history->getSuccessfulCount());
        $this->assertSame(1, $history->getProblematicCount());
        $this->assertSame(['canceled' => 1], $history->getProblematicByStatus());
        $this->assertSame(['EUR' => 20.0, 'BGN' => 5.5], $history->getSuccessfulTotals());
        $this->assertCount(2, $history->getOrders(), 'Only the configured number of orders is listed.');

        [$first, $second] = $history->getOrders();
        $this->assertSame(3, $first['entity_id']);
        $this->assertSame([MatchBy::CUSTOMER, MatchBy::EMAIL], $first['matched_by']);
        $this->assertSame('Default Store View', $first['store_name']);
        $this->assertSame('Иван Петров', $first['customer_name']);
        $this->assertSame([MatchBy::EMAIL, MatchBy::PHONE], $second['matched_by']);
        $this->assertSame(Classifier::OUTCOME_PROBLEMATIC, $second['outcome']);
    }

    public function testGuestWithIgnoredDetailsIsNew(): void
    {
        $this->config->method('getHistoryMatchBy')->willReturn([MatchBy::CUSTOMER, MatchBy::EMAIL, MatchBy::PHONE]);
        $this->config->method('getIgnoredValues')->willReturn(['ORDERS@shop.bg', '+359 888 000 000']);
        $this->customerOrders->expects($this->never())->method('getIdsByCustomerId');
        $this->customerOrders->expects($this->never())->method('getIdsByEmail');
        $this->customerOrders->expects($this->never())->method('getIdsByPhone');
        $this->customerOrders->method('getOrders')->with([])->willReturn([]);

        $history = $this->provider->getHistory($this->getOrder(null, 'orders@shop.bg', '0888 000 000'));

        $this->assertSame(Classifier::VERDICT_NEW, $history->getVerdict());
        $this->assertSame(0, $history->getTotalCount());
        $this->assertSame([], $history->getOrders());
    }

    public function testHistoryIsBuiltOncePerOrder(): void
    {
        $this->config->method('getHistoryMatchBy')->willReturn([MatchBy::EMAIL]);
        $this->config->method('getIgnoredValues')->willReturn([]);
        $this->customerOrders->expects($this->once())->method('getIdsByEmail')->willReturn([1]);
        $this->customerOrders->expects($this->once())->method('getOrders')->willReturn([$this->row(1, 'complete')]);
        $order = $this->getOrder(null, 'ivan@example.com', '');

        $first = $this->provider->getHistory($order);

        $this->assertSame($first, $this->provider->getHistory($order));
    }

    public function testWholeEmailDomainCanBeIgnored(): void
    {
        $this->config->method('getHistoryMatchBy')->willReturn([MatchBy::EMAIL]);
        $this->config->method('getIgnoredValues')->willReturn(['@Shop.bg']);
        $this->customerOrders->expects($this->never())->method('getIdsByEmail');
        $this->customerOrders->method('getOrders')->with([])->willReturn([]);

        $history = $this->provider->getHistory($this->getOrder(null, 'phone-order-0888123456@shop.bg', ''));

        $this->assertSame(Classifier::VERDICT_NEW, $history->getVerdict());
    }

    public function testDomainRuleDoesNotIgnoreOtherDomains(): void
    {
        $this->config->method('getHistoryMatchBy')->willReturn([MatchBy::EMAIL]);
        $this->config->method('getIgnoredValues')->willReturn(['@shop.bg']);
        $this->customerOrders->expects($this->once())->method('getIdsByEmail')->with('ivan@myshop.bg')->willReturn([]);
        $this->customerOrders->method('getOrders')->willReturn([]);

        $this->provider->getHistory($this->getOrder(null, 'ivan@myshop.bg', ''));
    }

    public function testDisabledCriteriaAreNotQueried(): void
    {
        $this->config->method('getHistoryMatchBy')->willReturn([MatchBy::EMAIL]);
        $this->config->method('getIgnoredValues')->willReturn([]);
        $this->customerOrders->expects($this->never())->method('getIdsByCustomerId');
        $this->customerOrders->expects($this->never())->method('getIdsByPhone');
        $this->customerOrders->method('getIdsByEmail')->willReturn([1]);
        $this->customerOrders->method('getOrders')->willReturn([$this->row(1, 'complete')]);

        $history = $this->provider->getHistory($this->getOrder(7, 'ivan@example.com', '0888123456'));

        $this->assertSame(Classifier::VERDICT_REGULAR, $history->getVerdict());
    }

    public function testMatchesAboveTheLimitAreCut(): void
    {
        $this->config->method('getHistoryMatchBy')->willReturn([MatchBy::EMAIL]);
        $this->config->method('getIgnoredValues')->willReturn([]);
        $ids = range(CustomerOrders::MAX_MATCHES + 1, 1);
        $this->customerOrders->method('getIdsByEmail')->willReturn($ids);
        $this->customerOrders->expects($this->once())->method('getOrders')
            ->with(array_slice($ids, 0, CustomerOrders::MAX_MATCHES))
            ->willReturn([]);

        $history = $this->provider->getHistory($this->getOrder(null, 'shared@example.com', ''));

        $this->assertSame([MatchBy::EMAIL], $history->getTruncatedCriteria());
    }
}
