<?php

namespace App\Tests\Unit\Security;

use App\Security\UserInfo;
use PHPUnit\Framework\TestCase;

final class UserInfoTest extends TestCase
{
    public function testHoldsWhatAProviderSays(): void
    {
        // Act
        $info = new UserInfo(id: '42', email: 'ada@example.org', emailVerified: true, name: 'Ada');

        // Assert
        self::assertSame('42', $info->id);
        self::assertTrue($info->emailVerified);
    }

    public function testAnIdentityHasAnId(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        new UserInfo(id: '', email: null, emailVerified: false, name: null);
    }

    public function testThereIsNoEmailToVerifyWithoutAnEmail(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        new UserInfo(id: '42', email: null, emailVerified: true, name: null);
    }

    public function testTheEmailCanBeMadeUnverifiedWithoutChangingTheOriginal(): void
    {
        // Arrange
        $info = new UserInfo(id: '42', email: 'ada@example.org', emailVerified: true, name: 'Ada');

        // Act
        $unverified = $info->withUnverifiedEmail();

        // Assert
        self::assertFalse($unverified->emailVerified);
        self::assertSame('ada@example.org', $unverified->email);
        self::assertTrue($info->emailVerified);
    }
}
