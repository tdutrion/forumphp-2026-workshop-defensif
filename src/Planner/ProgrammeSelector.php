<?php

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
    public const POOL = 10;

    public function __construct(#[Autowire('%app.planner.draw_pool%')] private int $pool = self::POOL)
    {
    }

    /**
     * @param list<Programme> $programmes programmes produced by ChainBuilder::build()
     * @param int|null        $seed       null: the $max best ones; otherwise $max drawn among the pool of best
     *                                    ones, always the same for the same seed (a new seed, other programmes)
     *
     * @return list<Programme> at most $max programmes, from least wait to most wait (then from least travel to most travel)
     */
    public function select(array $programmes, int $max = 3, ?int $seed = null): array
    {
        $score = static fn (Programme $a, Programme $b) => [$a->wait, $a->distance] <=> [$b->wait, $b->distance];
        usort($programmes, $score);

        $candidates = [];
        $workSets = [];
        $wanted = null === $seed ? $max : max($max, $this->pool);
        foreach ($programmes as $programme) {
            // Programmes are different when their works differ (whatever the chain of each film).
            $works = $programme->workIds();
            sort($works);
            $key = implode('|', $works);
            if (isset($workSets[$key])) {
                continue;
            }
            $workSets[$key] = true;
            $candidates[] = $programme;
            if (\count($candidates) === $wanted) {
                break;
            }
        }

        if (null === $seed || \count($candidates) <= $max) {
            return array_slice($candidates, 0, $max);
        }

        $drawn = (new Randomizer(new Xoshiro256StarStar($seed)))->pickArrayKeys($candidates, $max);

        // pickArrayKeys() keeps the order of the array: the best of the drawn programmes come first.
        return array_values(array_intersect_key($candidates, array_flip($drawn)));
    }
}
