<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Persists entities built by the test builders. The kernel must already be booted.
 */
trait StoresEntities
{
    protected function store(object ...$entities): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        foreach ($entities as $entity) {
            $em->persist($entity);
        }
        $em->flush();
    }
}
