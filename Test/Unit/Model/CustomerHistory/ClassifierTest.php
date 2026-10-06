<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Test\Unit\Model\CustomerHistory;

use Bulmeg\AdminTools\Model\CustomerHistory\Classifier;
use PHPUnit\Framework\TestCase;

class ClassifierTest extends TestCase
{
    private const RELIABLE = ['processing', 'complete'];

    private Classifier $classifier;

    protected function setUp(): void
    {
        $this->classifier = new Classifier();
    }

    public function testOutcome(): void
    {
        $neutral = ['pending'];

        $this->assertSame(
            Classifier::OUTCOME_SUCCESSFUL,
            $this->classifier->getOutcome('complete', self::RELIABLE, $neutral)
        );
        $this->assertSame(
            Classifier::OUTCOME_SUCCESSFUL,
            $this->classifier->getOutcome('processing', self::RELIABLE, $neutral)
        );
        $this->assertSame(
            Classifier::OUTCOME_NOT_COUNTED,
            $this->classifier->getOutcome('pending', self::RELIABLE, $neutral)
        );
        $this->assertSame(
            Classifier::OUTCOME_PROBLEMATIC,
            $this->classifier->getOutcome('canceled', self::RELIABLE, $neutral)
        );
        $this->assertSame(
            Classifier::OUTCOME_PROBLEMATIC,
            $this->classifier->getOutcome('pending', self::RELIABLE, [])
        );
    }

    public function testVerdict(): void
    {
        $cases = [
            'first order' => [0, 0, 0, 1, Classifier::VERDICT_NEW],
            'one successful order' => [1, 1, 0, 1, Classifier::VERDICT_REGULAR],
            'many successful orders' => [5, 5, 0, 1, Classifier::VERDICT_REGULAR],
            'successful and not counted' => [3, 2, 0, 1, Classifier::VERDICT_REGULAR],
            'only not counted' => [2, 0, 0, 1, Classifier::VERDICT_RETURNING],
            'below the regular threshold' => [2, 2, 0, 3, Classifier::VERDICT_RETURNING],
            'successful and problematic' => [3, 2, 1, 1, Classifier::VERDICT_MIXED],
            'only problematic' => [2, 0, 2, 1, Classifier::VERDICT_UNRELIABLE],
            'problematic and not counted' => [3, 0, 1, 1, Classifier::VERDICT_UNRELIABLE],
        ];
        foreach ($cases as $name => [$total, $successful, $problematic, $regularMinOrders, $expected]) {
            $this->assertSame(
                $expected,
                $this->classifier->getVerdict($total, $successful, $problematic, $regularMinOrders),
                $name
            );
        }
    }
}
