<?php

namespace App\Tests\Unit\Security;

use App\Security\UnverifiedEmail;
use App\Security\UserInfo;
use App\Security\VerifiedEmail;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class UserInfoTest extends TestCase
{
    public function testHoldsWhatAProviderSays(): void
    {
        // Act
        $info = new UserInfo(id: '42', email: new VerifiedEmail('Ada@Example.org'), name: 'Ada');

        // Assert
        self::assertSame('42', $info->id);
        self::assertInstanceOf(VerifiedEmail::class, $info->email);
        self::assertSame('ada@example.org', $info->email->value, 'compared in lower case');
    }

    public function testAnIdentityHasAnId(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        new UserInfo(id: '', email: null, name: null);
    }

    public function testTheEmailCanBeMadeUnverifiedWithoutChangingTheOriginal(): void
    {
        // Arrange
        $info = new UserInfo(id: '42', email: new VerifiedEmail('ada@example.org'), name: 'Ada');

        // Act
        $unverified = $info->withUnverifiedEmail();

        // Assert
        self::assertInstanceOf(UnverifiedEmail::class, $unverified->email);
        self::assertSame('ada@example.org', $unverified->email->value);
        self::assertInstanceOf(VerifiedEmail::class, $info->email);
    }

    public function testAnIdentityWithoutEmailStaysWithoutEmail(): void
    {
        // Act
        $info = (new UserInfo(id: '42', email: null, name: null))->withUnverifiedEmail();

        // Assert
        self::assertNull($info->email);
    }

    #[TestWith([''])]
    #[TestWith(['ada'])]
    #[TestWith(['ada@'])]
    #[TestWith(['a da@example.org'])]
    public function testAnEmailIsAnAddress(string $value): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        new UnverifiedEmail($value);
    }

    public function testAccentsAreKept(): void
    {
        // Act
        $email = new VerifiedEmail('JOSÉ@corp.example');

        // Assert
        self::assertSame('josé@corp.example', $email->value, 'josé@ is not jose@');
    }
}
