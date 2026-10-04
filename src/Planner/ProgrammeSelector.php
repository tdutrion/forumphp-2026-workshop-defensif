<?php

namespace App\Planner;

/**
 * Keeps the best programmes, pairwise different.
 */
class ProgrammeSelector
{
    /**
     * @param array $programmes programmes produced by ChainBuilder::build()
     *
     * @return array at most $max programmes, from least wait to most wait (then from least travel to most travel)
     */
    public function select(array $programmes, int $max = 3): array
    {
        usort($programmes, static fn (array $a, array $b) => [$a['wait'], $a['distance']] <=> [$b['wait'], $b['distance']]);

        $selected = [];
        $filmSets = [];
        foreach ($programmes as $programme) {
            $films = array_column($programme['showtimes'], 'filmSlug');
            sort($films);
            $key = implode('|', $films);
            if (isset($filmSets[$key])) {
                continue;
            }
            $filmSets[$key] = true;
            $selected[] = $programme;
            if (\count($selected) === $max) {
                break;
            }
        }

        return $selected;
    }
}
