<?php

namespace App\Catalog\Entity;

use App\Catalog\Repository\FilmRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FilmRepository::class)]
class Film
{
    #[ORM\Id]
    #[ORM\Column(length: 150)]
    private ?string $slug = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    /** The work this chain's film shows, common to every chain. */
    #[ORM\ManyToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Work $work = null;

    /** Cinema chain this record comes from, e.g. 'pathe' (see config/packages/chains.yaml). */
    #[ORM\Column(length: 32)]
    private ?string $chain = null;

    /** Running time in minutes. */
    #[ORM\Column(nullable: true)]
    private ?int $duration = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $releaseDate = null;

    /** @var array list of genres, e.g. ['Action', 'Comédie'] */
    #[ORM\Column(type: Types::JSON)]
    private array $genres = [];

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $posterUrl = null;

    /** Rating label, e.g. "Tout public". */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $contentRating = null;

    /** ISO 639-1 original language, e.g. 'fr'; null when unknown. */
    #[ORM\Column(length: 2, nullable: true)]
    private ?string $originalLanguage = null;

    /** Plain text, read from the film page; null when Pathé gives none. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $synopsis = null;

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getReleaseDate(): ?\DateTimeImmutable
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(?\DateTimeImmutable $releaseDate): static
    {
        $this->releaseDate = $releaseDate;

        return $this;
    }

    public function getGenres(): array
    {
        return $this->genres;
    }

    public function setGenres(array $genres): static
    {
        $this->genres = $genres;

        return $this;
    }

    public function getPosterUrl(): ?string
    {
        return $this->posterUrl;
    }

    public function setPosterUrl(?string $posterUrl): static
    {
        $this->posterUrl = $posterUrl;

        return $this;
    }

    public function getContentRating(): ?string
    {
        return $this->contentRating;
    }

    public function setContentRating(?string $contentRating): static
    {
        $this->contentRating = $contentRating;

        return $this;
    }

    public function getChain(): ?string
    {
        return $this->chain;
    }

    public function setChain(string $chain): static
    {
        $this->chain = $chain;

        return $this;
    }

    public function getOriginalLanguage(): ?string
    {
        return $this->originalLanguage;
    }

    public function setOriginalLanguage(?string $originalLanguage): static
    {
        $this->originalLanguage = $originalLanguage;

        return $this;
    }

    public function getSynopsis(): ?string
    {
        return $this->synopsis;
    }

    public function setSynopsis(?string $synopsis): static
    {
        $this->synopsis = $synopsis;

        return $this;
    }

    public function getWork(): Work
    {
        return $this->work ?? throw new \LogicException('A film always has a work.');
    }

    public function setWork(Work $work): static
    {
        $this->work = $work;

        return $this;
    }
}
