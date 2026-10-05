<?php

namespace App\Account;

/**
 * One page of a list of films of the user (history, films not for them).
 */
final readonly class FilmPage
{
    /** The numbers of films per page a user can choose in their settings. */
    public const PAGE_SIZES = [10, 15, 20, 30];

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
     * @param int                                      $pageSize number of films per page, one of PAGE_SIZES
     * @param callable(int $offset, int $limit): array $fetch    reads one page of rows
     */
    public static function load(int $total, int $page, int $pageSize, callable $fetch): self
    {
        if (!in_array($pageSize, self::PAGE_SIZES, true)) {
            throw new \InvalidArgumentException('Unsupported page size: '.$pageSize);
        }
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min(max(1, $page), $pages);

        return new self($fetch(($page - 1) * $pageSize, $pageSize), $page, $pages, $total);
    }
}
