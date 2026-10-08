<?php

namespace App\Account;

use App\Account\Repository\ExcludedCinemaRepository;
use App\Catalog\Entity\Cinema;
use App\Catalog\Repository\CinemaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Cinemas the user does not want to go to: the planner never uses them.
 */
class ExcludedCinemaService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ExcludedCinemaRepository $excludedCinemaRepository,
        private CinemaRepository $cinemaRepository,
    ) {
    }

    /**
     * @return bool false if the cinema is unknown
     */
    public function exclude(string $userId, string $cinemaSlug): bool
    {
        $cinema = $this->em->find(Cinema::class, $cinemaSlug);
        if (null === $cinema) {
            return false;
        }

        // INSERT IGNORE: two clicks at the same time must not hit the (user, cinema) unique key.
        $this->em->getConnection()->executeStatement(
            'INSERT IGNORE INTO excluded_cinema (id, user_id, cinema_slug, created_at) VALUES (:id, :user, :cinema, :createdAt)',
            [
                'id' => Uuid::v7()->toBinary(),
                'user' => Uuid::fromString($userId)->toBinary(),
                'cinema' => $cinema->slug,
                'createdAt' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            ],
        );

        return true;
    }

    public function include(string $userId, string $cinemaSlug): void
    {
        $excludedCinema = $this->excludedCinemaRepository->findOneByUserAndCinema($userId, $cinemaSlug);
        if (null !== $excludedCinema) {
            $this->em->remove($excludedCinema);
            $this->em->flush();
        }
    }

    /**
     * @return array slugs of the cinemas the user excluded
     */
    public function getExcludedCinemaSlugs(string $userId): array
    {
        return $this->excludedCinemaRepository->findCinemaSlugsByUser($userId);
    }

    /**
     * @return array list of ['slug' => ..., 'name' => ...] sorted by name
     */
    public function listExcludedCinemas(string $userId): array
    {
        return $this->cinemaRepository->findBySlugs($this->getExcludedCinemaSlugs($userId));
    }
}
