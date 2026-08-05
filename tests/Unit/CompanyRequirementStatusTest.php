<?php

namespace Tests\Unit;

use App\Enums\CompanyRequirementStatus;
use App\Enums\RequirementStatus;
use PHPUnit\Framework\TestCase;

class CompanyRequirementStatusTest extends TestCase
{
    /**
     * fromRequirements() only ever reads ->status, so a plain stand-in
     * avoids booting Eloquent for what is otherwise a pure enum-logic test.
     */
    private function statuses(array $statuses): array
    {
        return array_map(fn (RequirementStatus $status) => new class($status)
        {
            public function __construct(public RequirementStatus $status)
            {
            }
        }, $statuses);
    }

    public function test_all_pending_is_pending(): void
    {
        $requirements = $this->statuses([RequirementStatus::Pending, RequirementStatus::Pending]);

        $this->assertSame(CompanyRequirementStatus::Pending, CompanyRequirementStatus::fromRequirements($requirements));
    }

    public function test_mix_of_pending_and_in_progress_is_partially_processed(): void
    {
        $requirements = $this->statuses([RequirementStatus::Pending, RequirementStatus::InProgress]);

        $this->assertSame(CompanyRequirementStatus::PartiallyProcessed, CompanyRequirementStatus::fromRequirements($requirements));
    }

    public function test_no_pending_and_none_completed_is_processed(): void
    {
        $requirements = $this->statuses([RequirementStatus::InProgress, RequirementStatus::OnHold]);

        $this->assertSame(CompanyRequirementStatus::Processed, CompanyRequirementStatus::fromRequirements($requirements));
    }

    public function test_some_completed_but_not_all_is_partially_done(): void
    {
        $requirements = $this->statuses([RequirementStatus::Completed, RequirementStatus::Pending]);

        $this->assertSame(CompanyRequirementStatus::PartiallyDone, CompanyRequirementStatus::fromRequirements($requirements));
    }

    public function test_some_completed_alongside_in_progress_is_still_partially_done(): void
    {
        $requirements = $this->statuses([RequirementStatus::Completed, RequirementStatus::InProgress]);

        $this->assertSame(CompanyRequirementStatus::PartiallyDone, CompanyRequirementStatus::fromRequirements($requirements));
    }

    public function test_all_completed_is_done(): void
    {
        $requirements = $this->statuses([RequirementStatus::Completed, RequirementStatus::Completed]);

        $this->assertSame(CompanyRequirementStatus::Done, CompanyRequirementStatus::fromRequirements($requirements));
    }

    public function test_no_requirements_defaults_to_pending(): void
    {
        $this->assertSame(CompanyRequirementStatus::Pending, CompanyRequirementStatus::fromRequirements([]));
    }
}
