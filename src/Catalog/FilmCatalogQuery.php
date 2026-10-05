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

    public function __construct(
        #[Assert\Length(max: 100)]
        public ?string $q = null,
        #[Assert\Length(max: 100)]
        public ?string $genre = null,
        #[Assert\Regex(pattern: '/^[a-z0-9-]{1,100}$/')]
        public ?string $city = null,
        #[Assert\Choice(choices: ['vf', 'vost', 'vo', 'vfst'])]
        public ?string $version = null,
        #[Assert\Choice(choices: self::SORTS)]
        public string $sort = 'title',
        /** Leave out the films already seen and those not for the user. */
        #[SerializedName('hide_marked')]
        public bool $hideMarked = false,
        #[Assert\Positive]
        public int $page = 1,
    ) {
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
            'hide_marked' => $this->hideMarked ? 1 : null,
        ], static fn ($value) => null !== $value && '' !== $value);
    }
}
