<?php

namespace App\Tests\Unit\Planner;

use App\Planner\PlanFailure;
use App\Planner\PlanNotice;
use App\Planner\PlanResult;
use App\Planner\ProgrammeList;
use App\Tests\Builder\ProgrammeBuilder;
use PHPUnit\Framework\TestCase;

final class PlanResultTest extends TestCase
{
    public function testASuccessCarriesItsProgrammes(): void
    {
        // Arrange
        $programmes = new ProgrammeList([ProgrammeBuilder::aProgramme()->waiting(0)->build(), ProgrammeBuilder::aProgramme()->waiting(5)->build()]);

        // Act
        $result = PlanResult::success($programmes, films: 2, seed: 42);

        // Assert
        self::assertTrue($result->isSuccess());
        self::assertCount(2, $result->programmes);
        self::assertNull($result->failure);
        self::assertNull($result->reason());
        self::assertSame(42, $result->seed);
    }

    public function testASuccessCanCarryARemark(): void
    {
        // Act
        $result = PlanResult::success(new ProgrammeList([ProgrammeBuilder::aProgramme()->build()]), films: 1, seed: 1, notice: PlanNotice::FewerFilms);

        // Assert
        self::assertTrue($result->isSuccess());
        self::assertSame('fewer_films', $result->reason());
    }

    public function testAFailureHasNoProgramme(): void
    {
        // Act
        $result = PlanResult::failure(PlanFailure::NoShowtime);

        // Assert
        self::assertFalse($result->isSuccess());
        self::assertTrue($result->programmes->isEmpty());
        self::assertSame('no_showtime', $result->reason());
        self::assertNull($result->seed);
    }

    public function testASuccessWithoutProgrammeIsRefused(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        PlanResult::success(new ProgrammeList(), films: 2, seed: 1);
    }

    public function testEveryFailureHasATranslationKey(): void
    {
        foreach (PlanFailure::cases() as $failure) {
            self::assertStringStartsWith('planner.result.', $failure->messageKey());
        }
    }
}
