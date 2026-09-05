<?php

declare(strict_types=1);

namespace Http;

/**
 * Immutable snapshot of one HTTP request's input arrays.
 *
 * The ONLY place in classes/ that reads $_GET/$_POST/$_REQUEST/$_SERVER for
 * controller consumption. Holds no database handle, no session, and no actor
 * identity — see tests/Http/HttpRequestTest.php.
 *
 * Accessors are exact passthrough with zero sanitization: callers keep their
 * own is_string()/is_numeric() guards, so converting a call site cannot change
 * what it accepts.
 */
final class HttpRequest
{
    /** @var array<string, mixed> */
    private array $get;
    /** @var array<string, mixed> */
    private array $post;
    /** @var array<string, mixed> */
    private array $request;
    /** @var array<string, mixed> */
    private array $server;

    /**
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     * @param array<string, mixed> $request
     * @param array<string, mixed> $server
     */
    public function __construct(array $get = [], array $post = [], array $request = [], array $server = [])
    {
        $this->get = $get;
        $this->post = $post;
        $this->request = $request;
        $this->server = $server;
    }

    public static function fromGlobals(): self
    {
        // The superglobals are stubbed as array<mixed>; narrowing here keeps the
        // constructor's array<string, mixed> contract without widening it.
        /** @var array<string, mixed> $get */
        $get = $_GET;
        /** @var array<string, mixed> $post */
        $post = $_POST;
        /** @var array<string, mixed> $request */
        $request = $_REQUEST;
        /** @var array<string, mixed> $server */
        $server = $_SERVER;

        return new self($get, $post, $request, $server);
    }

    public function get(string $key): mixed
    {
        return $this->get[$key] ?? null;
    }

    public function post(string $key): mixed
    {
        return $this->post[$key] ?? null;
    }

    public function request(string $key): mixed
    {
        return $this->request[$key] ?? null;
    }

    public function server(string $key): mixed
    {
        return $this->server[$key] ?? null;
    }

    /** @return array<string, mixed> */
    public function allGet(): array
    {
        return $this->get;
    }

    /** @return array<string, mixed> */
    public function allPost(): array
    {
        return $this->post;
    }
}
