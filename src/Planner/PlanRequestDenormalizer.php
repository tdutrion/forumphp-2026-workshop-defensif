<?php

declare(strict_types=1);

namespace App\Planner;

use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * An empty query parameter ("films=") means "not given", like an empty field of the plan form. The
 * ObjectNormalizer refuses '' for an int or an enum before the PlanRequest constructor could treat it:
 * empty parameters are left out first, so that their argument keeps its default value.
 */
final class PlanRequestDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const string ALREADY_CALLED = self::class;

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $given = array_filter($data, static fn (mixed $value): bool => '' !== $value);

        return $this->denormalizer->denormalize($given, $type, $format, [self::ALREADY_CALLED => true] + $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return PlanRequest::class === $type && \is_array($data) && !isset($context[self::ALREADY_CALLED]);
    }

    public function getSupportedTypes(?string $format): array
    {
        // Not cacheable: the answer depends on the context.
        return [PlanRequest::class => false];
    }
}
