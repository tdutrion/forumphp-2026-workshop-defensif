<?php

declare(strict_types=1);

namespace App\Sdk\Wikidata;

/**
 * Turns raw answers of the Wikidata action API into arrays. External ids end up in links:
 * anything else than the expected formats is dropped.
 */
class WikidataMapper
{
    /**
     * @param array<string, mixed> $raw
     *
     * @return list<string> item ids of a wbsearchentities answer
     */
    public function searchIds(array $raw): array
    {
        $ids = [];
        foreach ($raw['search'] ?? [] as $hit) {
            $id = $hit['id'] ?? null;
            if (is_string($id) && 1 === preg_match('/^Q\d+$/', $id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $entity
     *
     * @return array{types: list<string>, years: list<int>, directorIds: list<string>, imdbId: ?string, tmdbId: ?string} Q ids, years, IMDb and TMDB ids
     */
    public function film(array $entity): array
    {
        $claims = $entity['claims'] ?? [];
        $years = [];
        foreach ($this->values($claims, 'P577') as $time) {
            if (is_array($time) && 1 === preg_match('/^[+-](\d{4})-/', (string) ($time['time'] ?? ''), $matches)) {
                $years[] = (int) $matches[1];
            }
        }

        return [
            'types' => $this->entityIds($claims, 'P31'),
            'years' => array_values(array_unique($years)),
            'directorIds' => $this->entityIds($claims, 'P57'),
            'imdbId' => $this->firstMatching($this->values($claims, 'P345'), '/^tt\d+$/'),
            'tmdbId' => $this->firstMatching($this->values($claims, 'P4947'), '/^\d+$/'),
        ];
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return array<string, string> [id => label] of a wbgetentities answer with labels (English, else French)
     */
    public function labels(array $raw): array
    {
        $labels = [];
        foreach ($raw['entities'] ?? [] as $id => $entity) {
            $label = $entity['labels']['en']['value'] ?? $entity['labels']['fr']['value'] ?? null;
            if (is_string($label)) {
                $labels[$id] = $label;
            }
        }

        return $labels;
    }

    private function values(array $claims, string $property): array
    {
        $values = [];
        foreach ($claims[$property] ?? [] as $claim) {
            if (array_key_exists('value', $claim['mainsnak']['datavalue'] ?? [])) {
                $values[] = $claim['mainsnak']['datavalue']['value'];
            }
        }

        return $values;
    }

    private function entityIds(array $claims, string $property): array
    {
        $ids = [];
        foreach ($this->values($claims, $property) as $value) {
            $id = is_array($value) ? ($value['id'] ?? null) : null;
            if (is_string($id) && 1 === preg_match('/^Q\d+$/', $id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function firstMatching(array $values, string $pattern): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && 1 === preg_match($pattern, $value)) {
                return $value;
            }
        }

        return null;
    }
}
