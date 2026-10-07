<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api;

use App\Api\Response\CityResource;
use App\Api\Response\NoShowtimeResource;
use App\Api\Response\Responder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class ResponderTest extends TestCase
{
    private function responder(): Responder
    {
        return new Responder(new Serializer([new ObjectNormalizer(propertyAccessor: PropertyAccess::createPropertyAccessor())]));
    }

    public function testTheShapeOfAResponseIsItsClass(): void
    {
        // Act
        $response = $this->responder()->json(new CityResource('dijon', 'Dijon'));

        // Assert
        self::assertSame('{"slug":"dijon","name":"Dijon"}', $response->getContent());
        self::assertSame(200, $response->getStatusCode());
    }

    public function testAListIsAJsonList(): void
    {
        // Act
        $response = $this->responder()->json([new CityResource('dijon', 'Dijon'), new CityResource('lyon', 'Lyon')]);
        $empty = $this->responder()->json([]);

        // Assert
        self::assertSame('[{"slug":"dijon","name":"Dijon"},{"slug":"lyon","name":"Lyon"}]', $response->getContent());
        self::assertSame('[]', $empty->getContent());
    }

    public function testNothingWasDrawnWhenNoShowtimeMatches(): void
    {
        // Act
        $response = $this->responder()->json(new NoShowtimeResource());

        // Assert
        self::assertSame('{"programmes":[],"reason":"no_showtime"}', $response->getContent());
    }
}
