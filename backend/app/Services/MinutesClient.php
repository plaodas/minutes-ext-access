<?php

namespace App\Services;

use App\Exceptions\MinutesException;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class MinutesClient
{
    public function provision(User $user): void
    {
        $response = $this->request(
            fn () => Http::acceptJson()
                ->withToken($this->provisionSecret())
                ->post($this->url('external/users'), [
                    'external_id' => (string) $user->getKey(),
                ]),
        );

        if (! $response->successful()) {
            throw MinutesException::fromResponse($response);
        }

        $user->forceFill([
            'minutes_user_id' => $response->json('user_id'),
            'minutes_token' => $response->json('token'),
            'minutes_token_id' => $response->json('token_id'),
        ])->save();
    }

    public function upload(User $user, UploadedFile $file): Response
    {
        return $this->withUserToken($user, function (string $token) use ($file) {
            $stream = fopen($file->getRealPath(), 'r');

            return Http::acceptJson()
                ->withToken($token)
                ->attach('file', $stream, $file->getClientOriginalName())
                ->post($this->url('transcribe-upload-bg'));
        });
    }

    public function tasks(User $user): Response
    {
        return $this->withUserToken($user, function (string $token) {
            return Http::acceptJson()
                ->withToken($token)
                ->get($this->url('bg/tasks'));
        });
    }

    public function status(User $user, string $taskId): Response
    {
        return $this->withUserToken($user, function (string $token) use ($taskId) {
            return Http::acceptJson()
                ->withToken($token)
                ->get($this->url('bg/status/'.$taskId));
        });
    }

    public function result(User $user, string $taskId): Response
    {
        return $this->withUserToken($user, function (string $token) use ($taskId) {
            return Http::acceptJson()
                ->withToken($token)
                ->get($this->url('bg/result/'.$taskId));
        });
    }

    private function withUserToken(User $user, callable $request): Response
    {
        if (! is_string($user->minutes_token) || $user->minutes_token === '') {
            $this->provision($user);
            $user->refresh();
        }

        $response = $this->request(fn () => $request($user->minutes_token));

        if (! $this->tokenWasRejected($response)) {
            return $response;
        }

        $this->provision($user);
        $user->refresh();

        return $this->request(fn () => $request($user->minutes_token));
    }

    private function tokenWasRejected(Response $response): bool
    {
        return $response->status() === 401 && $response->json('error') === 'Not authenticated';
    }

    private function request(callable $send): Response
    {
        try {
            return $send();
        } catch (ConnectionException) {
            throw new MinutesException('minutes に接続できません。', 502);
        }
    }

    private function provisionSecret(): string
    {
        $secret = config('services.minutes.provision_secret');

        if (! is_string($secret) || $secret === '') {
            throw new MinutesException('minutes の発行秘密が未設定です。', 500);
        }

        return $secret;
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.minutes.base_url'), '/').'/'.ltrim($path, '/');
    }
}
