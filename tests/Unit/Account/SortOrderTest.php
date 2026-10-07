<?php

declare(strict_types=1);

namespace App\Tests\Unit\Account;

use App\Account\SortOrder;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class SortOrderTest extends TestCase
{
    public function testIsReadFromAUrlValue(): void
    {
        // Assert
        self::assertSame(SortOrder::Asc, SortOrder::from('asc'));
        self::assertSame(SortOrder::Desc, SortOrder::from('desc'));
    }

    #[TestWith(['ASC'])]
    #[TestWith(['up'])]
    #[TestWith([''])]
    public function testRefusesAnythingElse(string $value): void
    {
        // Assert: the URL is case sensitive
        self::assertNull(SortOrder::tryFrom($value));
    }

    public function testGivesTheDirectionTheRepositoriesSpeak(): void
    {
        // Assert
        self::assertSame(\SortDirection::Ascending, SortOrder::Asc->direction());
        self::assertSame(\SortDirection::Descending, SortOrder::Desc->direction());
    }
}
