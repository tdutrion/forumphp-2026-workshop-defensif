<?php

namespace App\Tests\Builder;

use App\Catalog\Entity\Work;
use App\Catalog\WorkLinkStatus;

final class WorkBuilder
{
    private string $title = 'Digger';
    private ?int $year = 2026;
    private ?array $directors = null;
    private ?array $link = null;

    public static function aWork(): self
    {
        return new self();
    }

    public function titled(string $title): self
    {
        $clone = clone $this;
        $clone->title = $title;

        return $clone;
    }

    public function releasedIn(?int $year): self
    {
        $clone = clone $this;
        $clone->year = $year;

        return $clone;
    }

    public function directedBy(string ...$directors): self
    {
        $clone = clone $this;
        $clone->directors = $directors;

        return $clone;
    }

    public function linkedTo(string $wikidataId, ?string $imdbId = null, ?string $tmdbId = null): self
    {
        $clone = clone $this;
        $clone->link = [$wikidataId, $imdbId, $tmdbId];

        return $clone;
    }

    public function build(): Work
    {
        $work = (new Work())->describe($this->title, $this->year, $this->directors);
        if (null !== $this->link) {
            $work->setExternalIds($this->link[0], $this->link[1], $this->link[2], WorkLinkStatus::Wikidata);
        }

        return $work;
    }
}
