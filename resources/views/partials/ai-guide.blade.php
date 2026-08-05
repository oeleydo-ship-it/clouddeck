{{-- Floating AI guide for signed-in users when a superadmin has enabled it. --}}
@auth
@if($aiGuideEnabled ?? false)
<div class="fixed bottom-5 right-5 z-50" x-data="{
        open: false,
        sending: false,
        message: '',
        error: '',
        history: [],
        async send() {
            const text = this.message.trim();
            if (! text || this.sending) return;
            this.error = '';
            this.sending = true;
            this.history.push({ role: 'user', content: text });
            this.message = '';
            try {
                const response = await fetch(@js(route('guide.chat')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({
                        message: text,
                        history: this.history.slice(0, -1).slice(-10),
                    }),
                });
                const data = await response.json();
                if (! response.ok) {
                    this.error = data.message || 'Could not get a reply.';
                    this.history.pop();
                } else {
                    this.history.push({ role: 'assistant', content: data.reply });
                    this.$nextTick(() => {
                        const box = this.$refs.thread;
                        if (box) box.scrollTop = box.scrollHeight;
                    });
                }
            } catch (e) {
                this.error = 'Network error. Try again.';
                this.history.pop();
            } finally {
                this.sending = false;
            }
        },
    }" @keydown.escape.window="open = false">
    <div x-cloak x-show="open" x-transition class="mb-3 flex w-[min(100vw-2.5rem,22rem)] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/15 dark:border-white/10 dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-white/5">
            <div>
                <p class="text-sm font-semibold heading">AI guide</p>
                <p class="text-[11px] muted">Ask how to use {{ $branding['name'] ?? 'the platform' }}</p>
            </div>
            <button type="button" class="icon-button" @click="open = false" aria-label="Close guide">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-4"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="max-h-72 space-y-3 overflow-y-auto px-4 py-3" x-ref="thread">
            <template x-if="history.length === 0">
                <p class="text-sm muted">Try: “How do I connect a Contabo server?” or “How do I enable SSL?”</p>
            </template>
            <template x-for="(item, index) in history" :key="index">
                <div class="rounded-xl px-3 py-2 text-sm" :class="item.role === 'user' ? 'ml-6 bg-sky-50 text-sky-900 dark:bg-sky-400/10 dark:text-sky-100' : 'mr-2 bg-slate-50 heading dark:bg-white/5'">
                    <p class="whitespace-pre-wrap" x-text="item.content"></p>
                </div>
            </template>
            <p x-show="error" class="text-sm text-rose-600 dark:text-rose-300" x-text="error"></p>
        </div>
        <form class="border-t border-slate-100 p-3 dark:border-white/5" @submit.prevent="send">
            <div class="flex gap-2">
                <input type="text" class="field !mt-0 !py-2 text-sm" x-model="message" :disabled="sending" maxlength="2000" placeholder="Ask a question…" aria-label="Ask the AI guide">
                <button type="submit" class="button-primary !px-3 !py-2 text-sm" :disabled="sending">Send</button>
            </div>
        </form>
    </div>
    <button type="button" class="button-primary ml-auto flex size-12 items-center justify-center rounded-full !p-0 shadow-lg shadow-sky-500/30" @click="open = ! open" :aria-expanded="open" aria-label="Open AI guide">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5"><path d="M21 15a4 4 0 0 1-4 4H7l-4 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>
    </button>
</div>
@endif
@endauth
