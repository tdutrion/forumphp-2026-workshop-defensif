<?php

namespace App\Catalog\Entity;

use App\Catalog\BookingStatus;
use App\Catalog\Repository\ShowtimeRepository;
use App\Catalog\ShowtimeVersion;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShowtimeRepository::class)]
#[ORM\Index(columns: ['starts_at'])]
#[ORM\Index(columns: ['local_date'])]
class Showtime
{
    /** Pathé showtime identifier, e.g. "V3345S85474" (extracted from the booking link). */
    #[ORM\Id]
    #[ORM\Column(length: 32)]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'film_slug', referencedColumnName: 'slug', nullable: false, onDelete: 'CASCADE')]
    private ?Film $film = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'cinema_slug', referencedColumnName: 'slug', nullable: false, onDelete: 'CASCADE')]
    private ?Cinema $cinema = null;

    /** Session start (start of the ads), in UTC. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $startsAt = null;

    /** End of the film, in UTC. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $endsAt = null;

    /** Calendar day of the session in the cinema's time zone: a session starting at 00:30 local time belongs to that day. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $localDate = null;

    #[ORM\Column(length: 8, enumType: ShowtimeVersion::class)]
    private ?ShowtimeVersion $version = null;

    #[ORM\Column(length: 20, enumType: BookingStatus::class)]
    private ?BookingStatus $status = null;

    #[ORM\Column(length: 255)]
    private ?string $bookingUrl = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reservableUntil = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $auditorium = null;

    /** Number of seats, as Pathé sends it (string, e.g. "244"). */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $capacity = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getFilm(): ?Film
    {
        return $this->film;
    }

    public function setFilm(?Film $film): static
    {
        $this->film = $film;

        return $this;
    }

    public function getCinema(): ?Cinema
    {
        return $this->cinema;
    }

    public function setCinema(?Cinema $cinema): static
    {
        $this->cinema = $cinema;

        return $this;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getVersion(): ?ShowtimeVersion
    {
        return $this->version;
    }

    public function setVersion(ShowtimeVersion $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function getStatus(): ?BookingStatus
    {
        return $this->status;
    }

    public function setStatus(BookingStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getBookingUrl(): ?string
    {
        return $this->bookingUrl;
    }

    public function setBookingUrl(string $bookingUrl): static
    {
        $this->bookingUrl = $bookingUrl;

        return $this;
    }

    public function getReservableUntil(): ?\DateTimeImmutable
    {
        return $this->reservableUntil;
    }

    public function setReservableUntil(?\DateTimeImmutable $reservableUntil): static
    {
        $this->reservableUntil = $reservableUntil;

        return $this;
    }

    public function getAuditorium(): ?string
    {
        return $this->auditorium;
    }

    public function setAuditorium(?string $auditorium): static
    {
        $this->auditorium = $auditorium;

        return $this;
    }

    public function getCapacity(): ?string
    {
        return $this->capacity;
    }

    public function setCapacity(?string $capacity): static
    {
        $this->capacity = $capacity;

        return $this;
    }

    public function getLocalDate(): ?\DateTimeImmutable
    {
        return $this->localDate;
    }

    public function setLocalDate(\DateTimeImmutable $localDate): static
    {
        $this->localDate = $localDate;

        return $this;
    }
}
