<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\ViewModel;

use Bulmeg\AdminTools\Model\Config;
use Bulmeg\AdminTools\Model\Config\Source\OrderStatus;
use Bulmeg\AdminTools\Model\Config\Source\StatusColor;
use Bulmeg\AdminTools\Model\CustomerHistory\Classifier;
use Bulmeg\AdminTools\Model\CustomerHistory\History;
use Bulmeg\AdminTools\Model\CustomerHistory\MatchBy;
use Magento\Directory\Model\Currency;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\Phrase;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class CustomerHistoryFormatter implements ArgumentInterface
{
    private const VERDICT_COLORS = [
        Classifier::VERDICT_NEW => StatusColor::GRAY,
        Classifier::VERDICT_RETURNING => StatusColor::BLUE,
        Classifier::VERDICT_REGULAR => StatusColor::GREEN,
        Classifier::VERDICT_MIXED => StatusColor::ORANGE,
        Classifier::VERDICT_UNRELIABLE => StatusColor::RED,
    ];

    private const VERDICT_MESSAGE_TYPES = [
        Classifier::VERDICT_NEW => 'notice',
        Classifier::VERDICT_RETURNING => 'notice',
        Classifier::VERDICT_REGULAR => 'success',
        Classifier::VERDICT_MIXED => 'warning',
        Classifier::VERDICT_UNRELIABLE => 'error',
    ];

    private array $currencies = [];

    public function __construct(
        private readonly Config $config,
        private readonly TimezoneInterface $timezone,
        private readonly CurrencyFactory $currencyFactory,
        private readonly OrderStatus $orderStatus,
        private readonly MatchBy $matchBy
    ) {
    }

    public function getVerdictLabel(History $history): Phrase
    {
        switch ($history->getVerdict()) {
            case Classifier::VERDICT_REGULAR:
                return __('Regular customer');
            case Classifier::VERDICT_RETURNING:
                return __('Returning customer');
            case Classifier::VERDICT_MIXED:
                return __('Returning customer with problem orders');
            case Classifier::VERDICT_UNRELIABLE:
                return __('Returning customer, but unreliable');
            default:
                return __('New customer');
        }
    }

    public function getVerdictMessage(History $history): Phrase
    {
        switch ($history->getVerdict()) {
            case Classifier::VERDICT_REGULAR:
                return __(
                    'Successful previous orders: %1. You can thank the customer with a discount.',
                    $history->getSuccessfulCount()
                );
            case Classifier::VERDICT_RETURNING:
                return __(
                    'Other orders: %1, none of them problematic, but too few successful ones for a regular customer.',
                    $history->getTotalCount()
                );
            case Classifier::VERDICT_MIXED:
                return __(
                    'Previous orders: %1 successful and %2 problematic. Check them before offering a discount.',
                    $history->getSuccessfulCount(),
                    $history->getProblematicCount()
                );
            case Classifier::VERDICT_UNRELIABLE:
                return __(
                    'Previous orders: %1. None of them was completed successfully.',
                    $history->getTotalCount()
                );
            default:
                return __('This is the first order of the customer.');
        }
    }

    public function getVerdictBadgeClass(History $history): string
    {
        return $this->getBadgeClass(self::VERDICT_COLORS[$history->getVerdict()] ?? StatusColor::GRAY);
    }

    public function getVerdictMessageType(History $history): string
    {
        return self::VERDICT_MESSAGE_TYPES[$history->getVerdict()] ?? 'notice';
    }

    public function getProblemSummary(History $history): string
    {
        $parts = [];
        foreach ($history->getProblematicByStatus() as $status => $count) {
            $parts[] = $this->getStatusLabel((string)$status) . ' × ' . $count;
        }

        return implode(', ', $parts);
    }

    public function getSuccessfulTotal(History $history): string
    {
        $parts = [];
        foreach ($history->getSuccessfulTotals() as $currency => $amount) {
            $parts[] = $this->formatPrice((float)$amount, (string)$currency);
        }

        return implode(' + ', $parts);
    }

    public function formatDate(string $date): string
    {
        try {
            return $this->timezone->formatDateTime($date, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT);
        } catch (\Exception $e) {
            return $date;
        }
    }

    public function formatPrice(float $amount, string $currencyCode): string
    {
        if ($currencyCode === '') {
            return number_format($amount, 2);
        }
        if (!isset($this->currencies[$currencyCode])) {
            $this->currencies[$currencyCode] = $this->currencyFactory->create()->load($currencyCode);
        }
        $currency = $this->currencies[$currencyCode];

        return $currency instanceof Currency
            ? (string)$currency->formatPrecision($amount, 2, [], false)
            : number_format($amount, 2) . ' ' . $currencyCode;
    }

    public function getStatusLabel(string $status): string
    {
        return $this->orderStatus->getLabel($status);
    }

    public function getStatusClass(string $status): string
    {
        if (!$this->config->isStatusColorEnabled()) {
            return '';
        }
        $color = $this->config->getStatusColor($status);

        return $color !== null ? $this->getBadgeClass($color) : '';
    }

    public function getOutcomeLabel(string $outcome): Phrase
    {
        switch ($outcome) {
            case Classifier::OUTCOME_SUCCESSFUL:
                return __('Successful');
            case Classifier::OUTCOME_PROBLEMATIC:
                return __('Problematic');
            default:
                return __('Not counted');
        }
    }

    public function getMatchedByLabel(array $criteria): string
    {
        $labels = $this->matchBy->toArray();
        $parts = [];
        foreach ($criteria as $criterion) {
            if (isset($labels[$criterion])) {
                $parts[] = (string)$labels[$criterion];
            }
        }

        return implode(', ', $parts);
    }

    private function getBadgeClass(string $color): string
    {
        return 'bulmeg-status bulmeg-status-badge bulmeg-status-' . $color;
    }
}
