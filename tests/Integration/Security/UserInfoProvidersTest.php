<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Security\UserInfo\GithubUserInfoProvider;
use App\Security\UserInfo\LocalUserInfoProvider;
use App\Security\UserInfo\OpenIdConnectUserInfoProvider;
use App\Security\UserInfoProviders;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserInfoProvidersTest extends KernelTestCase
{
    #[TestWith(['github', GithubUserInfoProvider::class])]
    #[TestWith(['local', LocalUserInfoProvider::class])]
    #[TestWith(['google', OpenIdConnectUserInfoProvider::class])]
    #[TestWith(['keycloak', OpenIdConnectUserInfoProvider::class])]
    #[TestWith(['linkedin', OpenIdConnectUserInfoProvider::class])]
    public function testEachProviderIsReadByItsOwnClassOrAsOpenIdConnect(string $name, string $expected): void
    {
        // Arrange
        self::bootKernel();
        $providers = self::getContainer()->get(UserInfoProviders::class);

        // Act
        $provider = $providers->for($name);

        // Assert
        self::assertInstanceOf($expected, $provider);
    }

    public function testEachProviderAsksForItsOwnScopes(): void
    {
        // Arrange
        self::bootKernel();
        $providers = self::getContainer()->get(UserInfoProviders::class);

        // Act
        $github = $providers->for('github')->scopes();
        $google = $providers->for('google')->scopes();

        // Assert
        self::assertSame(['read:user', 'user:email'], $github);
        self::assertSame(['openid', 'email', 'profile'], $google);
    }
}
