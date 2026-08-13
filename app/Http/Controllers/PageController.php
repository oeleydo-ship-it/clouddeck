<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\Plan;
use App\Models\Post;
use App\Services\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PageController extends Controller
{
    public function home(): Response
    {
        $settings = app(SystemSettings::class);
        $seo = $settings->pageSeo('home');
        $managed = $settings->managedServersEnabled();

        return Inertia::render('Marketing/Home', [
            'posts' => Post::published()->latest('published_at')->limit(3)->get(['id', 'title', 'slug', 'excerpt', 'published_at', 'cover_path'])->map(fn (Post $post) => $this->postCard($post)),
            'plans' => Plan::query()
                ->where('active', true)
                ->where('public', true)
                ->orderBy('sort_order')
                ->orderBy('monthly_price')
                ->get()
                ->map(fn (Plan $plan) => [
                    ...$plan->toArray(),
                    'quota_lines' => $plan->quotaLines($managed),
                    'monthly_price_label' => $plan->formattedPrice('monthly_price'),
                    'yearly_price_label' => $plan->yearly_price ? $plan->formattedPrice('yearly_price') : null,
                ]),
            'landing' => $settings->landing(),
            'managedServersEnabled' => $managed,
            'dnsEnabled' => $settings->dnsEnabled(),
            'stagingSitesEnabled' => $settings->stagingSitesEnabled(),
            'title' => $seo['title'],
            'metaDescription' => $seo['description'],
            'ogImage' => $seo['og_image'],
        ]);
    }

    public function about(): Response
    {
        return $this->marketingPage('about', 'Marketing/About');
    }

    public function features(): Response
    {
        return $this->marketingPage('features', 'Marketing/Features');
    }

    public function useCases(): Response
    {
        return $this->marketingPage('use_cases', 'Marketing/UseCases');
    }

    public function contact(): Response
    {
        return $this->marketingPage('contact', 'Marketing/Contact');
    }

    public function submitContact(Request $request, SystemSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = ContactMessage::create([...$data, 'ip_address' => $request->ip()]);

        if ($support = $settings->get('support_email')) {
            try {
                Mail::to($support)->send(new ContactMessageReceived($message));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return back()->with('status', 'Thanks — your message reached us. We will reply to '.$data['email'].'.');
    }

    private function marketingPage(string $page, string $component): Response
    {
        $settings = app(SystemSettings::class);
        $seo = $settings->pageSeo($page);

        $headings = [
            'about' => 'About',
            'features' => 'Features',
            'use_cases' => 'Use cases',
            'contact' => 'Send us a message.',
        ];

        return Inertia::render($component, [
            'title' => $seo['title'],
            'heading' => $headings[$page] ?? $seo['title'],
            'metaDescription' => $seo['description'],
            'ogImage' => $seo['og_image'],
            'landing' => $settings->landing(),
            'managedServersEnabled' => $settings->managedServersEnabled(),
            'dnsEnabled' => $settings->dnsEnabled(),
            'stagingSitesEnabled' => $settings->stagingSitesEnabled(),
            'supportEmail' => $settings->get('support_email'),
        ]);
    }

    /**
     * @return array{id: mixed, title: string, slug: string, excerpt: ?string, published_at: ?string, cover_url: ?string}
     */
    private function postCard(Post $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'published_at' => $post->published_at?->toIso8601String(),
            'cover_url' => $post->cover_url,
        ];
    }
}
