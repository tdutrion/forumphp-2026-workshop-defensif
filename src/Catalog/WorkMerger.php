<?php

namespace App\Catalog;

use App\Catalog\Entity\Film;
use App\Catalog\Entity\Work;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Two works that turn out to be the same film become one: the oldest stays and receives the films,
 * the seen and the not for me marks of the other one (one mark per user and work, the oldest kept).
 */
class WorkMerger
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function merge(Work $a, Work $b): Work
    {
        [$kept, $absorbed] = [$a->getCreatedAt(), (string) $a->getId()] <= [$b->getCreatedAt(), (string) $b->getId()] ? [$a, $b] : [$b, $a];
        $this->em->flush();
        $connection = $this->em->getConnection();
        $params = ['kept' => $kept->getId()->toBinary(), 'absorbed' => $absorbed->getId()->toBinary()];

        $connection->transactional(function () use ($connection, $params): void {
            $connection->executeStatement('UPDATE film SET work_id = :kept WHERE work_id = :absorbed', $params);
            foreach (['seen_film' => 'seen_at', 'unwanted_film' => 'created_at'] as $table => $date) {
                // A user who marked both works keeps the oldest mark.
                $connection->executeStatement(
                    "DELETE a FROM $table a INNER JOIN $table b ON b.user_id = a.user_id
                     WHERE a.work_id = :absorbed AND b.work_id = :kept AND b.$date <= a.$date",
                    $params,
                );
                $connection->executeStatement(
                    "DELETE b FROM $table a INNER JOIN $table b ON b.user_id = a.user_id
                     WHERE a.work_id = :absorbed AND b.work_id = :kept",
                    $params,
                );
                $connection->executeStatement("UPDATE $table SET work_id = :kept WHERE work_id = :absorbed", $params);
            }
            $connection->executeStatement('DELETE FROM work WHERE id = :absorbed', $params);
        });

        // The films already loaded (a synchronization goes on with them) follow the database;
        // the absorbed work leaves the entity manager without clearing the rest.
        foreach ($this->em->getUnitOfWork()->getIdentityMap()[Film::class] ?? [] as $film) {
            if ($film->getWork() === $absorbed) {
                $film->setWork($kept);
            }
        }
        $this->em->detach($absorbed);

        return $kept;
    }
}
