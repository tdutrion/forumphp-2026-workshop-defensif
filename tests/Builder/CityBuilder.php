<?php

declare(strict_types=1);

namespace App\Tests\Builder;

use App\Catalog\CountryCode;
use App\Catalog\Entity\City;

final class CityBuilder
{
    private string $slug = 'dijon';
    private string $name = 'Dijon';

    public static function aCity(): self
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

    public function build(): City
    {
        return (new City())->setSlug($this->slug)->setName($this->name)->setChain('pathe')->setCountry(new CountryCode('FR'));
    }
}
