<?php

namespace App\Planner;

/**
 * The outcome of a plan: either programmes (possibly with a remark), or a failure, never both.
 */
final readonly class PlanResult
{
    /**
     * @param int|null $films number of films per programme (fewer than asked with PlanNotice::FewerFilms)
     * @param int|null $seed  the seed of the draw, to get the same programmes again
     */
    private function __construct(
        public ProgrammeList $programmes,
        public ?PlanFailure $failure,
        public ?PlanNotice $notice,
        public ?int $films,
        public ?int $seed,
    ) {
    }

    public static function success(ProgrammeList $programmes, int $films, int $seed, ?PlanNotice $notice = null): self
    {
        if ($programmes->isEmpty()) {
            throw new \InvalidArgumentException('A successful plan offers at least one programme.');
        }

        return new self($programmes, null, $notice, $films, $seed);
    }

    /**
     * @param int|null $films the number of films the search went down to
     * @param int|null $seed  the seed of the draw, when the search got as far as drawing
     */
    public static function failure(PlanFailure $failure, ?int $films = null, ?int $seed = null): self
    {
        return new self(new ProgrammeList(), $failure, null, $films, $seed);
    }

    public function isSuccess(): bool
    {
        return null === $this->failure;
    }

    /**
     * Stable name of the failure or of the remark, for the API; null for a plain success.
     */
    public function reason(): ?string
    {
        return ($this->failure ?? $this->notice)?->value;
    }
}
