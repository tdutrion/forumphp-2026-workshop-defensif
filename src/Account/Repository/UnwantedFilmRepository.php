<?php

namespace App\Account\Repository;

use App\Account\Entity\UnwantedFilm;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UnwantedFilm>
 */
class UnwantedFilmRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UnwantedFilm::class);
    }

    /**
     * @return array slugs of the films the user does not want to see
     */
    public function findFilmSlugsByUser(string $userId): array
    {
        return $this->createQueryBuilder('u')
            ->select('IDENTITY(u.film) AS slug')
            ->where('u.user = :user')
            ->setParameter('user', $userId, 'uuid')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function findOneByUserAndFilm(string $userId, string $filmSlug): ?UnwantedFilm
    {
        return $this->createQueryBuilder('u')
            ->where('u.user = :user')
            ->andWhere('IDENTITY(u.film) = :film')
            ->setParameter('user', $userId, 'uuid')
            ->setParameter('film', $filmSlug)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
