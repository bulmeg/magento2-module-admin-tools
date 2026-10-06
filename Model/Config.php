<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Model;

use Bulmeg\AdminTools\Model\Config\Source\StatusColor;
use Bulmeg\AdminTools\Model\CustomerHistory\MatchBy;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;

class Config
{
    public const XML_PATH_PHONE_SEARCH_ENABLED = 'bulmeg_admintools/phone_search/enabled';
    public const XML_PATH_PHONE_KEYWORD_SEARCH = 'bulmeg_admintools/phone_search/keyword_search';
    public const XML_PATH_PHONE_COUNTRY_CODE = 'bulmeg_admintools/phone_search/country_code';
    public const XML_PATH_STATUS_COLOR_ENABLED = 'bulmeg_admintools/status_color/enabled';
    public const XML_PATH_STATUS_COLORS = 'bulmeg_admintools/status_color/colors';
    public const XML_PATH_HISTORY_ENABLED = 'bulmeg_admintools/customer_history/enabled';
    public const XML_PATH_HISTORY_MATCH_CUSTOMER = 'bulmeg_admintools/customer_history/match_customer';
    public const XML_PATH_HISTORY_MATCH_EMAIL = 'bulmeg_admintools/customer_history/match_email';
    public const XML_PATH_HISTORY_MATCH_PHONE = 'bulmeg_admintools/customer_history/match_phone';
    public const XML_PATH_HISTORY_RELIABLE_STATUSES = 'bulmeg_admintools/customer_history/reliable_statuses';
    public const XML_PATH_HISTORY_NEUTRAL_STATUSES = 'bulmeg_admintools/customer_history/neutral_statuses';
    public const XML_PATH_HISTORY_REGULAR_MIN_ORDERS = 'bulmeg_admintools/customer_history/regular_min_orders';
    public const XML_PATH_HISTORY_LIMIT = 'bulmeg_admintools/customer_history/limit';
    public const XML_PATH_HISTORY_IGNORED_VALUES = 'bulmeg_admintools/customer_history/ignored_values';

    private const DEFAULT_HISTORY_LIMIT = 20;
    private const MAX_HISTORY_LIMIT = 100;
    private const STATUS_CODE_MAX_LENGTH = 32;

    private ?array $statusColors = null;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Json $json,
        private readonly StatusColor $statusColor
    ) {
    }

    public function isPhoneSearchEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_PHONE_SEARCH_ENABLED);
    }

    public function isPhoneKeywordSearchEnabled(): bool
    {
        return $this->isPhoneSearchEnabled()
            && $this->scopeConfig->isSetFlag(self::XML_PATH_PHONE_KEYWORD_SEARCH);
    }

    public function getPhoneCountryCode(): string
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_PATH_PHONE_COUNTRY_CODE);

        return (string)preg_replace('/\D+/', '', $value);
    }

    public function isStatusColorEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_STATUS_COLOR_ENABLED);
    }

    public function getStatusColors(): array
    {
        if ($this->statusColors === null) {
            $this->statusColors = $this->readStatusColors();
        }

        return $this->statusColors;
    }

    public function getStatusColor(string $status): ?string
    {
        return $this->getStatusColors()[$status] ?? null;
    }

    public function isCustomerHistoryEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_HISTORY_ENABLED);
    }

    public function getHistoryMatchBy(): array
    {
        $paths = [
            MatchBy::CUSTOMER => self::XML_PATH_HISTORY_MATCH_CUSTOMER,
            MatchBy::EMAIL => self::XML_PATH_HISTORY_MATCH_EMAIL,
            MatchBy::PHONE => self::XML_PATH_HISTORY_MATCH_PHONE,
        ];

        return array_keys(array_filter(
            $paths,
            fn (string $path): bool => $this->scopeConfig->isSetFlag($path)
        ));
    }

    public function getReliableStatuses(): array
    {
        return array_values(array_filter(
            $this->getList(self::XML_PATH_HISTORY_RELIABLE_STATUSES),
            [$this, 'isValidStatusCode']
        ));
    }

    public function getNeutralStatuses(): array
    {
        return array_values(array_filter(
            $this->getList(self::XML_PATH_HISTORY_NEUTRAL_STATUSES),
            [$this, 'isValidStatusCode']
        ));
    }

    public function getRegularMinOrders(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_PATH_HISTORY_REGULAR_MIN_ORDERS));
    }

    public function getHistoryLimit(): int
    {
        $limit = (int)$this->scopeConfig->getValue(self::XML_PATH_HISTORY_LIMIT);

        return $limit > 0 ? min($limit, self::MAX_HISTORY_LIMIT) : self::DEFAULT_HISTORY_LIMIT;
    }

    public function getIgnoredValues(): array
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_PATH_HISTORY_IGNORED_VALUES);
        $lines = preg_split('/[\r\n,;]+/', $value) ?: [];

        return array_values(array_filter(
            array_map('trim', $lines),
            fn (string $line): bool => $line !== ''
        ));
    }

    public function isValidStatusCode(string $status): bool
    {
        return $status !== '' && strlen($status) <= self::STATUS_CODE_MAX_LENGTH;
    }

    private function readStatusColors(): array
    {
        $rows = $this->scopeConfig->getValue(self::XML_PATH_STATUS_COLORS);
        if (is_string($rows) && $rows !== '') {
            try {
                $rows = $this->json->unserialize($rows);
            } catch (\InvalidArgumentException $e) {
                return [];
            }
        }
        if (!is_array($rows)) {
            return [];
        }

        $colors = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $status = (string)($row['status'] ?? '');
            $color = (string)($row['color'] ?? '');
            if ($this->isValidStatusCode($status) && $this->statusColor->isValid($color)) {
                $colors[$status] = $color;
            }
        }

        return $colors;
    }

    private function getList(string $path): array
    {
        $value = (string)$this->scopeConfig->getValue($path);

        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            fn (string $item): bool => $item !== ''
        ));
    }
}
