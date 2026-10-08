<?php

declare(strict_types=1);

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
     * @return list<array{slug: string, name: string, citySlug: string, timezone: string, latitude: float, longitude: float}>
     */
    public function findOpenWithCoordinates(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.slug', 'c.name', 'IDENTITY(c.city) AS citySlug', 'c.timezone', 'c.latitude', 'c.longitude')
            ->where('c.open = true')
            ->andWhere('c.latitude IS NOT NULL')
            ->andWhere('c.longitude IS NOT NULL')
            ->orderBy('c.slug', \SortDirection::Ascending)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @return list<array{slug: string, name: string, citySlug: string, timezone: string, latitude: float|null, longitude: float|null}>
     */
    public function findByCity(string $citySlug): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.slug', 'c.name', 'IDENTITY(c.city) AS citySlug', 'c.timezone', 'c.latitude', 'c.longitude')
            ->where('IDENTITY(c.city) = :city')
            ->andWhere('c.open = true')
            ->setParameter('city', $citySlug)
            ->orderBy('c.slug', \SortDirection::Ascending)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @return list<array{slug: string, name: string}> sorted by name
     */
    public function findBySlugs(array $slugs): array
    {
        if ([] === $slugs) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->select('c.slug', 'c.name')
            ->where('c.slug IN (:slugs)')
            ->setParameter('slugs', $slugs)
            ->orderBy('c.name', \SortDirection::Ascending)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Open cinemas, except some, by city: the choices of a cinema picker.
     *
     * @param list<string> $exceptSlugs
     *
     * @return array<string, array<string, string>> city name => [cinema name => cinema slug], sorted by city then cinema
     */
    public function findOpenByCityName(array $exceptSlugs = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.slug', 'c.name', 'city.name AS cityName')
            ->join('c.city', 'city')
            ->where('c.open = true')
            ->orderBy('city.name', \SortDirection::Ascending)
            ->addOrderBy('c.name', \SortDirection::Ascending);
        if ([] !== $exceptSlugs) {
            $qb->andWhere('c.slug NOT IN (:except)')->setParameter('except', $exceptSlugs);
        }

        $byCity = [];
        foreach ($qb->getQuery()->getArrayResult() as $cinema) {
            $byCity[$cinema['cityName']][$cinema['name']] = $cinema['slug'];
        }

        return $byCity;
    }
}
