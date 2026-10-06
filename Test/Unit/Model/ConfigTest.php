<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Test\Unit\Model;

use Bulmeg\AdminTools\Model\Config;
use Bulmeg\AdminTools\Model\Config\Source\StatusColor;
use Bulmeg\AdminTools\Model\CustomerHistory\MatchBy;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;
    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new Config($this->scopeConfig, new Json(), new StatusColor());
    }

    private function setValues(array $values): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path) => $values[$path] ?? null
        );
        $this->scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path) => !empty($values[$path])
        );
    }

    public function testStatusColorsSkipInvalidRows(): void
    {
        $this->setValues([
            Config::XML_PATH_STATUS_COLORS => json_encode([
                '_1' => ['status' => 'pending', 'color' => 'white'],
                '_2' => ['status' => 'complete', 'color' => 'green'],
                '_3' => ['status' => 'canceled', 'color' => '#ff0000'],
                '_4' => ['status' => str_repeat('x', 33), 'color' => 'red'],
                '_7' => ['status' => 'Ready_To_Ship', 'color' => 'blue'],
                '_5' => ['status' => 'holded'],
                '_6' => 'not a row',
            ]),
        ]);

        $this->assertSame(
            ['pending' => 'white', 'complete' => 'green', 'Ready_To_Ship' => 'blue'],
            $this->config->getStatusColors()
        );
        $this->assertSame('green', $this->config->getStatusColor('complete'));
        $this->assertNull($this->config->getStatusColor('canceled'));
    }

    public function testBrokenStatusColorsGiveNoColors(): void
    {
        $this->setValues([Config::XML_PATH_STATUS_COLORS => '{broken']);

        $this->assertSame([], $this->config->getStatusColors());
    }

    public function testKeywordSearchNeedsPhoneSearch(): void
    {
        $this->setValues([
            Config::XML_PATH_PHONE_SEARCH_ENABLED => '0',
            Config::XML_PATH_PHONE_KEYWORD_SEARCH => '1',
        ]);

        $this->assertFalse($this->config->isPhoneKeywordSearchEnabled());
    }

    public function testHistorySettings(): void
    {
        $this->setValues([
            Config::XML_PATH_HISTORY_MATCH_CUSTOMER => '1',
            Config::XML_PATH_HISTORY_MATCH_EMAIL => '0',
            Config::XML_PATH_HISTORY_MATCH_PHONE => '1',
            Config::XML_PATH_HISTORY_RELIABLE_STATUSES => 'processing, complete,,Ready_To_Ship',
            Config::XML_PATH_HISTORY_NEUTRAL_STATUSES => '',
            Config::XML_PATH_HISTORY_REGULAR_MIN_ORDERS => '0',
            Config::XML_PATH_HISTORY_LIMIT => '500',
            Config::XML_PATH_HISTORY_IGNORED_VALUES => " orders@shop.bg \r\n\r\n0888 000 000\n",
        ]);

        $this->assertSame([MatchBy::CUSTOMER, MatchBy::PHONE], $this->config->getHistoryMatchBy());
        $this->assertSame(['processing', 'complete', 'Ready_To_Ship'], $this->config->getReliableStatuses());
        $this->assertSame([], $this->config->getNeutralStatuses());
        $this->assertSame(1, $this->config->getRegularMinOrders());
        $this->assertSame(100, $this->config->getHistoryLimit());
        $this->assertSame(['orders@shop.bg', '0888 000 000'], $this->config->getIgnoredValues());
    }

    public function testDefaultHistoryLimit(): void
    {
        $this->setValues([Config::XML_PATH_HISTORY_LIMIT => '']);

        $this->assertSame(20, $this->config->getHistoryLimit());
    }

    public function testPhoneCountryCodeKeepsDigitsOnly(): void
    {
        $this->setValues([Config::XML_PATH_PHONE_COUNTRY_CODE => ' +359 ']);

        $this->assertSame('359', $this->config->getPhoneCountryCode());
    }
}
