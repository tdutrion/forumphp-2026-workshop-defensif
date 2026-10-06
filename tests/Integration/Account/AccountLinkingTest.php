<?php

namespace App\Tests\Integration\Account;

use App\Account\AccountService;
use App\Account\Entity\User;
use App\Tests\Builder\IdentityBuilder;
use App\Tests\Builder\UserBuilder;
use App\Tests\StoresEntities;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AccountLinkingTest extends KernelTestCase
{
    use StoresEntities;

    private function accounts(): AccountService
    {
        return self::getContainer()->get(AccountService::class);
    }

    public function testTheFirstSignInCreatesAnAccountThatTheNextSignInReuses(): void
    {
        // Arrange
        self::bootKernel();
        $identity = IdentityBuilder::anIdentity()->withId('42')->build();

        // Act
        $first = $this->accounts()->loginWithProvider('github', $identity);
        $second = $this->accounts()->loginWithProvider('github', $identity);

        // Assert
        self::assertTrue($first->getId()->equals($second->getId()));
        self::assertSame('ada@example.org', $first->getEmail());
        self::assertSame(1, self::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->count([]));
    }

    public function testAVerifiedEmailLinksANewProviderToTheExistingAccount(): void
    {
        // Arrange
        self::bootKernel();
        $ada = UserBuilder::aUser()->withEmail('ada@example.org')->linkedTo('github', '42')->build();
        $this->store($ada);

        // Act
        $user = $this->accounts()->loginWithProvider('google', IdentityBuilder::anIdentity()->withId('g-7')->withEmail('ada@example.org')->build());

        // Assert
        self::assertTrue($ada->getId()->equals($user->getId()));
        self::assertCount(2, $user->getLinkedAccounts());
    }

    public function testAnUnverifiedEmailNeverLinksToAnExistingAccount(): void
    {
        // Arrange
        self::bootKernel();
        $ada = UserBuilder::aUser()->withEmail('ada@example.org')->linkedTo('github', '42')->build();
        $this->store($ada);

        // Act
        $user = $this->accounts()->loginWithProvider('local', IdentityBuilder::anIdentity()->withId('x')->withEmail('ada@example.org')->unverified()->build());

        // Assert
        self::assertFalse($ada->getId()->equals($user->getId()));
        self::assertNull($user->getEmail(), 'an unverified email is not copied to the account');
    }

    public function testAnEmailThatOnlyDiffersByAnAccentIsAnotherAccount(): void
    {
        // Arrange
        self::bootKernel();
        $jose = UserBuilder::aUser()->withEmail('jose@corp.example')->linkedTo('github', '42')->build();
        $this->store($jose);

        // Act
        $user = $this->accounts()->loginWithProvider('google', IdentityBuilder::anIdentity()->withId('g-7')->withEmail('josé@corp.example')->build());

        // Assert
        self::assertFalse($jose->getId()->equals($user->getId()));
        self::assertSame('josé@corp.example', $user->getEmail());
        self::assertCount(1, $jose->getLinkedAccounts());
    }

    public function testEmailsAreComparedAndStoredInLowerCase(): void
    {
        // Arrange
        self::bootKernel();
        $ada = UserBuilder::aUser()->withEmail('ada@example.org')->linkedTo('github', '42')->build();
        $this->store($ada);

        // Act
        $user = $this->accounts()->loginWithProvider('google', IdentityBuilder::anIdentity()->withId('g-7')->withEmail('Ada@Example.org')->build());
        $grace = $this->accounts()->loginWithProvider('google', IdentityBuilder::anIdentity()->withId('g-8')->withEmail('Grace@Example.org')->build());

        // Assert
        self::assertTrue($ada->getId()->equals($user->getId()));
        self::assertSame('grace@example.org', $grace->getEmail());
    }

    public function testAnIdentityAlreadyLinkedToAnotherAccountIsRefused(): void
    {
        // Arrange
        self::bootKernel();
        $ada = UserBuilder::aUser()->withEmail('ada@example.org')->linkedTo('local', 'ada')->build();
        $grace = UserBuilder::aUser()->withEmail('grace@example.org')->linkedTo('github', '42')->build();
        $this->store($ada, $grace);

        // Act
        $linked = $this->accounts()->linkProvider($ada, 'github', IdentityBuilder::anIdentity()->withId('42')->withEmail('grace@example.org')->build());

        // Assert
        self::assertFalse($linked);
        self::assertCount(1, $ada->getLinkedAccounts());
    }

    public function testTheLastConnectionCannotBeRemoved(): void
    {
        // Arrange
        self::bootKernel();
        $ada = UserBuilder::aUser()->linkedTo('local', 'ada')->build();
        $this->store($ada);
        $onlyConnection = $ada->getLinkedAccounts()->first()->getId()->toRfc4122();

        // Act
        $removed = $this->accounts()->removeLinkedAccount($ada, $onlyConnection);

        // Assert
        self::assertFalse($removed);
        self::assertCount(1, $ada->getLinkedAccounts());
    }
}
