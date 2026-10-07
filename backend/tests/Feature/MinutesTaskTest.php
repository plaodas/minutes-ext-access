<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MinutesTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_provisions_a_token_and_stores_it(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'http://minutes.test/api/external/users' => Http::response([
                'user_id' => 'minutes-user-1',
                'token' => 'service-token',
                'token_id' => 'token-1',
            ]),
            'http://minutes.test/api/transcribe-upload-bg' => Http::response([
                'task_id' => 'task-1',
            ]),
        ]);

        $response = $this->withToken(auth('api')->login($user))
            ->post('/api/minutes/tasks', [
                'file' => UploadedFile::fake()->create('meeting.mp3', 20, 'audio/mpeg'),
            ]);

        $response->assertOk()->assertJsonPath('task_id', 'task-1');
        $this->assertStringNotContainsString('test-provision-secret', $response->getContent());
        $this->assertStringNotContainsString('service-token', $response->getContent());

        $user->refresh();
        $this->assertSame('minutes-user-1', $user->minutes_user_id);
        $this->assertSame('token-1', $user->minutes_token_id);
        $this->assertSame('service-token', $user->minutes_token);
        $this->assertNotSame('service-token', $user->getRawOriginal('minutes_token'));

        Http::assertSent(function ($request) use ($user) {
            return $request->url() === 'http://minutes.test/api/external/users'
                && $request->hasHeader('Authorization', 'Bearer test-provision-secret')
                && $request['external_id'] === (string) $user->id;
        });
    }

    public function test_rejected_service_token_is_replaced_and_retried_once(): void
    {
        $user = User::factory()->create([
            'minutes_user_id' => 'minutes-user-1',
            'minutes_token_id' => 'token-1',
            'minutes_token' => 'old-token',
        ]);

        Http::fake([
            'http://minutes.test/api/external/users' => Http::response([
                'user_id' => 'minutes-user-1',
                'token' => 'new-token',
                'token_id' => 'token-2',
            ]),
            'http://minutes.test/api/transcribe-upload-bg' => Http::sequence()
                ->push(['error' => 'Not authenticated'], 401)
                ->push(['task_id' => 'task-2'], 200),
        ]);

        $response = $this->withToken(auth('api')->login($user))
            ->post('/api/minutes/tasks', [
                'file' => UploadedFile::fake()->create('meeting.wav', 20, 'audio/wav'),
            ]);

        $response->assertOk()->assertJsonPath('task_id', 'task-2');
        $this->assertSame('new-token', $user->fresh()->minutes_token);

        $uploads = Http::recorded(function ($request) {
            return $request->url() === 'http://minutes.test/api/transcribe-upload-bg';
        })->values();

        $this->assertCount(2, $uploads);
        $this->assertTrue($uploads[0][0]->hasHeader('Authorization', 'Bearer old-token'));
        $this->assertTrue($uploads[1][0]->hasHeader('Authorization', 'Bearer new-token'));

        $provisions = Http::recorded(function ($request) {
            return $request->url() === 'http://minutes.test/api/external/users';
        });
        $this->assertCount(1, $provisions);
    }

    public function test_secret_is_not_exposed_on_the_user_payload(): void
    {
        $user = User::factory()->create([
            'minutes_token' => 'service-token',
            'minutes_token_id' => 'token-1',
            'minutes_user_id' => 'minutes-user-1',
        ]);

        $response = $this->withToken(auth('api')->login($user))->getJson('/api/me');

        $response->assertOk();
        $this->assertStringNotContainsString('test-provision-secret', $response->getContent());
        $this->assertStringNotContainsString('service-token', $response->getContent());
        $response->assertJsonMissingPath('minutes_token');
    }
}
