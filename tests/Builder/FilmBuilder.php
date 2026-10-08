<?php

declare(strict_types=1);

namespace App\Tests\Builder;

use App\Catalog\Entity\Film;
use App\Catalog\Entity\Work;

final class FilmBuilder
{
    private string $slug = 'digger-51293';
    private ?string $title = null;
    private int $duration = 100;
    private ?string $originalLanguage = null;
    private ?string $synopsis = null;
    private array $genres = [];
    private ?Work $work = null;

    public static function aFilm(): self
    {
        return new self();
    }

    public function withSlug(string $slug): self
    {
        $clone = clone $this;
        $clone->slug = $slug;

        return $clone;
    }

    public function titled(string $title): self
    {
        $clone = clone $this;
        $clone->title = $title;

        return $clone;
    }

    /**
     * @param int $minutes running time of the film itself (Pathé adds 20 minutes of ads before it)
     */
    public function lasting(int $minutes): self
    {
        $clone = clone $this;
        $clone->duration = $minutes;

        return $clone;
    }

    public function inOriginalLanguage(?string $language): self
    {
        $clone = clone $this;
        $clone->originalLanguage = $language;

        return $clone;
    }

    public function withSynopsis(?string $synopsis): self
    {
        $clone = clone $this;
        $clone->synopsis = $synopsis;

        return $clone;
    }

    public function inGenres(string ...$genres): self
    {
        $clone = clone $this;
        $clone->genres = $genres;

        return $clone;
    }

    public function ofWork(Work $work): self
    {
        $clone = clone $this;
        $clone->work = $work;

        return $clone;
    }

    public function build(): Film
    {
        return (new Film())
            ->setSlug($this->slug)
            ->setTitle($this->title ?? 'Film '.$this->slug)
            ->setChain('pathe')
            ->setDuration($this->duration)
            ->setOriginalLanguage($this->originalLanguage)
            ->setSynopsis($this->synopsis)
            ->setGenres($this->genres)
            ->setWork($this->work ?? WorkBuilder::aWork()->titled($this->title ?? 'Film '.$this->slug)->build());
    }
}
