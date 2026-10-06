<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Test\Unit\Plugin\OrderGrid;

use Bulmeg\AdminTools\Model\Config;
use Bulmeg\AdminTools\Model\Phone\Normalizer;
use Bulmeg\AdminTools\Model\ResourceModel\OrderPhone;
use Bulmeg\AdminTools\Plugin\OrderGrid\PhoneColumnPlugin;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Sql\Expression;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PhoneColumnPluginTest extends TestCase
{
    private Config&MockObject $config;
    private OrderPhone&MockObject $orderPhone;
    private Collection&MockObject $collection;
    private PhoneColumnPlugin $plugin;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('getPhoneCountryCode')->willReturn('359');
        $this->orderPhone = $this->createMock(OrderPhone::class);
        $this->collection = $this->createMock(Collection::class);
        $this->collection->method('getPageSize')->willReturn(20);
        $this->plugin = new PhoneColumnPlugin($this->config, new Normalizer($this->config), $this->orderPhone);
    }

    public function testOtherFieldsAreNotTouched(): void
    {
        $this->config->expects($this->never())->method('isPhoneSearchEnabled');

        $this->assertNull($this->plugin->beforeAddFieldToFilter($this->collection, 'status', ['eq' => 'pending']));
        $this->assertNull($this->plugin->beforeAddFieldToFilter($this->collection, ['a', 'b'], []));
    }

    public function testFilterIsIgnoredWhenTheToolIsOff(): void
    {
        $this->config->method('isPhoneSearchEnabled')->willReturn(false);
        $this->orderPhone->expects($this->never())->method('getOrderIdsSelect');

        $this->assertSame(
            ['main_table.entity_id', ['notnull' => true]],
            $this->plugin->beforeAddFieldToFilter($this->collection, PhoneColumnPlugin::FIELD, ['like' => '%0888%'])
        );
    }

    public function testFilterBecomesOrderIdCondition(): void
    {
        $this->config->method('isPhoneSearchEnabled')->willReturn(true);
        $select = $this->createMock(Select::class);
        $select->method('assemble')->willReturn('SELECT parent_id FROM sales_order_address');
        $this->orderPhone->expects($this->once())->method('getOrderIdsSelect')
            ->with('888123456')
            ->willReturn($select);

        $result = $this->plugin->beforeAddFieldToFilter(
            $this->collection,
            PhoneColumnPlugin::FIELD,
            ['like' => '%+359 888 123-456%']
        );

        $this->assertSame('main_table.entity_id', $result[0]);
        $this->assertInstanceOf(Expression::class, $result[1]['in']);
        $this->assertSame('SELECT parent_id FROM sales_order_address', (string)$result[1]['in']);
    }

    public function testTextWithoutDigitsIsSearchedAsTyped(): void
    {
        $this->config->method('isPhoneSearchEnabled')->willReturn(true);
        $select = $this->createMock(Select::class);
        $select->method('assemble')->willReturn('SELECT 1');
        $this->orderPhone->expects($this->never())->method('getOrderIdsSelect');
        $this->orderPhone->expects($this->once())->method('getOrderIdsSelectByText')
            ->with('n/a_x')
            ->willReturn($select);

        $this->plugin->beforeAddFieldToFilter($this->collection, PhoneColumnPlugin::FIELD, ['like' => '%n/a\\_x%']);
    }

    public function testPhonesAreAddedOnceToLoadedItems(): void
    {
        $this->config->method('isPhoneSearchEnabled')->willReturn(true);
        $first = new DataObject(['entity_id' => '1']);
        $second = new DataObject(['entity_id' => '2']);
        $this->orderPhone->expects($this->once())->method('getPhones')
            ->with([1, 2])
            ->willReturn([1 => '0888 123 456']);

        $items = [$first, $second];
        $this->assertSame($items, $this->plugin->afterGetItems($this->collection, $items));
        $this->plugin->afterGetItems($this->collection, $items);

        $this->assertSame('0888 123 456', $first->getData(PhoneColumnPlugin::FIELD));
        $this->assertSame('', $second->getData(PhoneColumnPlugin::FIELD));
    }

    public function testNoPhonesWhenTheToolIsOff(): void
    {
        $this->config->method('isPhoneSearchEnabled')->willReturn(false);
        $this->orderPhone->expects($this->never())->method('getPhones');

        $item = new DataObject(['entity_id' => '1']);
        $this->plugin->afterGetItems($this->collection, [$item]);

        $this->assertFalse($item->hasData(PhoneColumnPlugin::FIELD));
    }

    public function testNoPhonesForLoadsWithoutPageSize(): void
    {
        $this->config->method('isPhoneSearchEnabled')->willReturn(true);
        $this->orderPhone->expects($this->never())->method('getPhones');
        $collection = $this->createMock(Collection::class);
        $collection->method('getPageSize')->willReturn(false);

        $item = new DataObject(['entity_id' => '1']);
        $this->plugin->afterGetItems($collection, [$item]);

        $this->assertFalse($item->hasData(PhoneColumnPlugin::FIELD));
    }
}
