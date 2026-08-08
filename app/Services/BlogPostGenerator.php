<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * AI-backed draft writer for the superadmin blog. Reuses the encrypted API key and
 * model from system settings; enablement is a separate admin toggle from the customer guide.
 */
final class BlogPostGenerator
{
    public function __construct(
        private SystemSettings $settings,
        private AiChatClient $client,
    ) {}

    public function enabled(): bool
    {
        return $this->settings->aiBlogEnabled();
    }

    /**
     * @return list<array{title: string, keyword: string, angle: string}>
     */
    public function suggestTopics(?string $keyword = null): array
    {
        $hint = filled($keyword)
            ? "Focus suggestions on the keyword or theme: \"{$keyword}\"."
            : 'Cover a mix of product education, ops how-tos, and SEO-friendly cloud/hosting topics.';

        $raw = $this->chat(
            temperature: 0.7,
            user: <<<PROMPT
Suggest 6 blog post topics for the {$this->platformName()} public marketing blog.

{$hint}

Return ONLY valid JSON (no markdown fences) shaped as:
{"topics":[{"title":"...","keyword":"...","angle":"..."}]}

Rules:
- Titles should be specific and searchable (Laravel PaaS, VPS provisioning, deployments, staging, monitoring, SSL, etc.).
- keyword is a short SEO phrase; angle is one sentence on the angle to take.
- Sound like a human editor pitching titles — concrete, not buzzwordy.
- Do not invent customer names, case studies, pricing, or metrics you do not know.
{$this->avoidPhrasesPromptBlock()}
PROMPT
        );

        $decoded = $this->decodeJson($raw);
        $topics = collect($decoded['topics'] ?? [])
            ->filter(fn ($row) => is_array($row) && filled($row['title'] ?? null))
            ->map(fn (array $row) => [
                'title' => Str::limit(trim((string) $row['title']), 160, ''),
                'keyword' => Str::limit(trim((string) ($row['keyword'] ?? '')), 80, ''),
                'angle' => Str::limit(trim((string) ($row['angle'] ?? '')), 240, ''),
            ])
            ->values()
            ->all();

        if ($topics === []) {
            throw new RuntimeException('The AI returned no usable topics.');
        }

        return $topics;
    }

    /**
     * @return array{
     *     title: string,
     *     excerpt: string,
     *     body: string,
     *     meta_title: string,
     *     meta_description: string,
     *     suggested_keywords: list<string>
     * }
     */
    public function generate(?string $topic = null, ?string $keyword = null): array
    {
        $topicLine = filled($topic)
            ? "Write a full draft for this topic: \"{$topic}\"."
            : 'Choose a strong SEO topic relevant to the product and write a full draft.';
        $keywordLine = filled($keyword)
            ? "Primary keyword / theme to weave in naturally: \"{$keyword}\"."
            : 'Pick a primary keyword related to cloud management, servers, sites, or deployments.';

        $raw = $this->chat(
            temperature: 0.65,
            user: <<<PROMPT
{$topicLine}
{$keywordLine}

Return ONLY valid JSON (no markdown fences) shaped as:
{"title":"...","excerpt":"...","body":"...","meta_title":"...","meta_description":"...","suggested_keywords":["..."]}

Field rules:
- title: max 160 characters, clear and specific.
- excerpt: 1–2 sentences, max 500 characters, suitable for the blog index card.
- body: PLAIN TEXT only (no HTML, no Markdown headings/lists). Separate paragraphs with a blank line. Aim for 600–1200 words of useful, accurate product-aware content. Do not invent fake customers, quotes, pricing, uptime %, or case-study metrics.
- meta_title: max 180 characters (may match title).
- meta_description: max 320 characters, SEO-friendly.
- suggested_keywords: 3–8 short phrases (informational only; the blog has no tag field).

Human writing rules (mandatory):
- Write like an experienced ops engineer blogging for peers — natural, specific, slightly imperfect rhythm. Vary sentence length. Prefer concrete verbs and console steps over abstractions.
- Do NOT open with a sweeping “digital era / landscape / world” hook. Start with a real problem, a decision, or a task.
- Avoid filler transitions, rhetorical questions stacked in a row, and generic conclusions that restate the intro.
{$this->avoidPhrasesPromptBlock()}
{$this->insertWordsPromptBlock()}
{$this->styleNotesPromptBlock()}
PROMPT
        );

        $decoded = $this->decodeJson($raw);

        $title = $this->scrubPhrases(trim((string) ($decoded['title'] ?? '')));
        $body = $this->scrubPhrases($this->normalizeBody((string) ($decoded['body'] ?? '')));
        $excerpt = $this->scrubPhrases(trim((string) ($decoded['excerpt'] ?? '')));
        $metaTitle = $this->scrubPhrases(trim((string) ($decoded['meta_title'] ?? $title)));
        $metaDescription = $this->scrubPhrases(trim((string) ($decoded['meta_description'] ?? '')));

        if ($title === '' || $body === '') {
            throw new RuntimeException('The AI returned an incomplete draft.');
        }

        $keywords = collect($decoded['suggested_keywords'] ?? [])
            ->filter(fn ($word) => is_string($word) && trim($word) !== '')
            ->map(fn (string $word) => Str::limit(trim($word), 60, ''))
            ->unique()
            ->take(8)
            ->values()
            ->all();

        return [
            'title' => Str::limit($title, 160, ''),
            'excerpt' => Str::limit($excerpt, 500, ''),
            'body' => Str::limit($body, 200000, ''),
            'meta_title' => Str::limit($metaTitle !== '' ? $metaTitle : $title, 180, ''),
            'meta_description' => Str::limit($metaDescription, 320, ''),
            'suggested_keywords' => $keywords,
        ];
    }

    private function chat(float $temperature, string $user): string
    {
        if (! $this->enabled()) {
            throw new RuntimeException('AI blog generation is not enabled. Enable it under Admin → AI and save an API key.');
        }

        return $this->client->chat(
            messages: [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $user],
            ],
            temperature: $temperature,
            timeout: 90,
        );
    }

    private function systemPrompt(): string
    {
        $name = $this->platformName();

        return <<<PROMPT
You are a human marketing and technical writer for {$name}, a SaaS control plane (Laravel PaaS) that helps teams provision Ubuntu VPS servers, deploy Laravel and WordPress sites, and run day-to-day ops while their cloud bill stays with their provider.

Product surface you may reference accurately:
- Providers and servers: connect cloud/VPS by IP, SSH keys, Ubuntu provisioning
- Sites: Laravel and WordPress deployments from GitHub, GitLab, or Bitbucket; deploy scripts; PHP versions
- Deployments: atomic releases, webhooks, rollback
- Staging sites linked to production and promote-to-production
- SSL, databases, workers/queues, DNS, firewall (UFW), SSH
- Monitoring, site uptime/DNS checks, auto-heal
- Backups, security detection, remote nginx/management
- Teams, plans/billing (Stripe), documentation, SEO for the public site

Voice — write like a person, not a model:
- Clear, practical, slightly conversational. Short paragraphs. Occasional first person plural (“we”) is fine.
- Lead with the operator’s problem or the next step they need to take.
- Never use stock AI openers or clichés (especially “digital world”, “fast-paced digital landscape”, “delve”, “unlock the power”, “game-changer”, “cutting-edge”).
- Prefer specific product language over vague “solutions” and “ecosystems”.
Never invent fake customer logos, testimonials, revenue numbers, or proprietary competitor internals.
When unsure about a specific product detail, stay general rather than inventing UI labels that may not exist.
Output must follow the user's JSON schema exactly when asked for JSON.
PROMPT;
    }

    private function avoidPhrasesPromptBlock(): string
    {
        $phrases = $this->settings->aiBlogAvoidPhrases();
        if ($phrases === []) {
            return '';
        }

        $list = collect($phrases)->map(fn (string $p) => '- '.$p)->implode("\n");

        return <<<BLOCK

Banned phrases / patterns — do not use these (or close paraphrases) anywhere in the output:
{$list}
BLOCK;
    }

    private function insertWordsPromptBlock(): string
    {
        $words = $this->settings->aiBlogInsertWords();
        if ($words === []) {
            return '';
        }

        $list = collect($words)->map(fn (string $w) => '- '.$w)->implode("\n");

        return <<<BLOCK

House phrases to weave in naturally where they fit (do not force every item; never dump them as a list):
{$list}
BLOCK;
    }

    private function styleNotesPromptBlock(): string
    {
        $notes = $this->settings->aiBlogStyleNotes();
        if (! filled($notes)) {
            return '';
        }

        return "\n\nAdditional house style from the editor:\n".trim($notes);
    }

    /**
     * Soft scrub so banned clichés that still slip through are removed from saved drafts.
     */
    public function scrubPhrases(string $text): string
    {
        foreach ($this->settings->aiBlogAvoidPhrases() as $phrase) {
            if ($phrase === '') {
                continue;
            }
            $text = preg_replace('/'.preg_quote($phrase, '/').'/iu', '', $text) ?? $text;
        }

        $text = preg_replace('/[ \t]{2,}/', ' ', $text) ?? $text;
        $text = preg_replace('/ +\n/', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function platformName(): string
    {
        return $this->settings->branding()['name'] ?: 'Uplary';
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $raw): array
    {
        $trimmed = trim($raw);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $trimmed, $matches)) {
            $trimmed = trim($matches[1]);
        }

        $decoded = json_decode($trimmed, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('The AI returned invalid JSON. Try again.');
        }

        return $decoded;
    }

    private function normalizeBody(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        // Strip accidental HTML/Markdown the model sometimes emits despite instructions.
        $body = strip_tags($body);
        $body = preg_replace('/^#{1,6}\s+/m', '', $body) ?? $body;
        $body = preg_replace('/\n{3,}/', "\n\n", $body) ?? $body;

        return trim($body);
    }
}
