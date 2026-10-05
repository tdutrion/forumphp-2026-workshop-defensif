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

    public function countByUser(string $userId): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.user = :user')
            ->setParameter('user', $userId, 'uuid')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array rows 'slug', 'title', 'posterUrl', 'synopsis', 'markedAt', latest first
     */
    public function findPageByUser(string $userId, int $offset, int $limit): array
    {
        return $this->createQueryBuilder('s')
            ->select('f.slug', 'f.title', 'f.posterUrl', 'f.synopsis', 's.seenAt AS markedAt')
            ->join('s.film', 'f')
            ->where('s.user = :user')
            ->setParameter('user', $userId, 'uuid')
            // UUID v7: the identifier breaks the ties of a same second in the order of creation.
            ->orderBy('s.seenAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
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
