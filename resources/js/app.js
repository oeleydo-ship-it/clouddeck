import './bootstrap';
import QRCode from 'qrcode';

/**
 * Alpine state for site/server manage tabs.
 *
 * Kept out of Blade attributes: @js() emits single-quoted literals, which prematurely
 * close an x-data='...' attribute and dump the rest of the object as visible page text.
 *
 * Registered via Alpine.data on alpine:init (Vite ESM runs before Livewire's
 * DOMContentLoaded Alpine.start). window.managedTabs remains a fallback for expression eval.
 */
function managedTabs({ tab, keys = [], backupType = 'database', frequency = 'daily' } = {}) {
    return {
        tab,
        keys,
        backupType,
        frequency,
        init() {
            const fromQuery = new URLSearchParams(location.search).get('tab');
            const fromHash = location.hash.replace('#', '');
            if (this.keys.includes(fromQuery)) {
                this.tab = fromQuery;
            } else if (this.keys.includes(fromHash)) {
                this.tab = fromHash;
                this.persistTab(fromHash);
            }
            this.$watch('tab', (v) => this.persistTab(v));
        },
        persistTab(v) {
            const url = new URL(location.href);
            url.searchParams.set('tab', v);
            url.hash = '';
            history.replaceState(null, '', url);
        },
        ensureTab(event) {
            const form = event.target;
            if (! (form instanceof HTMLFormElement) || form.method.toUpperCase() === 'GET') {
                return;
            }
            let input = form.querySelector('input[name=_tab]');
            if (! input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = '_tab';
                form.appendChild(input);
            }
            input.value = this.tab;
        },
    };
}

window.managedTabs = managedTabs;

document.addEventListener('alpine:init', () => {
    window.Alpine.data('managedTabs', (config = {}) => managedTabs(config));
});

/**
 * Renders the two-factor setup URI as a scannable code.
 *
 * Drawn in the browser rather than fetched as an image: the URI carries the TOTP secret,
 * and asking a server — ours or anyone else's — for a picture of it would put the secret
 * in a request log for no benefit. It is already on this page; this only reshapes it.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-qr]').forEach((element) => {
        const value = element.dataset.qr;

        if (! value) {
            return;
        }

        QRCode.toCanvas(element, value, {
            width: Number(element.dataset.qrSize || 208),
            // Four modules is the quiet zone the QR spec requires. Trimming it looks
            // tidier and decodes less reliably: a reader needs that blank border to find
            // the symbol at all, and CSS padding around the canvas does not supply it.
            margin: 4,
            errorCorrectionLevel: 'M',
            color: { dark: '#191c1e', light: '#ffffff' },
        }, (error) => {
            if (error) {
                // A failed render must not leave a blank square implying a code is there.
                element.insertAdjacentHTML('afterend', '<p class="mt-2 text-xs text-rose-600 dark:text-rose-300">This code could not be drawn. Use the setup URI below instead.</p>');
            }
        });
    });
});
