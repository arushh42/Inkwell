<?php

namespace Inkwell\Vercel;

use Kirby\Exception\Exception;
use Kirby\Exception\NotFoundException;
use Kirby\Session\SessionStore;

/**
 * Kirby session store backed by Upstash Redis (REST API).
 *
 * Serverless functions don't share a file system, so file-based sessions
 * would randomly log editors out. Redis keys expire on their own, which
 * replaces Kirby's garbage collection.
 */
class UpstashSessionStore extends SessionStore
{
    public function __construct(
        protected string $url,
        protected string $token,
        protected string $prefix = 'kirby:session:'
    ) {
        $this->url = rtrim($url, '/');
    }

    public function createId(int $expiryTime): string
    {
        do {
            $id = static::generateId();
            // reserve the ID atomically so two requests can't claim it
            $created = $this->command(['SET', $this->key($expiryTime, $id), '', 'NX', 'EXAT', (string)$expiryTime]);
        } while ($created !== 'OK');

        return $id;
    }

    public function exists(int $expiryTime, string $id): bool
    {
        return (int)$this->command(['EXISTS', $this->key($expiryTime, $id)]) === 1;
    }

    public function lock(int $expiryTime, string $id): void
    {
        // Upstash writes are atomic; Kirby's last-write-wins semantics are fine here.
    }

    public function unlock(int $expiryTime, string $id): void
    {
    }

    public function get(int $expiryTime, string $id): string
    {
        $data = $this->command(['GET', $this->key($expiryTime, $id)]);

        if ($data === null) {
            throw new NotFoundException(
                key: 'session.filestore.notFound',
                data: ['name' => $id]
            );
        }

        return (string)$data;
    }

    public function set(int $expiryTime, string $id, string $data): void
    {
        $this->command(['SET', $this->key($expiryTime, $id), $data, 'EXAT', (string)$expiryTime]);
    }

    public function destroy(int $expiryTime, string $id): void
    {
        $this->command(['DEL', $this->key($expiryTime, $id)]);
    }

    public function collectGarbage(): void
    {
        // keys expire automatically via EXAT
    }

    protected function key(int $expiryTime, string $id): string
    {
        return $this->prefix . $expiryTime . '.' . $id;
    }

    /**
     * Runs a single Redis command via Upstash's REST API.
     */
    protected function command(array $command): mixed
    {
        $curl = curl_init($this->url);
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($command),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
        ]);

        $body   = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($body === false || $status !== 200) {
            throw new Exception('Session store unavailable (Upstash HTTP ' . $status . ')');
        }

        $response = json_decode($body, true);

        if (isset($response['error'])) {
            throw new Exception('Session store error: ' . $response['error']);
        }

        return $response['result'] ?? null;
    }
}
