<?php

namespace App\Tests\Unit\Sdk\Pathe;

use App\Sdk\Pathe\BotBlockedException;
use App\Sdk\Pathe\CinemaSlug;
use App\Sdk\Pathe\PatheApiException;
use App\Sdk\Pathe\PatheClient;
use App\Sdk\Pathe\PatheUnavailableException;
use App\Sdk\Pathe\RateLimitedException;
use App\Sdk\Pathe\ShowSlug;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class PatheClientTest extends TestCase
{
    private function client(ResponseInterface|\Throwable $answer): PatheClient
    {
        $http = new class($answer) implements ClientInterface {
            public function __construct(private ResponseInterface|\Throwable $answer)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                if ($this->answer instanceof \Throwable) {
                    throw $this->answer;
                }

                return $this->answer;
            }
        };

        return new PatheClient(httpClient: $http, requestFactory: new Psr17Factory(), cache: null, delayMs: 0);
    }

    public function testReadsWhatPatheAnswers(): void
    {
        // Arrange
        $client = $this->client(new Response(200, [], '{"shows": [{"slug": "digger-51293"}]}'));

        // Act
        $shows = $client->getShows();

        // Assert
        self::assertSame([['slug' => 'digger-51293']], $shows);
    }

    public function testBlockedMeansStop(): void
    {
        // Arrange
        $client = $this->client(new Response(403, [], '{"error":"Error from IP 1.2.3.4"}'));

        // Assert
        $this->expectException(BotBlockedException::class);

        // Act
        $client->getCities();
    }

    public function testTooFastMeansStop(): void
    {
        // Arrange
        $client = $this->client(new Response(429));

        // Assert
        $this->expectException(RateLimitedException::class);

        // Act
        $client->getCinemas();
    }

    #[TestWith([500])]
    #[TestWith([404])]
    #[TestWith([503])]
    public function testAnErrorStatusIsAOneOffFailure(int $status): void
    {
        // Arrange
        $client = $this->client(new Response($status, [], '"Object with slug = x doesn\'t exist"'));

        // Assert
        $this->expectException(PatheUnavailableException::class);

        // Act
        $client->getShow(new ShowSlug('digger-51293'));
    }

    public function testTheNetworkFailingIsAOneOffFailure(): void
    {
        // Arrange
        $client = $this->client(new class('connection reset') extends \RuntimeException implements ClientExceptionInterface {
        });

        // Act
        try {
            $client->getCities();
            self::fail('A network failure must not be silent.');
        } catch (PatheUnavailableException $e) {
            // Assert
            self::assertSame('cities', $e->path);
            self::assertInstanceOf(ClientExceptionInterface::class, $e->getPrevious(), 'the cause is kept');
        }
    }

    #[TestWith(['not json'])]
    #[TestWith(['"a string"'])]
    #[TestWith(['42'])]
    public function testAnAnswerThatIsNotAnObjectOrAListIsAOneOffFailure(string $body): void
    {
        // Arrange
        $client = $this->client(new Response(200, [], $body));

        // Assert
        $this->expectException(PatheUnavailableException::class);

        // Act
        $client->getCinemas();
    }

    public function testShowtimesOfAnUnexpectedShapeAreAOneOffFailure(): void
    {
        // Arrange
        $client = $this->client(new Response(200, [], '[{"time": "2026-10-04 16:30:00"}]'));

        // Assert
        $this->expectException(PatheUnavailableException::class);

        // Act
        $client->getShowtimes(new ShowSlug('verity-50815'), new CinemaSlug('cinema-pathe-dijon'));
    }

    public function testEveryFailureIsAPatheApiException(): void
    {
        foreach ([403 => BotBlockedException::class, 429 => RateLimitedException::class, 500 => PatheUnavailableException::class] as $status => $expected) {
            try {
                $this->client(new Response($status))->getCities();
                self::fail('A failure must not be silent.');
            } catch (PatheApiException $e) {
                self::assertInstanceOf($expected, $e);
            }
        }
    }
}
