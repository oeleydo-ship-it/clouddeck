<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiBlogGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
    }

    private function customer(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function enableAiBlog(string $key = 'sk-blog-test'): void
    {
        $settings = app(SystemSettings::class);
        $settings->put('ai_blog_enabled', '1', 'boolean', true);
        $settings->put('openai_api_key', $key, 'string', false);
        $settings->put('openai_model', 'gpt-4o-mini', 'string', true);
    }

    public function test_superadmin_can_enable_ai_blog_alongside_guide_settings(): void
    {
        $this->actingAs($this->admin())->put('/admin/settings/ai', [
            'ai_provider' => 'openai',
            'ai_guide_enabled' => '1',
            'ai_blog_enabled' => '1',
            'openai_api_key' => 'sk-test-blog',
            'openai_model' => 'gpt-4o-mini',
        ])->assertSessionHas('status');

        $settings = app(SystemSettings::class);
        $this->assertTrue($settings->aiGuideEnabled());
        $this->assertTrue($settings->aiBlogEnabled());
        $this->assertSame('openai', $settings->aiProvider());
    }

    public function test_blog_ai_endpoints_require_enablement_and_superadmin(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/admin/posts/ai/suggest-topics', ['keyword' => 'ssl'])
            ->assertNotFound();

        $this->actingAs($admin)->postJson('/admin/posts/ai/generate', ['topic' => 'SSL'])
            ->assertNotFound();

        $this->enableAiBlog();

        $this->actingAs($this->customer())->postJson('/admin/posts/ai/suggest-topics', ['keyword' => 'ssl'])
            ->assertForbidden();

        $this->actingAs($this->customer())->postJson('/admin/posts/ai/generate', ['topic' => 'SSL'])
            ->assertForbidden();
    }

    public function test_suggest_topics_returns_platform_aware_json_from_openai(): void
    {
        $this->enableAiBlog('sk-live-blog');

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'topics' => [
                                [
                                    'title' => 'Zero-downtime Laravel deployments on your VPS',
                                    'keyword' => 'laravel zero downtime deploy',
                                    'angle' => 'Explain atomic releases without inventing metrics.',
                                ],
                            ],
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $this->actingAs($this->admin())
            ->postJson('/admin/posts/ai/suggest-topics', ['keyword' => 'deployments'])
            ->assertOk()
            ->assertJsonPath('topics.0.title', 'Zero-downtime Laravel deployments on your VPS')
            ->assertJsonPath('topics.0.keyword', 'laravel zero downtime deploy');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk-live-blog')
            && str_contains($request['messages'][0]['content'] ?? '', 'SaaS control plane')
            && str_contains($request['messages'][1]['content'] ?? '', 'deployments'));
    }

    public function test_generate_returns_draft_fields_matching_post_shape(): void
    {
        $this->enableAiBlog('sk-live-blog');

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'title' => 'How staging sites keep production safe',
                            'excerpt' => 'Promote only after you verify the release.',
                            'body' => "Staging is a linked site on the same server.\n\nPromote copies repo settings onto production and queues a deploy.",
                            'meta_title' => 'Staging sites for safer deploys',
                            'meta_description' => 'Use staging to verify Laravel releases before promoting to production.',
                            'suggested_keywords' => ['staging sites', 'promote to production', 'laravel paas'],
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin())
            ->postJson('/admin/posts/ai/generate', [
                'topic' => 'Staging sites',
                'keyword' => 'staging',
            ])
            ->assertOk()
            ->assertJsonPath('draft.title', 'How staging sites keep production safe')
            ->assertJsonPath('draft.excerpt', 'Promote only after you verify the release.')
            ->assertJsonPath('draft.meta_title', 'Staging sites for safer deploys')
            ->assertJsonFragment(['staging sites']);

        $body = (string) $response->json('draft.body');
        $this->assertStringContainsString('Staging is a linked site', $body);
        $this->assertStringNotContainsString('<p>', $body);
    }

    public function test_openai_errors_surface_as_validation_style_json(): void
    {
        $this->enableAiBlog();

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'error' => ['message' => 'insufficient_quota'],
            ], 429),
        ]);

        $this->actingAs($this->admin())
            ->postJson('/admin/posts/ai/generate', ['topic' => 'Monitoring'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'AI provider rejected the request: insufficient_quota']);
    }

    public function test_blog_generate_uses_moonshot_when_provider_is_kimi(): void
    {
        $settings = app(SystemSettings::class);
        $settings->put('ai_blog_enabled', '1', 'boolean', true);
        $settings->put('ai_provider', 'moonshot', 'string', true);
        $settings->put('openai_api_key', 'msk-blog', 'string', false);
        $settings->put('openai_model', 'kimi-k3', 'string', true);
        $settings->put('ai_base_url', 'https://api.moonshot.cn/v1', 'string', true);

        Http::fake([
            'https://api.moonshot.cn/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'title' => 'Kimi drafted staging guide',
                            'excerpt' => 'Draft from Moonshot.',
                            'body' => "First paragraph.\n\nSecond paragraph.",
                            'meta_title' => 'Kimi drafted staging guide',
                            'meta_description' => 'Draft from Moonshot CN endpoint.',
                            'suggested_keywords' => ['staging'],
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $this->actingAs($this->admin())
            ->postJson('/admin/posts/ai/generate', ['topic' => 'Staging'])
            ->assertOk()
            ->assertJsonPath('draft.title', 'Kimi drafted staging guide');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.moonshot.cn/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer msk-blog')
            && ($request['model'] ?? null) === 'kimi-k3'
            && ($request['reasoning_effort'] ?? null) === 'low');
    }

    public function test_blog_index_exposes_ai_panel_when_enabled(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/admin/posts')
            ->assertOk()
            ->assertSee('Generate with AI')
            ->assertSee('Enable in AI settings');

        $this->enableAiBlog();

        $this->actingAs($admin)
            ->get('/admin/posts')
            ->assertOk()
            ->assertSee('Suggest topics')
            ->assertSee('Generate draft')
            ->assertDontSee('Enable in AI settings');
    }

    public function test_blog_voice_training_settings_and_cliche_scrubbing(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/admin/settings/ai', [
            'ai_provider' => 'openai',
            'ai_blog_enabled' => '1',
            'openai_api_key' => 'sk-voice',
            'openai_model' => 'gpt-4o-mini',
            'ai_blog_avoid_phrases' => "digital world\nIn today's fast-paced digital landscape",
            'ai_blog_insert_words' => "managed VPS\nzero-downtime deploy",
            'ai_blog_style_notes' => 'Write for solo freelancers. Short sentences.',
        ])->assertSessionHas('status');

        $settings = app(SystemSettings::class);
        $this->assertSame(['digital world', "In today's fast-paced digital landscape"], $settings->aiBlogAvoidPhrases());
        $this->assertSame(['managed VPS', 'zero-downtime deploy'], $settings->aiBlogInsertWords());
        $this->assertSame('Write for solo freelancers. Short sentences.', $settings->aiBlogStyleNotes());

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'title' => 'Deploy without drama',
                            'excerpt' => "In today's fast-paced digital landscape, shipping is hard.",
                            'body' => "In today's fast-paced digital landscape we all struggle.\n\nUse a managed VPS and keep releases boring.",
                            'meta_title' => 'Deploy without drama',
                            'meta_description' => 'A digital world of deploys.',
                            'suggested_keywords' => ['deploy'],
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $response = $this->actingAs($admin)
            ->postJson('/admin/posts/ai/generate', ['topic' => 'Deploys'])
            ->assertOk();

        $draft = $response->json('draft');
        $this->assertStringNotContainsString('fast-paced digital landscape', $draft['excerpt']);
        $this->assertStringNotContainsString('fast-paced digital landscape', $draft['body']);
        $this->assertStringNotContainsString('digital world', $draft['meta_description']);

        Http::assertSent(function ($request) {
            $messages = collect($request['messages'] ?? [])->pluck('content')->implode("\n");

            return str_contains($messages, 'digital world')
                && str_contains($messages, 'managed VPS')
                && str_contains($messages, 'Write for solo freelancers');
        });
    }
}
