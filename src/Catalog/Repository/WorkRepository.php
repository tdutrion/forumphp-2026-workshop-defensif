<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\Entity\Film;
use App\Catalog\Entity\Work;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Work>
 */
class WorkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Work::class);
    }

    public function findOneByWikidataId(string $wikidataId): ?Work
    {
        return $this->findOneBy(['wikidataId' => $wikidataId]);
    }

    /**
     * A work with this fingerprint whose films come from another chain than those of $except.
     */
    public function findOtherChainWorkByFingerprint(string $fingerprint, Work $except): ?Work
    {
        return $this->createQueryBuilder('w')
            ->join(Film::class, 'f', 'WITH', 'f.work = w')
            ->where('w.fingerprint = :fingerprint')
            ->andWhere('w.id != :except')
            ->andWhere('f.chain NOT IN (SELECT DISTINCT f2.chain FROM '.Film::class.' f2 WHERE f2.work = :except)')
            ->setParameter('fingerprint', $fingerprint)
            ->setParameter('except', $except->getId(), 'uuid')
            ->orderBy('w.createdAt')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<string> titles of the films of a work (one per chain), to search Wikidata
     */
    public function findFilmTitles(Work $work): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT f.title')
            ->from(Film::class, 'f')
            ->where('f.work = :work')
            ->setParameter('work', $work->getId(), 'uuid')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * @return list<Work> works of the given films (by slug), each once
     */
    public function findWorksOfFilms(array $slugs): array
    {
        if ([] === $slugs) {
            return [];
        }

        return $this->createQueryBuilder('w')
            ->join(Film::class, 'f', 'WITH', 'f.work = w')
            ->where('f.slug IN (:slugs)')
            ->setParameter('slugs', $slugs)
            ->distinct()
            ->getQuery()
            ->getResult();
    }
}
