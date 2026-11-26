<?php

namespace Inkwell\Vercel;

/**
 * Minimal Upstash Redis REST client. Dependency-free on purpose, so the
 * Vercel entry point can use it before Kirby is loaded.
 */
class Redis
{
    public function __construct(
        protected string $url,
        protected string $token
    ) {
        $this->url = rtrim($url, '/');
    }

    /**
     * Returns a client when Upstash credentials are configured, else null.
     */
    public static function fromEnv(): static|null
    {
        $url   = getenv('KV_REST_API_URL') ?: getenv('UPSTASH_REDIS_REST_URL');
        $token = getenv('KV_REST_API_TOKEN') ?: getenv('UPSTASH_REDIS_REST_TOKEN');

        return $url && $token ? new static($url, $token) : null;
    }

    /**
     * Runs one Redis command, e.g. ['SET', 'key', 'value'].
     */
    public function command(array $command): mixed
    {
        $curl = curl_init($this->url);
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(array_map('strval', $command)),
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $this->token, 'Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
        ]);

        $body   = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $data   = json_decode((string)$body, true);

        if ($body === false || $status !== 200 || isset($data['error'])) {
            throw new \RuntimeException('Redis command failed: ' . ($data['error'] ?? 'HTTP ' . $status));
        }

        return $data['result'] ?? null;
    }

    /**
     * Fixed-window rate limit. Returns false once $max hits are exceeded
     * within $seconds for the given key.
     */
    public function allow(string $key, int $max, int $seconds): bool
    {
        $count = (int)$this->command(['INCR', $key]);

        if ($count === 1) {
            $this->command(['EXPIRE', $key, $seconds]);
        }

        return $count <= $max;
    }
}
