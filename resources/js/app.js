import './bootstrap';
import QRCode from 'qrcode';

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
