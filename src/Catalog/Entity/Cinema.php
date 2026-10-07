<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use App\Catalog\CinemaChain;
use App\Catalog\Coordinates;
use App\Catalog\CountryCode;
use App\Catalog\Repository\CinemaRepository;
use App\Doctrine\CountryCodeType;
use App\Doctrine\DateTimeZoneType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CinemaRepository::class)]
class Cinema
{
    #[ORM\Id]
    #[ORM\Column(length: 100)]
    public private(set) string $slug;

    #[ORM\Column(length: 255)]
    public private(set) string $name;

    /** Cinema chain this record comes from, e.g. 'pathe' (see config/packages/chains.yaml). */
    #[ORM\Column(length: 32)]
    public private(set) string $chain;

    #[ORM\Column(type: CountryCodeType::NAME, length: 2)]
    public private(set) CountryCode $country;

    /** Showtimes are stored in UTC and shown in the zone of the cinema, which is the chain's unless a cinema says otherwise. */
    #[ORM\Column(type: DateTimeZoneType::NAME, length: 64)]
    public private(set) \DateTimeZone $timezone;

    /** ISO 639-1 language of the chain, e.g. 'fr': a film made in this language plays in its original version. */
    #[ORM\Column(length: 2)]
    public private(set) string $language;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'city_slug', referencedColumnName: 'slug', nullable: false)]
    public private(set) City $city;

    #[ORM\Column(length: 255, nullable: true)]
    public private(set) ?string $address = null;

    #[ORM\Column(length: 10, nullable: true)]
    public private(set) ?string $postalCode = null;

    #[ORM\Column(length: 100, nullable: true)]
    public private(set) ?string $town = null;

    #[ORM\Column(nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(nullable: true)]
    private ?float $longitude = null;

    /**
     * Where the cinema is, null when the chain gives no position. Doctrine has no nullable embeddable:
     * the value object is a view over the two columns, which raw SQL reads as they are.
     */
    public ?Coordinates $coordinates {
        get => null === $this->latitude || null === $this->longitude ? null : new Coordinates($this->latitude, $this->longitude);
    }

    #[ORM\Column(nullable: true)]
    public private(set) ?int $hallCount = null;

    #[ORM\Column]
    public private(set) bool $open = true;

    private function __construct()
    {
    }

    /**
     * A cinema of a chain, open and without a position until it is said otherwise.
     */
    public static function register(string $slug, string $name, City $city, CinemaChain $chain): self
    {
        $cinema = new self();
        $cinema->slug = $slug;
        $cinema->name = $name;
        $cinema->city = $city;
        $cinema->chain = $chain->id;
        $cinema->follow($chain);

        return $cinema;
    }

    /**
     * What the chain says about the cinema itself.
     */
    public function describe(string $name, City $city, ?string $address, ?string $postalCode, ?string $town, ?int $hallCount): void
    {
        $this->name = $name;
        $this->city = $city;
        $this->address = $address;
        $this->postalCode = $postalCode;
        $this->town = $town;
        $this->hallCount = $hallCount;
    }

    /**
     * The country, the time zone and the language of the chain the cinema belongs to.
     */
    public function follow(CinemaChain $chain): void
    {
        $this->country = $chain->country;
        $this->timezone = $chain->timezone;
        $this->language = $chain->language;
    }

    public function locate(?Coordinates $coordinates): void
    {
        $this->latitude = $coordinates?->latitude;
        $this->longitude = $coordinates?->longitude;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function reopen(): void
    {
        $this->open = true;
    }
}
