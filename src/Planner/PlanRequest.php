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
    /**
     * @param ?string $date     local day of the cinemas (Y-m-d)
     * @param ?string $from     earliest start, local time of the cinema (H:i)
     * @param ?string $until    latest end, local time (H:i), after $from: the range stays within the day
     * @param ?string $city     city slug (or $position)
     * @param ?string $position JSON position sent by the browser, e.g. {"lat": 47.32, "lng": 5.04}
     * @param ?int    $seed     draw of the programmes (see ProgrammeSelector): drawn when null
     */
    public function __construct(
        #[Assert\NotBlank(message: 'planner.date.required')]
        #[AvailableDate]
        public ?string $date = null,
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Time(withSeconds: false)]
        public ?string $from = null,
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Time(withSeconds: false)]
        #[Assert\GreaterThan(propertyPath: 'from', message: 'planner.time_range.order')]
        public ?string $until = null,
        #[Assert\NotBlank(allowNull: true)]
        public ?string $city = null,
        #[Assert\NotBlank(allowNull: true)]
        public ?string $position = null,
        #[Assert\Range(notInRangeMessage: 'planner.seed.invalid', min: 0, max: PlannerService::MAX_SEED)]
        public ?int $seed = null,
        public TravelMode $travelMode = TravelMode::Transit,
        #[Assert\Range(notInRangeMessage: 'planner.films.range', min: FilmCount::MIN, max: FilmCount::MAX)]
        public int $films = FilmCount::DEFAULT,
        public ?ShowtimeVersion $version = null,
        public bool $acceptAds = false,
    ) {
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
}
