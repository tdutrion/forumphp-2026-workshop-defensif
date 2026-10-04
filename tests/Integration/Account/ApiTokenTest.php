<?php

namespace App\Tests\Integration\Account;

use App\Account\ApiTokenService;
use App\Account\Entity\ApiToken;
use App\Tests\Builder\UserBuilder;
use App\Tests\StoresEntities;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ApiTokenTest extends KernelTestCase
{
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
}
