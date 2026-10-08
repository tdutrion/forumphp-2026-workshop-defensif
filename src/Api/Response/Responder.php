<?php

declare(strict_types=1);

namespace App\Api\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Turns the output DTOs of the API into JSON: the Serializer reads their public properties, so the shape
 * of a response is the class that describes it (and the OpenAPI documentation reads the same class).
 */
final readonly class Responder
{
    public function __construct(private NormalizerInterface $normalizer)
    {
    }

    /**
     * @param object|list<object>   $resource
     * @param array<string, string> $headers
     */
    public function json(object|array $resource, int $status = JsonResponse::HTTP_OK, array $headers = []): JsonResponse
    {
        return new JsonResponse($this->normalizer->normalize($resource, 'json'), $status, $headers);
    }
}
