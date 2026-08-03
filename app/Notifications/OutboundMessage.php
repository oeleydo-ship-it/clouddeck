<?php

namespace App\Notifications;

/**
 * What every CloudDeck notification reduces to before it leaves for Slack, Discord, Telegram,
 * an SMS, or a push. Each destination formats it differently, but none of them get to decide
 * what the event was.
 */
final class OutboundMessage
{
    public function __construct(
        public readonly string $event,
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url = null,
        public readonly string $severity = 'info',
    ) {}

    /** A single line, for destinations that have no concept of a title. */
    public function plain(): string
    {
        return trim($this->title."\n".$this->body.($this->url ? "\n".$this->url : ''));
    }
}
