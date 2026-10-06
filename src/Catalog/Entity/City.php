<?php

namespace App\Catalog\Entity;

use App\Catalog\Repository\CityRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CityRepository::class)]
class City
{
    #[ORM\Id]
    #[ORM\Column(length: 100)]
    private ?string $slug = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /** Cinema chain this record comes from, e.g. 'pathe' (see config/packages/chains.yaml). */
    #[ORM\Column(length: 32)]
    private ?string $chain = null;

    /** ISO 3166-1 alpha-2 country code, e.g. 'FR'. */
    #[ORM\Column(length: 2)]
    private ?string $country = null;

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = $country;

        return $this;
    }
}
