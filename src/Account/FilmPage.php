<?php

namespace App\Account;

/**
 * One page of a list of films of the user (history, films not for them).
 */
final readonly class FilmPage
{
    public const PER_PAGE = 20;

    /**
     * @param array $films rows: 'slug', 'title', 'posterUrl', 'synopsis', 'markedAt' (UTC), latest first
     * @param int   $page  current page, from 1 (a page after the last one shows the last one)
     * @param int   $pages number of pages, at least 1
     */
    public function __construct(
        public array $films,
        public int $page,
        public int $pages,
        public int $total,
    ) {
    }

    /**
     * @param callable(int $offset, int $limit): array $fetch reads one page of rows
     */
    public static function load(int $total, int $page, callable $fetch): self
    {
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $page), $pages);

        return new self($fetch(($page - 1) * self::PER_PAGE, self::PER_PAGE), $page, $pages, $total);
    }
}
