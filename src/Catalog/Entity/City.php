<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use App\Catalog\CountryCode;
use App\Catalog\Repository\CityRepository;
use App\Doctrine\CountryCodeType;
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

    #[ORM\Column(type: CountryCodeType::NAME, length: 2)]
    private ?CountryCode $country = null;

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

    public function getCountry(): ?CountryCode
    {
        return $this->country;
    }

    public function setCountry(CountryCode $country): static
    {
        $this->country = $country;

        return $this;
    }
}
