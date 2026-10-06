<?php

namespace App\Tests\Builder;

/**
 * Builds the answers of the providers with the shapes of the real ones: OpenID Connect claims for
 * the local provider (same reading as Google), /user and /user/emails for GitHub, whose public
 * email is optional and not necessarily verified.
 */
final class OAuthServerBuilder
{
    /** @var array<string, array{0: int, 1: array|string}> */
    private array $responses = [];

    public static function anOAuthServer(): self
    {
        return new self();
    }

    public function withLocalUser(string $subject, ?string $email, bool $emailVerified = true): self
    {
        return $this->withOidcUser('local', $subject, $email, $emailVerified);
    }

    /**
     * Any OpenID Connect provider whose endpoints are /{provider}/token and /{provider}/userinfo.
     */
    public function withOidcUser(string $provider, string $subject, ?string $email, bool $emailVerified = true): self
    {
        $clone = clone $this;
        $clone->responses['/'.$provider.'/token'] = [200, self::accessToken()];
        $clone->responses['/'.$provider.'/userinfo'] = [200, ['sub' => $subject, 'email' => $email, 'email_verified' => $emailVerified, 'name' => $subject]];

        return $clone;
    }

    public function withGithubUser(int $id, string $login, ?string $publicEmail = null): self
    {
        $clone = clone $this;
        $clone->responses['/login/oauth/access_token'] = [200, self::accessToken()];
        $clone->responses['/user'] = [200, ['id' => $id, 'login' => $login, 'name' => $login, 'email' => $publicEmail]];
        $clone->responses['/user/emails'] ??= [200, []];

        return $clone;
    }

    public function withGithubEmail(string $email, bool $primary, bool $verified): self
    {
        $clone = clone $this;
        $clone->responses['/user/emails'] ??= [200, []];
        $clone->responses['/user/emails'][1][] = ['email' => $email, 'primary' => $primary, 'verified' => $verified, 'visibility' => null];

        return $clone;
    }

    /**
     * GitHub answers /user/emails with something that is not JSON (outage page, proxy error...).
     */
    public function withGithubEmailsAnswering(int $status, string $rawBody): self
    {
        $clone = clone $this;
        $clone->responses['/user/emails'] = [$status, $rawBody];

        return $clone;
    }

    /**
     * @return array<string, array{0: int, 1: array|string}> URL path => [HTTP status, JSON body or raw body]
     */
    public function build(): array
    {
        return $this->responses;
    }

    private static function accessToken(): array
    {
        return ['access_token' => 'an-access-token', 'token_type' => 'Bearer', 'expires_in' => 3600];
    }
}
