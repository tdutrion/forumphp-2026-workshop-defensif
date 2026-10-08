<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\Entity\Cinema;
use App\Catalog\Entity\City;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<City>
 */
class CityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, City::class);
    }

    /**
     * @return list<array{slug: string, name: string}> sorted by name
     */
    public function findAllForSelect(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.slug', 'c.name')
            ->orderBy('c.name', \SortDirection::Ascending)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Where each city is, to choose the city nearest to a browser position: the centre of its open cinemas.
     *
     * @return list<array{slug: string, latitude: float, longitude: float}> cities without a located cinema left out
     */
    public function findCentres(): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(cinema.city) AS slug', 'AVG(cinema.latitude) AS latitude', 'AVG(cinema.longitude) AS longitude')
            ->from(Cinema::class, 'cinema')
            ->where('cinema.open = true')
            ->andWhere('cinema.latitude IS NOT NULL')
            ->andWhere('cinema.longitude IS NOT NULL')
            ->groupBy('cinema.city')
            ->orderBy('slug', \SortDirection::Ascending)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'slug' => $row['slug'],
            'latitude' => round((float) $row['latitude'], 6),
            'longitude' => round((float) $row['longitude'], 6),
        ], $rows);
    }
}
