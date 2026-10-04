<?php

namespace App\Account\Repository;

use App\Account\Entity\SeenFilm;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeenFilm>
 */
class SeenFilmRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeenFilm::class);
    }

    /**
     * @return array slugs of the films seen by the user
     */
    public function findFilmSlugsByUser(string $userId): array
    {
        return $this->createQueryBuilder('s')
            ->select('IDENTITY(s.film) AS slug')
            ->where('s.user = :user')
            ->setParameter('user', $userId, 'uuid')
            ->orderBy('s.seenAt', 'DESC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function findOneByUserAndFilm(string $userId, string $filmSlug): ?SeenFilm
    {
        return $this->createQueryBuilder('s')
            ->where('s.user = :user')
            ->andWhere('IDENTITY(s.film) = :film')
            ->setParameter('user', $userId, 'uuid')
            ->setParameter('film', $filmSlug)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
