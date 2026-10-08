<?php

declare(strict_types=1);

namespace App\Tests\Unit\Sdk\Pathe;

use App\Sdk\Pathe\ShowSlug;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ShowSlugTest extends TestCase
{
    public function testKeepsAPatheSlug(): void
    {
        // Act
        $slug = new ShowSlug('digger-51293');

        // Assert
        self::assertSame('digger-51293', $slug->value);
    }

    #[TestWith([''])]
    #[TestWith(['Digger'])]
    #[TestWith(['digger 51293'])]
    #[TestWith(['digger/51293'])]
    #[TestWith(['-digger'])]
    public function testRefusesWhatIsNotASlug(string $value): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        new ShowSlug($value);
    }
}
