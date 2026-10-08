<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\FilmSlug;
use App\Catalog\FilmSlugValueResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FilmSlugValueResolverTest extends TestCase
{
    public function testBuildsTheSlugOfTheRoute(): void
    {
        // Arrange
        $request = new Request(attributes: ['slug' => 'digger-51293']);

        // Act
        $resolved = (new FilmSlugValueResolver())->resolve($request, new ArgumentMetadata('slug', FilmSlug::class, false, false, null));

        // Assert
        self::assertEquals([new FilmSlug('digger-51293')], $resolved);
    }

    public function testLeavesTheOtherArgumentsAlone(): void
    {
        // Arrange
        $request = new Request(attributes: ['slug' => 'digger-51293']);

        // Act
        $resolved = (new FilmSlugValueResolver())->resolve($request, new ArgumentMetadata('slug', 'string', false, false, null));

        // Assert
        self::assertSame([], $resolved);
    }

    public function testAWrongSlugIsAnUnknownFilm(): void
    {
        // Arrange
        $request = new Request(attributes: ['slug' => 'Not_A_Slug']);

        // Assert
        $this->expectException(NotFoundHttpException::class);

        // Act
        (new FilmSlugValueResolver())->resolve($request, new ArgumentMetadata('slug', FilmSlug::class, false, false, null));
    }
}
