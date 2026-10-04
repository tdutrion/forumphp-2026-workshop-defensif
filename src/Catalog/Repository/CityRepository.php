<?php

namespace App\Catalog\Repository;

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
     * @return array list of ['slug' => ..., 'name' => ...] sorted by name
     */
    public function findAllForSelect(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.slug', 'c.name')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}
