<?php

declare(strict_types=1);

namespace App\Tests\Integration\Account;

use App\Account\ApiTokenService;
use App\Account\Entity\ApiToken;
use App\Tests\Builder\UserBuilder;
use App\Tests\StoresEntities;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ApiTokenTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use StoresEntities;

    public function testATokenIdentifiesItsOwnerUntilItIsRevokedOrExpires(): void
    {
        // Arrange
        self::bootKernel();
        $tokens = self::getContainer()->get(ApiTokenService::class);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $ada = UserBuilder::aUser()->build();
        $this->store($ada);
        $valid = $tokens->create($ada, 'Phone');
        $revoked = $tokens->create($ada, 'Old phone');
        $expired = $tokens->create($ada, 'Laptop');
        $tokens->revoke($ada, $em->getRepository(ApiToken::class)->findOneBy(['tokenHash' => hash('sha256', $revoked)])->getId()->toRfc4122());
        $em->getRepository(ApiToken::class)->findOneBy(['tokenHash' => hash('sha256', $expired)])->setExpiresAt(new \DateTimeImmutable('-1 minute'));
        $em->flush();

        // Act
        $owner = $tokens->findUserByToken($valid);
        $revokedOwner = $tokens->findUserByToken($revoked);
        $expiredOwner = $tokens->findUserByToken($expired);

        // Assert
        self::assertTrue($ada->getId()->equals($owner->getId()));
        self::assertNull($revokedOwner);
        self::assertNull($expiredOwner);
        self::assertNull($em->getRepository(ApiToken::class)->findOneBy(['tokenHash' => $valid]), 'the plaintext token is never stored');
    }

    public function testATokenLastsNinetyDays(): void
    {
        // Arrange
        self::bootKernel();
        self::mockTime('2030-01-10 12:00:00 UTC');
        $tokens = self::getContainer()->get(ApiTokenService::class);
        $ada = UserBuilder::aUser()->build();
        $this->store($ada);
        $token = $tokens->create($ada, 'Phone');

        // Act
        self::mockTime('2030-04-10 11:59:59 UTC');
        $lastSecond = $tokens->findUserByToken($token);
        self::mockTime('2030-04-10 12:00:00 UTC');
        $expired = $tokens->findUserByToken($token);

        // Assert
        self::assertNotNull($lastSecond, 'still valid one second before the 90th day ends');
        self::assertNull($expired);
        self::assertSame([], $tokens->listForUser($ada), 'an expired token is no longer listed');
    }
}
