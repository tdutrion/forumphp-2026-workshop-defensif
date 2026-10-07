<?php

declare(strict_types=1);

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
    public private(set) string $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'film_slug', referencedColumnName: 'slug', nullable: false, onDelete: 'CASCADE')]
    public private(set) Film $film;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'cinema_slug', referencedColumnName: 'slug', nullable: false, onDelete: 'CASCADE')]
    public private(set) Cinema $cinema;

    /** Session start (start of the ads), in UTC. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public private(set) \DateTimeImmutable $startsAt;

    /** End of the film, in UTC. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public private(set) \DateTimeImmutable $endsAt;

    /** Calendar day of the session in the cinema's time zone: a session starting at 00:30 local time belongs to that day. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    public private(set) \DateTimeImmutable $localDate;

    #[ORM\Column(length: 8, enumType: ShowtimeVersion::class)]
    public private(set) ShowtimeVersion $version;

    #[ORM\Column(length: 20, enumType: BookingStatus::class)]
    public private(set) BookingStatus $status;

    #[ORM\Column(length: 255)]
    public private(set) string $bookingUrl;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public private(set) ?\DateTimeImmutable $reservableUntil = null;

    #[ORM\Column(length: 50, nullable: true)]
    public private(set) ?string $auditorium = null;

    /**
     * Number of seats. Pathé sends it as a string ("244"): the hook reads it once, here; what is not a
     * number of seats is an unknown capacity. Doctrine hydrates the column without going through the hook.
     */
    #[ORM\Column(nullable: true)]
    public private(set) ?int $capacity = null {
        set(int|string|null $value) {
            if (\is_int($value) && $value < 0) {
                throw new \InvalidArgumentException(\sprintf('A hall cannot have %d seats.', $value));
            }
            $this->capacity = match (true) {
                \is_string($value) => ctype_digit($value) ? (int) $value : null,
                default => $value,
            };
        }
    }

    private function __construct()
    {
    }

    /**
     * @param int|string|null $capacity the number of seats, as a number or as Pathé sends it
     *
     * @throws \InvalidArgumentException if the film ends before it starts
     */
    public static function schedule(
        string $id,
        Film $film,
        Cinema $cinema,
        \DateTimeImmutable $startsAt,
        \DateTimeImmutable $endsAt,
        \DateTimeImmutable $localDate,
        ShowtimeVersion $version,
        BookingStatus $status,
        string $bookingUrl,
        ?\DateTimeImmutable $reservableUntil = null,
        ?string $auditorium = null,
        int|string|null $capacity = null,
    ): self {
        $showtime = new self();
        $showtime->id = $id;
        $showtime->film = $film;
        $showtime->cinema = $cinema;
        $showtime->reschedule($startsAt, $endsAt, $localDate);
        $showtime->version = $version;
        $showtime->status = $status;
        $showtime->bookingUrl = $bookingUrl;
        $showtime->reservableUntil = $reservableUntil;
        $showtime->auditorium = $auditorium;
        $showtime->capacity = $capacity;

        return $showtime;
    }

    /**
     * @throws \InvalidArgumentException if the film ends before it starts
     */
    public function reschedule(\DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt, \DateTimeImmutable $localDate): void
    {
        if ($endsAt < $startsAt) {
            throw new \InvalidArgumentException(\sprintf('Showtime %s ends (%s) before it starts (%s).', $this->id, $endsAt->format('c'), $startsAt->format('c')));
        }
        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;
        $this->localDate = $localDate;
    }

    public function updateBooking(BookingStatus $status, string $bookingUrl, ?\DateTimeImmutable $reservableUntil): void
    {
        $this->status = $status;
        $this->bookingUrl = $bookingUrl;
        $this->reservableUntil = $reservableUntil;
    }

    public function describeScreening(ShowtimeVersion $version, ?string $auditorium, int|string|null $capacity): void
    {
        $this->version = $version;
        $this->auditorium = $auditorium;
        $this->capacity = $capacity;
    }

    /**
     * Whether a seat can still be booked at $now (the same rule as the planner's SQL: the status allows it
     * and the booking is not closed; whether the cinema is open is a matter of the cinema).
     */
    public function isBookableAt(\DateTimeImmutable $now): bool
    {
        return $this->status->isBookable() && (null === $this->reservableUntil || $this->reservableUntil > $now);
    }
}
