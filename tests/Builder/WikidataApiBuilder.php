<?php

namespace App\Tests\Builder;

/**
 * Answers of the Wikidata action API for FakeWikidataApi: films and their directors.
 */
final class WikidataApiBuilder
{
    private array $films = [];
    private array $people = [];
    private bool $failing = false;
    private bool $garbage = false;

    public static function aWikidataApi(): self
    {
        return new self();
    }

    public function withFilm(string $id, string $label, ?int $year, array $directors = [], ?string $imdbId = null, ?string $tmdbId = null, string $type = 'Q11424'): self
    {
        $clone = clone $this;
        $directorIds = [];
        foreach ($directors as $name) {
            $personId = 'Q9'.abs(crc32($name));
            $clone->people[$personId] = $name;
            $directorIds[] = $personId;
        }
        $clone->films[$id] = compact('label', 'year', 'directorIds', 'imdbId', 'tmdbId', 'type');

        return $clone;
    }

    public function failing(): self
    {
        $clone = clone $this;
        $clone->failing = true;

        return $clone;
    }

    public function answeringGarbage(): self
    {
        $clone = clone $this;
        $clone->garbage = true;

        return $clone;
    }

    /**
     * @return array{0: string, 1: int} JSON body and HTTP status for the query parameters of a request
     */
    public function answer(array $query): array
    {
        if ($this->failing) {
            return ['{"error":"unavailable"}', 503];
        }
        if ($this->garbage) {
            return ['<html>Something went wrong</html>', 200];
        }

        if ('wbsearchentities' === ($query['action'] ?? null)) {
            $search = mb_strtolower((string) ($query['search'] ?? ''));
            $hits = [];
            foreach ($this->films as $id => $film) {
                if (str_contains(mb_strtolower($film['label']), $search)) {
                    $hits[] = ['id' => $id, 'label' => $film['label']];
                }
            }

            return [json_encode(['search' => $hits], \JSON_THROW_ON_ERROR), 200];
        }

        $entities = [];
        foreach (explode('|', (string) ($query['ids'] ?? '')) as $id) {
            if (isset($this->films[$id])) {
                $film = $this->films[$id];
                $claim = static fn (string $type, mixed $value): array => ['mainsnak' => ['datavalue' => ['type' => $type, 'value' => $value]]];
                $claims = ['P31' => [$claim('wikibase-entityid', ['id' => $film['type']])]];
                if (null !== $film['year']) {
                    $claims['P577'] = [$claim('time', ['time' => '+'.$film['year'].'-01-01T00:00:00Z'])];
                }
                $claims['P57'] = array_map(static fn (string $person) => $claim('wikibase-entityid', ['id' => $person]), $film['directorIds']);
                if (null !== $film['imdbId']) {
                    $claims['P345'] = [$claim('string', $film['imdbId'])];
                }
                if (null !== $film['tmdbId']) {
                    $claims['P4947'] = [$claim('string', $film['tmdbId'])];
                }
                $entities[$id] = ['id' => $id, 'claims' => $claims];
            } elseif (isset($this->people[$id])) {
                $entities[$id] = ['id' => $id, 'labels' => ['en' => ['language' => 'en', 'value' => $this->people[$id]]]];
            } else {
                $entities[$id] = ['id' => $id, 'missing' => ''];
            }
        }

        return [json_encode(['entities' => $entities], \JSON_THROW_ON_ERROR), 200];
    }
}
