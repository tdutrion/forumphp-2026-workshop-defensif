<?php

declare(strict_types=1);

namespace App\Sdk\Pathe;

use Uri\InvalidUriException;
use Uri\Rfc3986\Uri;

/**
 * The URLs of the pathe.fr API: the base is parsed and checked once, here, and every call is
 * resolved from it. The slugs (ShowSlug, CinemaSlug) hold nothing that needs encoding.
 */
final readonly class PatheEndpoints
{
    public const string DEFAULT_BASE = 'https://www.pathe.fr/api/';

    private Uri $base;

    /**
     * @param string $base the API root: an https URL with a host and a path that ends with "/"
     *
     * @throws \InvalidArgumentException if it is not
     */
    public function __construct(string $base = self::DEFAULT_BASE)
    {
        try {
            $uri = new Uri($base);
        } catch (InvalidUriException $e) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a URL.', $base), 0, $e);
        }
        // new Uri('') does not throw (an empty string is a valid relative reference), and "https:///api/" has an empty host.
        if ('https' !== $uri->getScheme() || \in_array($uri->getHost(), [null, ''], true) || !str_ends_with($uri->getPath(), '/')) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not the root of an https API: expected https://host/path/.', $base));
        }
        $this->base = $uri;
    }

    public function cities(): Uri
    {
        return $this->endpoint('cities');
    }

    public function cinemas(): Uri
    {
        return $this->endpoint('cinemas');
    }

    public function shows(): Uri
    {
        return $this->endpoint('shows');
    }

    public function show(ShowSlug $show): Uri
    {
        return $this->endpoint('show/'.$show->value);
    }

    public function cinemaProgramme(CinemaSlug $cinema): Uri
    {
        return $this->endpoint('cinema/'.$cinema->value.'/shows');
    }

    public function showtimes(ShowSlug $show, CinemaSlug $cinema): Uri
    {
        return $this->endpoint('show/'.$show->value.'/showtimes/'.$cinema->value);
    }

    private function endpoint(string $path): Uri
    {
        return $this->base->resolve($path)->withQuery(http_build_query(['language' => 'fr']));
    }
}
