<?php

namespace App\Tests\Fake;

use App\Tests\Builder\OAuthServerBuilder;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Replaces the token and user-info endpoints of every OAuth provider in the test environment
 * (knpu_oauth2_client.http_client). Answers by URL path; any other path answers 404.
 */
final class FakeOAuthServer
{
    /** @var array<string, array{0: int, 1: array}> path => [HTTP status, JSON body] */
    private array $responses = [];

    public function serve(OAuthServerBuilder $server): void
    {
        $this->responses = $server->build();
    }

    public function httpClient(): Client
    {
        return new Client(['handler' => HandlerStack::create($this)]);
    }

    public function __invoke(RequestInterface $request, array $options): PromiseInterface
    {
        [$status, $body] = $this->responses[$request->getUri()->getPath()] ?? [404, ['error' => 'not_found']];

        return Create::promiseFor(new Response($status, ['Content-Type' => 'application/json'], json_encode($body)));
    }
}
