<?php

declare(strict_types=1);

namespace App\Planner;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Keeps good programmes, pairwise different.
 */
class ProgrammeSelector
{
    /** With a seed, the programmes are drawn among this many best ones (app.planner.draw_pool). */
    public const int POOL = 10;

    public function __construct(#[Autowire('%app.planner.draw_pool%')] private int $pool = self::POOL)
    {
    }

    /**
     * @param ProgrammeList $programmes programmes produced by ChainBuilder::build()
     * @param int|null      $seed       null: the $max best ones; otherwise $max drawn among the pool of best
     *                                  ones, always the same for the same seed (a new seed, other programmes)
     *
     * @return ProgrammeList at most $max programmes, from least wait to most wait (then from least travel to most travel)
     */
    public function select(ProgrammeList $programmes, int $max = 3, ?int $seed = null): ProgrammeList
    {
        \assert($max >= 1, 'At least one programme is asked for.');
        $ranked = $programmes->sortedBy(static fn (Programme $a, Programme $b): int => [$a->wait, $a->distance] <=> [$b->wait, $b->distance]);

        $candidates = new ProgrammeList();
        $workSets = [];
        $wanted = null === $seed ? $max : max($max, $this->pool);
        foreach ($ranked as $programme) {
            // Programmes are different when their works differ (whatever the chain of each film).
            $works = $programme->workIds();
            sort($works);
            $key = implode('|', $works);
            if (isset($workSets[$key])) {
                continue;
            }
            $workSets[$key] = true;
            $candidates = $candidates->with($programme);
            if (\count($candidates) === $wanted) {
                break;
            }
        }

        if (null === $seed || \count($candidates) <= $max) {
            return $candidates->take($max);
        }

        $all = $candidates->toArray();
        $drawn = (new Randomizer(new Xoshiro256StarStar($seed)))->pickArrayKeys($all, $max);

        // pickArrayKeys() keeps the order of the array: the best of the drawn programmes come first.
        return new ProgrammeList(...array_values(array_intersect_key($all, array_flip($drawn))));
    }
}
