<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Plugin\OrderGrid;

use Bulmeg\AdminTools\Model\Config;
use Bulmeg\AdminTools\Model\Phone\Normalizer;
use Bulmeg\AdminTools\Model\ResourceModel\OrderPhone;
use Magento\Framework\Api\Filter;
use Magento\Framework\Data\Collection;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\View\Element\UiComponent\DataProvider\FulltextFilter;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as OrderGridCollection;

class KeywordSearchPlugin
{
    public function __construct(
        private readonly Config $config,
        private readonly Normalizer $normalizer,
        private readonly OrderPhone $orderPhone
    ) {
    }

    public function aroundApply(
        FulltextFilter $subject,
        callable $proceed,
        Collection $collection,
        Filter $filter
    ): mixed {
        if (!$collection instanceof OrderGridCollection || !$this->config->isPhoneKeywordSearchEnabled()) {
            return $proceed($collection, $filter);
        }
        $digits = $this->normalizer->getKeywordDigits((string)$filter->getValue());
        if ($digits === null) {
            return $proceed($collection, $filter);
        }

        $collection->getSelect()->where(
            'main_table.entity_id IN (?)',
            new Expression((string)$this->orderPhone->getOrderIdsSelect($digits)->assemble())
        );

        return null;
    }
}
