<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Test\Unit\Plugin\OrderGrid;

use Bulmeg\AdminTools\Model\Config;
use Bulmeg\AdminTools\Model\Phone\Normalizer;
use Bulmeg\AdminTools\Model\ResourceModel\OrderPhone;
use Bulmeg\AdminTools\Plugin\OrderGrid\KeywordSearchPlugin;
use Magento\Framework\Api\Filter;
use Magento\Framework\Data\Collection;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\View\Element\UiComponent\DataProvider\FulltextFilter;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as OrderGridCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class KeywordSearchPluginTest extends TestCase
{
    private const PHONE_SELECT = 'SELECT parent_id FROM sales_order_address';

    private Config&MockObject $config;
    private Select&MockObject $select;
    private KeywordSearchPlugin $plugin;
    private bool $proceeded = false;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('getPhoneCountryCode')->willReturn('359');
        $this->select = $this->createMock(Select::class);

        $phoneSelect = $this->createMock(Select::class);
        $phoneSelect->method('assemble')->willReturn(self::PHONE_SELECT);
        $orderPhone = $this->createMock(OrderPhone::class);
        $orderPhone->method('getOrderIdsSelect')->with('888123456')->willReturn($phoneSelect);

        $this->plugin = new KeywordSearchPlugin($this->config, new Normalizer($this->config), $orderPhone);
    }

    private function apply(Collection $collection, string $keyword): void
    {
        $this->proceeded = false;
        $proceed = function (): void {
            $this->proceeded = true;
        };
        $filter = new Filter(['value' => $keyword]);
        $this->plugin->aroundApply($this->createMock(FulltextFilter::class), $proceed, $collection, $filter);
    }

    private function getOrderGrid(): OrderGridCollection&MockObject
    {
        $collection = $this->createMock(OrderGridCollection::class);
        $collection->method('getSelect')->willReturn($this->select);

        return $collection;
    }

    public function testPhoneKeywordSearchesByPhone(): void
    {
        $this->config->method('isPhoneKeywordSearchEnabled')->willReturn(true);
        $this->select->expects($this->once())->method('where')
            ->with(
                'main_table.entity_id IN (?)',
                $this->callback(
                    fn ($value): bool => $value instanceof Expression && (string)$value === self::PHONE_SELECT
                )
            );

        $this->apply($this->getOrderGrid(), '0888 123 456');

        $this->assertFalse($this->proceeded);
    }

    public function testOtherKeywordsUseTheStandardSearch(): void
    {
        $this->config->method('isPhoneKeywordSearchEnabled')->willReturn(true);
        $this->select->expects($this->never())->method('where');

        foreach (['Иван', '000154321', '100154321', 'ivan@example.com'] as $keyword) {
            $this->apply($this->getOrderGrid(), $keyword);
            $this->assertTrue($this->proceeded, $keyword);
        }
    }

    public function testNothingChangesWhenTheToolIsOff(): void
    {
        $this->config->method('isPhoneKeywordSearchEnabled')->willReturn(false);
        $this->select->expects($this->never())->method('where');

        $this->apply($this->getOrderGrid(), '0888123456');

        $this->assertTrue($this->proceeded);
    }

    public function testOtherGridsAreLeftAlone(): void
    {
        $this->config->expects($this->never())->method('isPhoneKeywordSearchEnabled');

        $this->apply($this->createMock(Collection::class), '0888123456');

        $this->assertTrue($this->proceeded);
    }
}
