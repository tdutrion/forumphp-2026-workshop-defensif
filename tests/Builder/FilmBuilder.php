<?php

namespace App\Tests\Builder;

use App\Catalog\Entity\Film;

final class FilmBuilder
{
    private string $slug = 'digger-51293';
    private ?string $title = null;
    private int $duration = 100;
    private ?string $originalLanguage = null;

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

    public function build(): Film
    {
        return (new Film())
            ->setSlug($this->slug)
            ->setTitle($this->title ?? 'Film '.$this->slug)
            ->setChain('pathe')
            ->setDuration($this->duration)
            ->setOriginalLanguage($this->originalLanguage);
    }
}
