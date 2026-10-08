<?php

namespace App\Tests\Unit\Catalog;

use App\Catalog\FilmSlug;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class FilmSlugTest extends TestCase
{
    #[TestWith(['digger-51293'])]
    #[TestWith(['f1'])]
    public function testAcceptsASlug(string $value): void
    {
        // Act
        $slug = new FilmSlug($value);

        // Assert
        self::assertSame($value, $slug->value);
        self::assertSame($value, (string) $slug);
    }

    #[TestWith([''])]
    #[TestWith(['Digger-51293'])]
    #[TestWith(['digger_51293'])]
    #[TestWith(['digger--51293'])]
    #[TestWith(['-digger'])]
    #[TestWith(['../etc/passwd'])]
    public function testRefusesWhatIsNotASlug(string $value): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        new FilmSlug($value);
    }

    public function testRefusesASlugLongerThanTheColumn(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        new FilmSlug(str_repeat('a', FilmSlug::MAX_LENGTH + 1));
    }
}
