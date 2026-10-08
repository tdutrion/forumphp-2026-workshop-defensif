<?php

declare(strict_types=1);

namespace App\Sdk\Pathe;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Uri\Rfc3986\Uri;

/**
 * Only access point to the (unofficial) pathe.fr API.
 *
 * Standalone library: depends on PSR interfaces only, and on nothing it would find by itself: the HTTP
 * client and the request factory are given, the cache is optional (none = no cache), the logger defaults to
 * a logger that says nothing.
 */
class PatheClient
{
    // Pathé blocks crawler-like user agents (HTTP 403).
    private const string USER_AGENT = 'PatheApiExplorer/1.0';

    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private ?CacheItemPoolInterface $cache = null,
        private LoggerInterface $logger = new NullLogger(),
        private int $delayMs = 1000,
        private int $cacheTtl = 3600,
        private PatheEndpoints $endpoints = new PatheEndpoints(),
    ) {
    }

    /**
     * @throws BotBlockedException|RateLimitedException|PatheUnavailableException
     */
    public function getCities(): array
    {
        return $this->get($this->endpoints->cities(), true);
    }

    /**
     * @throws BotBlockedException|RateLimitedException|PatheUnavailableException
     */
    public function getCinemas(): array
    {
        return $this->get($this->endpoints->cinemas(), true);
    }

    /**
     * Detail page of a film: the only place where Pathé gives its nationality. Cached like the reference data.
     *
     * @throws BotBlockedException|RateLimitedException|PatheUnavailableException
     */
    public function getShow(ShowSlug $showSlug): array
    {
        return $this->get($this->endpoints->show($showSlug), true);
    }

    /**
     * @return list<array<string, mixed>> the list of films and events
     *
     * @throws BotBlockedException|RateLimitedException|PatheUnavailableException
     */
    public function getShows(): array
    {
        return $this->get($this->endpoints->shows(), true)['shows'] ?? [];
    }

    /**
     * A cinema's programme: which films play on which days (without the times).
     *
     * @throws BotBlockedException|RateLimitedException|PatheUnavailableException
     */
    public function getCinemaProgramme(CinemaSlug $cinemaSlug): array
    {
        return $this->get($this->endpoints->cinemaProgramme($cinemaSlug));
    }

    /**
     * Showtimes of a film in a cinema, all days together (Pathé indexes them by date, or returns [] when there are none).
     *
     * @throws BotBlockedException|RateLimitedException|PatheUnavailableException
     */
    public function getShowtimes(ShowSlug $showSlug, CinemaSlug $cinemaSlug): PatheShowtimes
    {
        $uri = $this->endpoints->showtimes($showSlug, $cinemaSlug);
        $data = $this->get($uri);

        try {
            return PatheShowtimes::fromApiResponse($data);
        } catch (\InvalidArgumentException $e) {
            $this->fail($uri->getPath(), 'Unexpected showtimes from Pathé: '.$e->getMessage(), $e);
        }
    }

    /**
     * @param bool $cacheable reference data that changes rarely: kept $cacheTtl seconds when a cache is available
     *
     * @throws BotBlockedException|RateLimitedException|PatheUnavailableException
     */
    private function get(Uri $uri, bool $cacheable = false): array
    {
        $path = $uri->getPath();
        $cache = $cacheable && $this->cacheTtl > 0 ? $this->cache : null;
        $item = $cache?->getItem('pathe.'.str_replace('/', '.', trim($path, '/')));
        if (null !== $item && $item->isHit()) {
            return $item->get();
        }

        usleep($this->delayMs * 1000);

        $request = $this->requestFactory
            ->createRequest('GET', $uri->toString())
            ->withHeader('User-Agent', self::USER_AGENT)
            ->withHeader('Accept', 'application/json');

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            $this->fail($path, 'Pathé call failed: '.$e->getMessage(), $e);
        }

        $status = $response->getStatusCode();
        if (403 === $status) {
            throw new BotBlockedException($path);
        }
        if (429 === $status) {
            throw new RateLimitedException($path);
        }
        if (200 !== $status) {
            $this->fail($path, \sprintf('Pathé answered HTTP %d.', $status));
        }

        try {
            $data = json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->fail($path, 'Pathé did not answer JSON: '.$e->getMessage(), $e);
        }
        if (!is_array($data)) {
            $this->fail($path, 'Pathé did not answer a JSON object or list.');
        }

        if (null !== $cache && null !== $item) {
            $cache->save($item->set($data)->expiresAfter($this->cacheTtl));
        }

        return $data;
    }

    /**
     * Logs a one-off failure and gives it up: this method never returns.
     *
     * @throws PatheUnavailableException
     */
    private function fail(string $path, string $reason, ?\Throwable $previous = null): never
    {
        $this->logger->warning($reason, ['path' => $path]);

        throw new PatheUnavailableException($path, $reason, $previous);
    }
}
