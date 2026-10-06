<?php

namespace App\Tests\Fake;

use App\Tests\Builder\PatheApiBuilder;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 client that replaces pathe.fr in the test environment (it is given to the SDK in place of the real one).
 * Any path that was not served answers 404, like Pathé does for an unknown slug.
 */
final class FakePatheApi implements ClientInterface
{
    /** @var array<string, array{0: string, 1: int}> */
    private array $responses = [];

    public function serve(PatheApiBuilder $api): void
    {
        $this->responses = $api->build();
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $path = substr($request->getUri()->getPath(), \strlen('/api/'));
        [$body, $status] = $this->responses[$path] ?? ['"Object with slug = '.$path.' doesn\'t exist"', 404];

        return new Response($status, ['Content-Type' => 'application/json'], $body);
    }
}
