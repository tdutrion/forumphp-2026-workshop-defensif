<?php

namespace App\Catalog;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Selection and order of the films page, read from the URL (every criterion is optional).
 */
final readonly class FilmCatalogQuery
{
    public const SORTS = ['title', 'showtimes', 'release', 'duration'];

    #[Assert\Length(max: 100)]
    public ?string $q;

    #[Assert\Length(max: 100)]
    public ?string $genre;

    #[Assert\Regex(pattern: '/^[a-z0-9-]{1,100}$/')]
    public ?string $city;

    #[Assert\Choice(choices: ['vf', 'vost', 'vo', 'vfst'])]
    public ?string $version;

    #[Assert\Choice(choices: self::SORTS)]
    public string $sort;

    /** Cinema week, from this Wednesday (Y-m-d) to the next Tuesday: the films with a showtime in it. */
    #[Assert\Date]
    public ?string $week;

    /** Leave out the films the user has already seen. */
    #[SerializedName('hide_seen')]
    public bool $hideSeen;

    /** Leave out the films the user said are not for them. */
    #[SerializedName('hide_unwanted')]
    public bool $hideUnwanted;

    #[Assert\Positive]
    public int $page;

    /**
     * An empty field of the form ("version=") means no criterion: it becomes null, like a missing one.
     */
    public function __construct(
        ?string $q = null,
        ?string $genre = null,
        ?string $city = null,
        ?string $version = null,
        ?string $sort = null,
        ?string $week = null,
        bool $hideSeen = false,
        bool $hideUnwanted = false,
        int $page = 1,
    ) {
        $this->q = self::nullIfBlank($q);
        $this->genre = self::nullIfBlank($genre);
        $this->city = self::nullIfBlank($city);
        $this->version = self::nullIfBlank($version);
        $this->sort = self::nullIfBlank($sort) ?? 'title';
        $this->week = self::nullIfBlank($week);
        $this->hideSeen = $hideSeen;
        $this->hideUnwanted = $hideUnwanted;
        $this->page = $page;
    }

    /**
     * @return array the criteria as URL parameters, empty ones left out (links of the pagination)
     */
    public function toQuery(): array
    {
        return array_filter([
            'q' => $this->q,
            'genre' => $this->genre,
            'city' => $this->city,
            'version' => $this->version,
            'sort' => 'title' === $this->sort ? null : $this->sort,
            'week' => $this->week,
            'hide_seen' => $this->hideSeen ? 1 : null,
            'hide_unwanted' => $this->hideUnwanted ? 1 : null,
        ], static fn ($value) => null !== $value && '' !== $value);
    }

    private static function nullIfBlank(?string $value): ?string
    {
        return null === $value || '' === trim($value) ? null : $value;
    }
}
