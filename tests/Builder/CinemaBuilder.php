<?php

namespace App\Tests\Builder;

use App\Catalog\Entity\Cinema;
use App\Catalog\Entity\City;

final class CinemaBuilder
{
    private string $slug = 'cinema-pathe-dijon';
    private ?string $name = null;
    private ?City $city = null;
    private float $latitude = 47.318031;
    private float $longitude = 5.029935;
    private string $timezone = 'Europe/Paris';
    private bool $open = true;

    public static function aCinema(): self
    {
        return new self();
    }

    public function withSlug(string $slug): self
    {
        $clone = clone $this;
        $clone->slug = $slug;

        return $clone;
    }

    public function named(string $name): self
    {
        $clone = clone $this;
        $clone->name = $name;

        return $clone;
    }

    public function in(City $city): self
    {
        $clone = clone $this;
        $clone->city = $city;

        return $clone;
    }

    public function at(float $latitude, float $longitude): self
    {
        $clone = clone $this;
        $clone->latitude = $latitude;
        $clone->longitude = $longitude;

        return $clone;
    }

    public function inTimezone(string $timezone): self
    {
        $clone = clone $this;
        $clone->timezone = $timezone;

        return $clone;
    }

    public function closed(): self
    {
        $clone = clone $this;
        $clone->open = false;

        return $clone;
    }

    public function build(): Cinema
    {
        if (null === $this->city) {
            throw new \LogicException('A cinema needs a city: call in() first.');
        }

        return (new Cinema())
            ->setSlug($this->slug)
            ->setName($this->name ?? 'Cinema '.$this->slug)
            ->setChain('pathe')
            ->setCountry('FR')
            ->setTimezone($this->timezone)
            ->setCity($this->city)
            ->setLatitude($this->latitude)
            ->setLongitude($this->longitude)
            ->setOpen($this->open);
    }
}
