<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Entity\Work;
use PHPUnit\Framework\TestCase;

final class WorkFingerprintTest extends TestCase
{
    public function testTheSameFilmGivesTheSameFingerprintWhateverItsSpelling(): void
    {
        // Arrange
        $pathe = Work::fingerprintOf("L'Odyssée", 2026, ['Christopher Nolan']);

        // Act
        $other = Work::fingerprintOf('l odyssee !', 2026, ['christopher  NOLAN']);

        // Assert
        self::assertSame('l odyssee|2026|nolan', $pathe);
        self::assertSame($pathe, $other);
    }

    public function testNoFingerprintWithoutTitleYearAndDirector(): void
    {
        // Act and assert: a part unknown, no fingerprint (two different films must never merge).
        self::assertNull(Work::fingerprintOf('Cars', null, ['John Lasseter']));
        self::assertNull(Work::fingerprintOf('Cars', 2006, null));
        self::assertNull(Work::fingerprintOf('Cars', 2006, []));
        self::assertNull(Work::fingerprintOf('  ', 2006, ['John Lasseter']));
    }
}
