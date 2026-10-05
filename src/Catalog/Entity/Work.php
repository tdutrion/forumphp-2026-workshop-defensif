<?php

namespace App\Catalog\Entity;

use App\Catalog\Repository\WorkRepository;
use App\Catalog\WorkLinkStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A film as a work, common to every cinema chain: each chain's Film points to its work.
 * Linked to Wikidata (CC0) when it can be found; its id is the common identifier anyway.
 */
#[ORM\Entity(repositoryClass: WorkRepository::class)]
#[ORM\Index(columns: ['fingerprint'])]
class Work
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $originalTitle = '';

    /** Original release year; null when unknown. */
    #[ORM\Column(nullable: true)]
    private ?int $year = null;

    /** @var array|null names of the directors; null = not read yet, [] = none given */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $directors = null;

    /** Normalized title, year and surname of the first director (see fingerprintOf()). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fingerprint = null;

    #[ORM\Column(length: 20, unique: true, nullable: true)]
    private ?string $wikidataId = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $imdbId = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $tmdbId = null;

    #[ORM\Column(length: 16, enumType: WorkLinkStatus::class)]
    private WorkLinkStatus $linkStatus = WorkLinkStatus::Unlinked;

    /** Last automatic attempt to link the work (UTC). */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $linkAttemptedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * Same film, same fingerprint, whatever the chain: lower case, accents removed, every run of
     * characters other than letters and digits becomes one space; then the year and the surname
     * (last word) of the first director. Null when a part is unknown: never merge on a guess.
     */
    public static function fingerprintOf(?string $title, ?int $year, ?array $directors): ?string
    {
        $normalize = static fn (string $text): string => trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower(
            (string) transliterator_transliterate('Any-Latin; Latin-ASCII', $text),
        )));
        $title = null === $title ? '' : $normalize($title);
        $director = null === $directors || [] === $directors ? '' : $normalize((string) reset($directors));
        if ('' === $title || null === $year || '' === $director) {
            return null;
        }
        $words = explode(' ', $director);

        return $title.'|'.$year.'|'.end($words);
    }

    public function describe(string $originalTitle, ?int $year, ?array $directors): static
    {
        $this->originalTitle = $originalTitle;
        $this->year = $year;
        $this->directors = $directors;
        $this->fingerprint = self::fingerprintOf($originalTitle, $year, $directors);

        return $this;
    }

    public function setExternalIds(string $wikidataId, ?string $imdbId, ?string $tmdbId, WorkLinkStatus $status): static
    {
        $this->wikidataId = $wikidataId;
        $this->imdbId = $imdbId;
        $this->tmdbId = $tmdbId;
        $this->linkStatus = $status;

        return $this;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOriginalTitle(): string
    {
        return $this->originalTitle;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function getDirectors(): ?array
    {
        return $this->directors;
    }

    public function getFingerprint(): ?string
    {
        return $this->fingerprint;
    }

    public function getWikidataId(): ?string
    {
        return $this->wikidataId;
    }

    public function getImdbId(): ?string
    {
        return $this->imdbId;
    }

    public function getTmdbId(): ?string
    {
        return $this->tmdbId;
    }

    public function getLinkStatus(): WorkLinkStatus
    {
        return $this->linkStatus;
    }

    public function setLinkStatus(WorkLinkStatus $linkStatus): static
    {
        $this->linkStatus = $linkStatus;

        return $this;
    }

    public function getLinkAttemptedAt(): ?\DateTimeImmutable
    {
        return $this->linkAttemptedAt;
    }

    public function setLinkAttemptedAt(?\DateTimeImmutable $linkAttemptedAt): static
    {
        $this->linkAttemptedAt = $linkAttemptedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
