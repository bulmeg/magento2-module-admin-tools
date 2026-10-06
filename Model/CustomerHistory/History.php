<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Model\CustomerHistory;

class History
{
    public function __construct(
        private readonly string $verdict,
        private readonly int $totalCount,
        private readonly int $successfulCount,
        private readonly int $problematicCount,
        private readonly int $notCountedCount,
        private readonly array $problematicByStatus,
        private readonly array $successfulTotals,
        private readonly array $orders,
        private readonly array $truncatedCriteria = []
    ) {
    }

    public function getVerdict(): string
    {
        return $this->verdict;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    public function getSuccessfulCount(): int
    {
        return $this->successfulCount;
    }

    public function getProblematicCount(): int
    {
        return $this->problematicCount;
    }

    public function getNotCountedCount(): int
    {
        return $this->notCountedCount;
    }

    public function getProblematicByStatus(): array
    {
        return $this->problematicByStatus;
    }

    public function getSuccessfulTotals(): array
    {
        return $this->successfulTotals;
    }

    public function getOrders(): array
    {
        return $this->orders;
    }

    public function getTruncatedCriteria(): array
    {
        return $this->truncatedCriteria;
    }
}
