<?php

namespace App\Tests\Builder;

use App\Catalog\BookingStatus;
use App\Catalog\Entity\Cinema;
use App\Catalog\Entity\Film;
use App\Catalog\Entity\Showtime;
use App\Catalog\ShowtimeVersion;

final class ShowtimeBuilder
{
    private ?string $id = null;
    private ?Film $film = null;
    private ?Cinema $cinema = null;
    private string $start = '2030-01-10 14:00:00';
    private ShowtimeVersion $version = ShowtimeVersion::Vf;
    private ?string $bookableUntil = null;

    public static function aShowtime(): self
    {
        return new self();
    }

    public function withId(string $id): self
    {
        $clone = clone $this;
        $clone->id = $id;

        return $clone;
    }

    public function of(Film $film): self
    {
        $clone = clone $this;
        $clone->film = $film;

        return $clone;
    }

    public function at(Cinema $cinema): self
    {
        $clone = clone $this;
        $clone->cinema = $cinema;

        return $clone;
    }

    /**
     * @param string $start session start (ads included) in the cinema's local time, e.g. '2030-01-10 14:00:00'
     */
    public function startingAt(string $start): self
    {
        $clone = clone $this;
        $clone->start = $start;

        return $clone;
    }

    public function inVersion(ShowtimeVersion $version): self
    {
        $clone = clone $this;
        $clone->version = $version;

        return $clone;
    }

    /**
     * @param string $moment UTC instant, e.g. '2020-01-01 00:00:00'
     */
    public function bookableUntil(string $moment): self
    {
        $clone = clone $this;
        $clone->bookableUntil = $moment;

        return $clone;
    }

    public function build(): Showtime
    {
        if (null === $this->film || null === $this->cinema) {
            throw new \LogicException('A showtime needs a film and a cinema: call of() and at() first.');
        }

        $local = new \DateTimeImmutable($this->start, new \DateTimeZone($this->cinema->getTimezone()));
        $start = $local->setTimezone(new \DateTimeZone('UTC'));
        $id = $this->id ?? 'V1S'.abs(crc32($this->film->getSlug().$this->cinema->getSlug().$this->start));

        return (new Showtime())
            ->setId($id)
            ->setFilm($this->film)
            ->setCinema($this->cinema)
            ->setStartsAt($start)
            ->setEndsAt($start->modify('+'.($this->film->getDuration() + 20).' minutes'))
            ->setLocalDate(new \DateTimeImmutable($local->format('Y-m-d')))
            ->setVersion($this->version)
            ->setStatus(BookingStatus::Available)
            ->setBookingUrl('https://s.pathe.fr/fr/'.$id.'/booking')
            ->setReservableUntil(new \DateTimeImmutable($this->bookableUntil ?? $start->modify('+20 minutes')->format('Y-m-d H:i:s')));
    }
}
