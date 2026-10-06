<?php

namespace App\Tests\Fake;

use App\Tests\Builder\WikidataApiBuilder;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 client that replaces the Wikidata API in the test environment. Without a served
 * builder, every search finds nothing.
 */
final class FakeWikidataApi implements ClientInterface
{
    private WikidataApiBuilder $api;

    public function __construct()
    {
        $this->api = WikidataApiBuilder::aWikidataApi();
    }

    public function serve(WikidataApiBuilder $api): void
    {
        $this->api = $api;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        parse_str($request->getUri()->getQuery(), $query);
        [$body, $status] = $this->api->answer($query);

        return new Response($status, ['Content-Type' => 'application/json'], $body);
    }
}
