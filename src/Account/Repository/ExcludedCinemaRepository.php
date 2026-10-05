<?php

namespace App\Account\Repository;

use App\Account\Entity\ExcludedCinema;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExcludedCinema>
 */
class ExcludedCinemaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExcludedCinema::class);
    }

    /**
     * @return array slugs of the cinemas the user excluded
     */
    public function findCinemaSlugsByUser(string $userId): array
    {
        return $this->createQueryBuilder('u')
            ->select('IDENTITY(u.cinema) AS slug')
            ->where('u.user = :user')
            ->setParameter('user', $userId, 'uuid')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function findOneByUserAndCinema(string $userId, string $cinemaSlug): ?ExcludedCinema
    {
        return $this->createQueryBuilder('u')
            ->where('u.user = :user')
            ->andWhere('IDENTITY(u.cinema) = :cinema')
            ->setParameter('user', $userId, 'uuid')
            ->setParameter('cinema', $cinemaSlug)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
