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

    public function countByUser(string $userId): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.user = :user')
            ->setParameter('user', $userId, 'uuid')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array rows 'slug', 'title', 'posterUrl', 'synopsis', 'markedAt', latest first
     */
    public function findPageByUser(string $userId, int $offset, int $limit): array
    {
        return $this->createQueryBuilder('u')
            ->select('f.slug', 'f.title', 'f.posterUrl', 'f.synopsis', 'u.createdAt AS markedAt')
            ->join('u.film', 'f')
            ->where('u.user = :user')
            ->setParameter('user', $userId, 'uuid')
            // UUID v7: the identifier breaks the ties of a same second in the order of creation.
            ->orderBy('u.createdAt', 'DESC')
            ->addOrderBy('u.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
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
