<?php

namespace App\Sdk\Pathe;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PsrDiscovery\Discover;

/**
 * Only access point to the (unofficial) pathe.fr API.
 *
 * Standalone library: depends on PSR interfaces only. Collaborators that are not given are
 * discovered among the installed implementations (psr-discovery).
 */
class PatheClient
{
    public const BASE_URL = 'https://www.pathe.fr/api/';
    // Pathé blocks crawler-like user agents (HTTP 403).
    private const USER_AGENT = 'PatheApiExplorer/1.0';

    private ClientInterface $httpClient;
    private RequestFactoryInterface $requestFactory;
    private ?CacheItemPoolInterface $cache;
    private LoggerInterface $logger;

    public function __construct(
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?CacheItemPoolInterface $cache = null,
        ?LoggerInterface $logger = null,
        private int $delayMs = 1000,
        private int $cacheTtl = 3600,
        private string $baseUrl = self::BASE_URL,
    ) {
        $httpClient ??= Discover::httpClient();
        if (!$httpClient instanceof ClientInterface) {
            throw new \RuntimeException('No PSR-18 HTTP client found: pass one to PatheClient or install one (symfony/http-client, guzzlehttp/guzzle...).');
        }
        $requestFactory ??= Discover::httpRequestFactory();
        if (!$requestFactory instanceof RequestFactoryInterface) {
            throw new \RuntimeException('No PSR-17 request factory found: pass one to PatheClient or install one (nyholm/psr7, guzzlehttp/psr7...).');
        }
        $cache ??= Discover::cache();
        $logger ??= Discover::log();

        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->cache = $cache instanceof CacheItemPoolInterface ? $cache : null;
        $this->logger = $logger instanceof LoggerInterface ? $logger : new NullLogger();
    }

    public function getCities(): array|false
    {
        return $this->get('cities', true);
    }

    public function getCinemas(): array|false
    {
        return $this->get('cinemas', true);
    }

    /**
     * @return array|false the list of films and events, or false on failure
     */
    /**
     * Detail page of a film: the only place where Pathé gives its nationality. Cached like the reference data.
     */
    public function getShow(string $showSlug): array|false
    {
        return $this->get('show/'.rawurlencode($showSlug), true);
    }

    public function getShows(): array|false
    {
        $data = $this->get('shows', true);
        if (false === $data) {
            return false;
        }

        return $data['shows'] ?? [];
    }

    /**
     * A cinema's programme: which films play on which days (without the times).
     */
    public function getCinemaProgramme(string $cinemaSlug): array|false
    {
        return $this->get('cinema/'.rawurlencode($cinemaSlug).'/shows');
    }

    /**
     * Showtimes of a film in a cinema, indexed by date. Pathé returns [] when there are none.
     */
    public function getShowtimes(string $showSlug, string $cinemaSlug): array|false
    {
        return $this->get('show/'.rawurlencode($showSlug).'/showtimes/'.rawurlencode($cinemaSlug));
    }

    /**
     * @param bool $cacheable reference data that changes rarely: kept $cacheTtl seconds when a cache is available
     */
    private function get(string $path, bool $cacheable = false): array|false
    {
        $cache = $cacheable && $this->cacheTtl > 0 ? $this->cache : null;
        $item = $cache?->getItem('pathe.'.str_replace('/', '.', $path));
        if (null !== $item && $item->isHit()) {
            return $item->get();
        }

        usleep($this->delayMs * 1000);

        $request = $this->requestFactory
            ->createRequest('GET', $this->baseUrl.$path.'?'.http_build_query(['language' => 'fr']))
            ->withHeader('User-Agent', self::USER_AGENT)
            ->withHeader('Accept', 'application/json');

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            $this->logger->warning('Pathé call failed', ['path' => $path, 'error' => $e->getMessage()]);

            return false;
        }

        $status = $response->getStatusCode();
        if (403 === $status || 429 === $status) {
            throw new \RuntimeException(sprintf('Pathé refused the request %s (HTTP %d): synchronization aborted.', $path, $status));
        }
        if (200 !== $status) {
            $this->logger->warning('Unexpected response from Pathé', ['path' => $path, 'status' => $status]);

            return false;
        }

        try {
            $data = json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->warning('Pathé did not answer JSON', ['path' => $path, 'error' => $e->getMessage()]);

            return false;
        }
        if (!is_array($data)) {
            $this->logger->warning('Pathé did not answer a JSON object or list', ['path' => $path]);

            return false;
        }

        if (null !== $cache && null !== $item) {
            $cache->save($item->set($data)->expiresAfter($this->cacheTtl));
        }

        return $data;
    }
}
