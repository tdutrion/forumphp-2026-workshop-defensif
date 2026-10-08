<?php

declare(strict_types=1);

namespace App\Account\Repository;

use App\Account\Entity\LinkedAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LinkedAccount>
 */
class LinkedAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LinkedAccount::class);
    }

    public function findOneByProviderIdentity(string $provider, string $providerUserId): ?LinkedAccount
    {
        return $this->findOneBy(['provider' => $provider, 'providerUserId' => $providerUserId]);
    }
}
