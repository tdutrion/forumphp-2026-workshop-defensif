<?php

namespace App\Planner;

use App\Catalog\ShowtimeVersion;
use App\Catalog\Validator\AvailableDate;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * The criteria of a marathon search, from the plan form (website) or the query string (API).
 * Validated as a whole: the value objects of the planner are only built from a valid request.
 */
final readonly class PlanRequest
{
    /** Local day of the cinemas (Y-m-d). */
    #[Assert\NotBlank(message: 'planner.date.required')]
    #[AvailableDate]
    public ?string $date;

    /** Earliest start, local time of the cinema (H:i). */
    #[Assert\Time(withSeconds: false)]
    public ?string $from;

    /** Latest end, local time (H:i), after $from: the range stays within the day. */
    #[Assert\Time(withSeconds: false)]
    #[Assert\GreaterThan(propertyPath: 'from', message: 'planner.time_range.order')]
    public ?string $until;

    /** City slug (or $position). */
    public ?string $city;

    /** JSON position sent by the browser, e.g. {"lat": 47.32, "lng": 5.04}. */
    public ?string $position;

    /**
     * An empty field ("from=") means no criterion: it becomes null, like a missing one. Constructor promotion
     * cannot normalize, so these five properties are declared above; the others are promoted. An empty
     * number or choice never reaches the constructor (see PlanRequestDenormalizer).
     *
     * @param ?int $seed draw of the programmes (see ProgrammeSelector): drawn when null
     */
    public function __construct(
        ?string $date = null,
        ?string $from = null,
        ?string $until = null,
        ?string $city = null,
        ?string $position = null,
        #[Assert\Range(notInRangeMessage: 'planner.seed.invalid', min: 0, max: PlannerService::MAX_SEED)]
        public ?int $seed = null,
        public TravelMode $travelMode = TravelMode::Transit,
        #[Assert\Range(notInRangeMessage: 'planner.films.range', min: FilmCount::MIN, max: FilmCount::MAX)]
        public int $films = FilmCount::DEFAULT,
        public ?ShowtimeVersion $version = null,
        public bool $acceptAds = false,
    ) {
        $this->date = self::nullIfBlank($date);
        $this->from = self::nullIfBlank($from);
        $this->until = self::nullIfBlank($until);
        $this->city = self::nullIfBlank($city);
        $this->position = self::nullIfBlank($position);
    }

    #[Assert\Callback]
    public function validateLocation(ExecutionContextInterface $context): void
    {
        if (null === $this->city && null === $this->position) {
            $context->buildViolation('planner.location.required')->atPath('city')->addViolation();
        }
    }

    public function filmCount(): FilmCount
    {
        return new FilmCount($this->films);
    }

    public function timeRange(): TimeRange
    {
        return new TimeRange($this->from, $this->until);
    }

    private static function nullIfBlank(?string $value): ?string
    {
        return null === $value || '' === trim($value) ? null : $value;
    }
}
