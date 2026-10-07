<?php

namespace App\Tests\Unit\Account;

use App\Account\Entity\User;
use App\Account\LastConnection;
use App\Security\VerifiedEmail;
use App\Tests\Builder\IdentityBuilder;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testIsBornWithItsFirstConnection(): void
    {
        // Act
        $user = User::fromProviderIdentity('github', IdentityBuilder::anIdentity()->withId('42')->withEmail('Ada@Example.org')->named('Ada')->build());

        // Assert
        self::assertCount(1, $user->getLinkedAccounts());
        self::assertSame('github', $user->getLinkedAccounts()->first()->getProvider());
        self::assertSame('ada@example.org', $user->getEmail());
        self::assertSame('Ada', $user->getDisplayName());
    }

    public function testAnUnverifiedEmailIsNotTheEmailOfTheAccount(): void
    {
        // Act
        $user = User::fromProviderIdentity('local', IdentityBuilder::anIdentity()->withEmail('ada@example.org')->unverified()->build());

        // Assert
        self::assertNull($user->getEmail());
        self::assertFalse($user->getLinkedAccounts()->first()->isEmailVerified());
    }

    public function testTheLastConnectionCannotBeRemoved(): void
    {
        // Arrange
        $user = User::fromProviderIdentity('github', IdentityBuilder::anIdentity()->build());

        // Assert
        $this->expectException(LastConnection::class);

        // Act
        $user->disconnect($user->getLinkedAccounts()->first());
    }

    public function testAConnectionCanBeRemovedWhenAnotherRemains(): void
    {
        // Arrange
        $user = User::fromProviderIdentity('github', IdentityBuilder::anIdentity()->withId('1')->build());
        $user->connect('google', IdentityBuilder::anIdentity()->withId('2')->build());

        // Act
        $user->disconnect($user->getLinkedAccounts()->first());

        // Assert
        self::assertCount(1, $user->getLinkedAccounts());
        self::assertSame('google', $user->getLinkedAccounts()->first()->getProvider());
    }

    public function testAdoptsAnEmailOnlyWhenItHasNone(): void
    {
        // Arrange
        $withoutEmail = User::fromProviderIdentity('local', IdentityBuilder::anIdentity()->withEmail(null)->build());
        $withEmail = User::fromProviderIdentity('google', IdentityBuilder::anIdentity()->withEmail('ada@example.org')->build());

        // Act
        $withoutEmail->adoptEmail(new VerifiedEmail('Grace@Example.org'));
        $withEmail->adoptEmail(new VerifiedEmail('grace@example.org'));

        // Assert
        self::assertSame('grace@example.org', $withoutEmail->getEmail());
        self::assertSame('ada@example.org', $withEmail->getEmail());
    }
}
