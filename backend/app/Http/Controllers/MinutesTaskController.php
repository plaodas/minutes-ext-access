<?php

namespace App\Http\Controllers;

use App\Exceptions\MinutesException;
use App\Models\User;
use App\Services\MinutesClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

class MinutesTaskController extends Controller
{
    public function index(MinutesClient $minutes): JsonResponse
    {
        return $this->proxy(fn () => $minutes->tasks($this->user()));
    }

    public function store(Request $request, MinutesClient $minutes): JsonResponse
    {
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                function (string $attribute, UploadedFile $file, \Closure $fail): void {
                    $extension = strtolower($file->getClientOriginalExtension());
                    $mime = (string) $file->getMimeType();
                    $allowed = ['wav', 'mp3', 'm4a', 'flac', 'ogg', 'opus'];

                    if (! in_array($extension, $allowed, true) && ! str_starts_with($mime, 'audio/')) {
                        $fail('wav、mp3、m4a、flac、ogg、opus のいずれかを選んでください。');
                    }
                },
            ],
        ]);

        return $this->proxy(fn () => $minutes->upload($this->user(), $validated['file']));
    }

    public function show(string $taskId, MinutesClient $minutes): JsonResponse
    {
        return $this->proxy(fn () => $minutes->status($this->user(), $taskId));
    }

    public function result(string $taskId, MinutesClient $minutes): JsonResponse
    {
        return $this->proxy(fn () => $minutes->result($this->user(), $taskId));
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        return $user;
    }

    private function proxy(callable $call): JsonResponse
    {
        try {
            $response = $call();
        } catch (MinutesException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
            ], $exception->status);
        }

        $body = $response->json();

        return response()->json(
            is_array($body) ? $body : ['error' => 'request failed'],
            $response->status(),
        );
    }
}
