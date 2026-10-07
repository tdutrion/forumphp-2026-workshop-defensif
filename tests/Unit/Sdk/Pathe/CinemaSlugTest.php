<?php

declare(strict_types=1);

namespace App\Tests\Unit\Sdk\Pathe;

use App\Sdk\Pathe\CinemaSlug;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class CinemaSlugTest extends TestCase
{
    public function testKeepsAPatheSlug(): void
    {
        // Act
        $slug = new CinemaSlug('cinema-pathe-dijon');

        // Assert
        self::assertSame('cinema-pathe-dijon', $slug->value);
    }

    #[TestWith([''])]
    #[TestWith(['Cinema Pathé Dijon'])]
    #[TestWith(['cinema-pathe-dijon/shows'])]
    #[TestWith(['cinema--pathe'])]
    public function testRefusesWhatIsNotASlug(string $value): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        new CinemaSlug($value);
    }
}
