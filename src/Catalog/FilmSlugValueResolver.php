<?php

declare(strict_types=1);

namespace App\Catalog;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Builds the FilmSlug argument of an action from the route attribute of the same name:
 * what is not a film slug is an unknown film, a 404.
 */
#[AutoconfigureTag('controller.argument_value_resolver', ['priority' => 150])]
final class FilmSlugValueResolver implements ValueResolverInterface
{
    #[\Override]
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $value = $request->attributes->get($argument->getName());
        if (FilmSlug::class !== $argument->getType() || !\is_string($value)) {
            return [];
        }

        try {
            return [new FilmSlug($value)];
        } catch (\InvalidArgumentException $e) {
            throw new NotFoundHttpException('error.film_not_found', $e);
        }
    }
}
