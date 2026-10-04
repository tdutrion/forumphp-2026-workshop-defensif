<?php

namespace App\Catalog\Repository;

use App\Catalog\Entity\Cinema;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cinema>
 */
class CinemaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cinema::class);
    }

    /**
     * Open cinemas whose position is known.
     *
     * @return array list of ['slug', 'name', 'citySlug', 'timezone', 'latitude', 'longitude']
     */
    public function findOpenWithCoordinates(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.slug', 'c.name', 'IDENTITY(c.city) AS citySlug', 'c.timezone', 'c.latitude', 'c.longitude')
            ->where('c.open = true')
            ->andWhere('c.latitude IS NOT NULL')
            ->andWhere('c.longitude IS NOT NULL')
            ->orderBy('c.slug', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @return array list of ['slug', 'name', 'citySlug', 'timezone', 'latitude', 'longitude']
     */
    public function findByCity(string $citySlug): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.slug', 'c.name', 'IDENTITY(c.city) AS citySlug', 'c.timezone', 'c.latitude', 'c.longitude')
            ->where('IDENTITY(c.city) = :city')
            ->andWhere('c.open = true')
            ->setParameter('city', $citySlug)
            ->orderBy('c.slug', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}
