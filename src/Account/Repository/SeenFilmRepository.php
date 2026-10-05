<?php

namespace App\Account\Repository;

use App\Account\Entity\SeenFilm;
use App\Catalog\Entity\Film;
use App\Catalog\Entity\Work;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

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
     * @return array slugs of every film, any chain, whose work the user marked (latest first)
     */
    public function findFilmSlugsByUser(string $userId): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('f.slug')
            ->from(SeenFilm::class, 's')
            ->join(Film::class, 'f', 'WITH', 'f.work = s.work')
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
     * @return array rows 'slug', 'title', 'posterUrl', 'synopsis', 'markedAt': one per work (its first film), latest first
     */
    public function findPageByUser(string $userId, int $offset, int $limit): array
    {
        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT slug, title, posterUrl, synopsis, markedAt FROM (
                 SELECT f.slug, f.title, f.poster_url AS posterUrl, f.synopsis, m.seen_at AS markedAt, m.id AS markId,
                        ROW_NUMBER() OVER (PARTITION BY m.id ORDER BY f.slug) AS position
                 FROM seen_film m INNER JOIN film f ON f.work_id = m.work_id
                 WHERE m.user_id = :user
             ) marks
             WHERE position = 1
             ORDER BY markedAt DESC, markId DESC
             LIMIT '.$limit.' OFFSET '.$offset,
            ['user' => Uuid::fromString($userId)->toBinary()],
        );
    }

    public function findOneByUserAndWork(string $userId, Work $work): ?SeenFilm
    {
        return $this->findOneBy(['user' => Uuid::fromString($userId), 'work' => $work]);
    }
}
