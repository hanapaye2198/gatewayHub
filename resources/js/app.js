/**
 * Copy helper for dashboard pages (works when Livewire strips inline scripts after wire:navigate).
 *
 * @param {string} text
 * @returns {Promise<void>}
 */
window.gatewayhubCopyText = async function gatewayhubCopyText(text) {
    if (text === undefined || text === null || String(text) === '') {
        throw new Error('empty');
    }

    const value = String(text);

    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(value);

            return;
        } catch {
            // Fall through to execCommand fallback.
        }
    }

    const textarea = document.createElement('textarea');
    textarea.value = value;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    textarea.setSelectionRange(0, value.length);

    try {
        const ok = document.execCommand('copy');
        if (! ok) {
            throw new Error('execCommand failed');
        }
    } finally {
        document.body.removeChild(textarea);
    }
};
