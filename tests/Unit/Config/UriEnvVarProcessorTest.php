<?php

declare(strict_types=1);

namespace App\Tests\Unit\Config;

use App\Config\UriEnvVarProcessor;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

final class UriEnvVarProcessorTest extends TestCase
{
    private function process(string $value): string
    {
        return (new UriEnvVarProcessor())->getEnv('uri', 'OAUTH_LOCAL_URL_TOKEN', static fn (string $name): string => $value);
    }

    #[TestWith(['http://oidc:8080/local/token'])]
    #[TestWith(['https://accounts.example.org/token?x=1'])]
    public function testGivesBackAnAbsoluteHttpUrl(string $url): void
    {
        // Act
        $processed = $this->process($url);

        // Assert
        self::assertSame($url, $processed);
    }

    #[TestWith([''])]
    #[TestWith(['not a url'])]
    #[TestWith(['localhost:8081/local/authorize'])]
    #[TestWith(['/local/authorize'])]
    #[TestWith(['ftp://oidc/token'])]
    #[TestWith(['http:///token'])]
    public function testAMalformedUrlFailsWithTheNameOfTheVariable(string $url): void
    {
        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OAUTH_LOCAL_URL_TOKEN');

        // Act
        $this->process($url);
    }

    public function testProvidesTheUriType(): void
    {
        // Assert
        self::assertSame(['uri' => 'string'], UriEnvVarProcessor::getProvidedTypes());
    }
}
