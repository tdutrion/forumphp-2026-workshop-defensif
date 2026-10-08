<?php

declare(strict_types=1);

namespace App\Sdk\Wikidata;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Only access point to the Wikidata action API (CC0 data). Wikidata asks for a User-Agent naming
 * the application and a way to contact it, and for a gentle pace: one request every $delayMs.
 */
class WikidataClient
{
    public const string BASE_URL = 'https://www.wikidata.org/w/api.php';

    public function __construct(
        private string $userAgent,
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private ?CacheItemPoolInterface $cache = null,
        private LoggerInterface $logger = new NullLogger(),
        private WikidataMapper $mapper = new WikidataMapper(),
        private int $delayMs = 500,
        private int $cacheTtl = 86400,
    ) {
    }

    /**
     * @return list<string>|false item ids found for a title (at most 10), or false when Wikidata fails
     */
    public function searchFilms(string $title, string $language): array|false
    {
        $raw = $this->get(['action' => 'wbsearchentities', 'search' => $title, 'language' => $language, 'type' => 'item', 'limit' => 10]);

        return false === $raw ? false : $this->mapper->searchIds($raw);
    }

    /**
     * @param list<string> $ids item ids (Q…)
     *
     * @return array<string, array{types: list<string>, years: list<int>, directors: list<string>, imdbId: ?string, tmdbId: ?string}>|false by item id, directors by label, or false when Wikidata fails
     */
    public function getFilms(array $ids): array|false
    {
        if ([] === $ids) {
            return [];
        }
        // Wikidata reads at most 50 items per request: every candidate is read, never a part of them.
        $films = [];
        $people = [];
        foreach (array_chunk(array_values(array_unique($ids)), 50) as $chunk) {
            $raw = $this->get(['action' => 'wbgetentities', 'ids' => implode('|', $chunk), 'props' => 'claims']);
            if (false === $raw) {
                return false;
            }
            foreach ($raw['entities'] ?? [] as $id => $entity) {
                if (isset($entity['claims']) && is_array($entity['claims'])) {
                    $films[$id] = $this->mapper->film($entity);
                    $people = array_merge($people, $films[$id]['directorIds']);
                }
            }
        }

        $labels = [];
        foreach (array_chunk(array_values(array_unique($people)), 50) as $chunk) {
            $rawPeople = $this->get(['action' => 'wbgetentities', 'ids' => implode('|', $chunk), 'props' => 'labels', 'languages' => 'en|fr']);
            if (false === $rawPeople) {
                return false;
            }
            $labels += $this->mapper->labels($rawPeople);
        }

        foreach ($films as $id => $film) {
            $films[$id] = [
                'types' => $film['types'],
                'years' => $film['years'],
                'directors' => array_values(array_filter(array_map(static fn (string $person) => $labels[$person] ?? null, $film['directorIds']))),
                'imdbId' => $film['imdbId'],
                'tmdbId' => $film['tmdbId'],
            ];
        }

        return $films;
    }

    private function get(array $query): array|false
    {
        $query += ['format' => 'json'];
        $url = self::BASE_URL.'?'.http_build_query($query);
        $cache = $this->cacheTtl > 0 ? $this->cache : null;
        $item = $cache?->getItem('wikidata.'.sha1($url));
        if (null !== $item && $item->isHit()) {
            return $item->get();
        }

        usleep($this->delayMs * 1000);
        $request = $this->requestFactory->createRequest('GET', $url)
            ->withHeader('User-Agent', $this->userAgent)
            ->withHeader('Accept', 'application/json');

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            $this->logger->warning('Wikidata call failed', ['action' => $query['action'], 'error' => $e->getMessage()]);

            return false;
        }
        if (200 !== $response->getStatusCode()) {
            $this->logger->warning('Unexpected response from Wikidata', ['action' => $query['action'], 'status' => $response->getStatusCode()]);

            return false;
        }

        try {
            $data = json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->logger->warning('Wikidata answered something else than JSON', ['action' => $query['action']]);

            return false;
        }
        if (!is_array($data) || isset($data['error'])) {
            $this->logger->warning('Wikidata answered an error', ['action' => $query['action']]);

            return false;
        }

        if (null !== $cache && null !== $item) {
            $cache->save($item->set($data)->expiresAfter($this->cacheTtl));
        }

        return $data;
    }
}
