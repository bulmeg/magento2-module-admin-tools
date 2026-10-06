<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Model\CustomerHistory;

class Classifier
{
    public const VERDICT_NEW = 'new';
    public const VERDICT_REGULAR = 'regular';
    public const VERDICT_RETURNING = 'returning';
    public const VERDICT_MIXED = 'mixed';
    public const VERDICT_UNRELIABLE = 'unreliable';

    public const OUTCOME_SUCCESSFUL = 'successful';
    public const OUTCOME_PROBLEMATIC = 'problematic';
    public const OUTCOME_NOT_COUNTED = 'not_counted';

    public function getOutcome(string $status, array $reliableStatuses, array $neutralStatuses): string
    {
        if (in_array($status, $reliableStatuses, true)) {
            return self::OUTCOME_SUCCESSFUL;
        }
        if (in_array($status, $neutralStatuses, true)) {
            return self::OUTCOME_NOT_COUNTED;
        }

        return self::OUTCOME_PROBLEMATIC;
    }

    public function getVerdict(int $total, int $successful, int $problematic, int $regularMinOrders): string
    {
        if ($total <= 0) {
            return self::VERDICT_NEW;
        }
        if ($problematic > 0) {
            return $successful > 0 ? self::VERDICT_MIXED : self::VERDICT_UNRELIABLE;
        }

        return $successful >= max(1, $regularMinOrders) ? self::VERDICT_REGULAR : self::VERDICT_RETURNING;
    }
}
