<?php

namespace Tests\Feature;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\Post;
use App\Models\User;
use App\Services\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MarketingAndBlogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
    }

    private function makePost(array $attributes = []): Post
    {
        return Post::create([...[
            'title' => 'Deploying Laravel without downtime',
            'slug' => 'deploying-laravel-without-downtime',
            'excerpt' => 'How atomic releases work.',
            'body' => "First paragraph.\n\nSecond paragraph.",
            'published_at' => now()->subDay(),
        ], ...$attributes]);
    }

    /** @return array<int, array{0: string, 1: string}> */
    public static function pages(): array
    {
        return [
            ['/', 'Provision servers. Deploy sites.'],
            ['/about', 'About'],
            ['/features', 'Features'],
            ['/use-cases', 'Use cases'],
            ['/contact', 'Send us a message.'],
            ['/blog', 'Blog'],
        ];
    }

    #[DataProvider('pages')]
    public function test_every_marketing_page_renders_for_a_visitor_who_is_not_signed_in(string $path, string $heading): void
    {
        $this->markInstalled();

        $this->get($path)->assertOk()->assertSee($heading);
    }

    public function test_the_blog_shows_published_posts_and_hides_drafts_and_future_ones(): void
    {
        $this->markInstalled();
        $this->makePost();
        $this->makePost(['title' => 'A draft', 'slug' => 'a-draft', 'published_at' => null]);
        $this->makePost(['title' => 'Next week', 'slug' => 'next-week', 'published_at' => now()->addWeek()]);

        $response = $this->get('/blog')->assertOk();

        $response->assertSee('Deploying Laravel without downtime');
        $response->assertDontSee('A draft');
        $response->assertDontSee('Next week');
    }

    public function test_a_draft_is_a_404_even_with_the_slug_in_hand(): void
    {
        $this->markInstalled();
        $this->makePost(['slug' => 'secret-draft', 'published_at' => null]);

        $this->get('/blog/secret-draft')->assertNotFound();
    }

    public function test_a_scheduled_post_is_hidden_until_its_moment_arrives(): void
    {
        $this->markInstalled();
        $post = $this->makePost(['slug' => 'scheduled', 'published_at' => now()->addHour()]);

        $this->get('/blog/scheduled')->assertNotFound();

        $post->update(['published_at' => now()->subMinute()]);
        $this->get('/blog/scheduled')->assertOk();
    }

    public function test_post_bodies_are_escaped_rather_than_rendered(): void
    {
        $this->markInstalled();
        $this->makePost(['slug' => 'xss', 'body' => '<script>alert(1)</script>']);

        // An administrator writes these, but rendering raw HTML would put script into
        // every reader's browser.
        $this->get('/blog/xss')->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_a_contact_message_is_stored_even_when_mail_is_not_configured(): void
    {
        Mail::fake();
        $this->markInstalled();

        $this->post('/contact', ['name' => 'Casey', 'email' => 'casey@example.test', 'body' => 'Do you support Hetzner?'])
            ->assertSessionHas('status');

        // Mail is optional configuration; an enquiry that only ever existed as an email is
        // lost the moment SMTP is wrong, which is the state a new instance is in.
        $this->assertDatabaseHas('contact_messages', ['email' => 'casey@example.test']);
        Mail::assertNothingSent();
    }

    public function test_a_contact_message_is_emailed_to_the_support_address_when_one_is_set(): void
    {
        Mail::fake();
        $this->markInstalled();
        app(SystemSettings::class)->put('support_email', 'support@example.test');

        $this->post('/contact', ['name' => 'Casey', 'email' => 'casey@example.test', 'body' => 'Hello'])->assertSessionHas('status');

        Mail::assertSent(ContactMessageReceived::class, fn ($mail) => $mail->hasTo('support@example.test'));
    }

    public function test_the_contact_form_rejects_an_empty_submission(): void
    {
        $this->markInstalled();

        $this->post('/contact', ['name' => '', 'email' => 'not-an-email', 'body' => ''])
            ->assertSessionHasErrors(['name', 'email', 'body']);

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_an_admin_can_write_publish_and_unpublish_a_post(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/posts', ['title' => 'Hello world', 'body' => 'Some words.'])->assertSessionHas('status');
        $post = Post::where('slug', 'hello-world')->sole();
        $this->assertNull($post->published_at);
        $this->assertSame($admin->id, $post->user_id);

        $this->actingAs($admin)->patch("/admin/posts/{$post->id}/publish")->assertSessionHas('status');
        $this->assertTrue($post->fresh()->isPublished());
        $this->get('/blog/hello-world')->assertOk();

        $this->actingAs($admin)->patch("/admin/posts/{$post->id}/publish")->assertSessionHas('status');
        $this->assertNull($post->fresh()->published_at);
        $this->get('/blog/hello-world')->assertNotFound();
    }

    public function test_a_cover_image_is_stored_and_replaced_without_orphaning_the_old_file(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/posts', ['title' => 'With cover', 'body' => 'Words.', 'cover' => UploadedFile::fake()->image('a.png')]);
        $post = Post::where('slug', 'with-cover')->sole();
        $first = $post->cover_path;
        Storage::disk('public')->assertExists($first);

        $this->actingAs($admin)->patch("/admin/posts/{$post->id}", ['title' => 'With cover', 'body' => 'Words.', 'cover' => UploadedFile::fake()->image('b.png')]);

        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($post->fresh()->cover_path);
    }

    public function test_slugs_stay_unique_so_two_posts_cannot_claim_one_url(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/posts', ['title' => 'Same title', 'body' => 'One.']);

        $this->actingAs($admin)->post('/admin/posts', ['title' => 'Same title', 'body' => 'Two.'])->assertSessionHasErrors('slug');

        $this->assertSame(1, Post::count());
    }

    public function test_the_blog_admin_is_closed_to_customers(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $post = $this->makePost();

        $this->actingAs($customer)->get('/admin/posts')->assertForbidden();
        $this->actingAs($customer)->post('/admin/posts', ['title' => 'Spam', 'body' => 'Spam.'])->assertForbidden();
        $this->actingAs($customer)->delete("/admin/posts/{$post->id}")->assertForbidden();
    }
}
