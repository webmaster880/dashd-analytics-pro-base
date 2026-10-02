(function () {
    'use strict';
    const form = document.getElementById('dashd-date-format-form');
    if (!form) return;
    const preset = document.getElementById('dashd-date-preset');
    const input = document.getElementById('dashd-date-format');
    const preview = document.getElementById('dashd-date-preview');
    let timer;
    let revision = 0;
    let controller;

    function schedulePreview() {
        clearTimeout(timer);
        if (controller) controller.abort();
        const current = ++revision;
        preview.setAttribute('aria-busy', 'true');
        timer = setTimeout(async function () {
            controller = new AbortController();
            try {
                const body = new URLSearchParams({
                    action: 'dashd_preview_update_date',
                    nonce: form.elements.dashd_display_nonce.value,
                    date_format: input.value
                });
                const response = await fetch(window.ajaxurl, { method: 'POST', body, signal: controller.signal });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error('Preview failed');
                if (current === revision) preview.textContent = result.data.preview;
            } catch (error) {
                if (current === revision && error.name !== 'AbortError') {
                    preview.textContent = form.dataset.previewError;
                }
            } finally {
                if (current === revision) preview.setAttribute('aria-busy', 'false');
            }
        }, 300);
    }

    preset.addEventListener('change', function () {
        if (preset.value === 'custom') {
            input.focus();
            return;
        }
        input.value = preset.value;
        schedulePreview();
    });
    input.addEventListener('input', function () {
        const matching = Array.from(preset.options).find(option => option.value !== 'custom' && option.value === input.value);
        preset.value = matching ? matching.value : 'custom';
        schedulePreview();
    });
})();
