function showToast() {
        const toast = document.getElementById('toast');
        toast.style.opacity = '1';

        setTimeout(() => {
            toast.style.opacity = '0';
        }, 1500);
    }

    function updateHighlight(span) {
        const original = parseInt(span.dataset.original);
        const current = parseInt(span.textContent);

        if (current !== original) {
            span.classList.add("text-blue-600", "font-bold");
            span.classList.remove("text-gray-800");
        } else {
            span.classList.remove("text-blue-600", "font-bold");
            span.classList.add("text-gray-800");

            span.classList.add("pulse-back");
            setTimeout(() => span.classList.remove("pulse-back"), 600);
        }
    }

    function initEffects() {
        const configElement = document.getElementById('effects-config');
        const effectsUpdateUrl = configElement?.dataset.updateUrl || '/effects/update';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        document.querySelectorAll('.plus-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const row = btn.closest('tr');
                const category = btn.dataset.category;
                const span = row.querySelector(`.effect-value[data-category="${category}"]`);

                let value = parseInt(span.textContent);
                if (value < 5) value++;

                span.textContent = value;
                updateHighlight(span);
            });
        });

        document.querySelectorAll('.minus-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const row = btn.closest('tr');
                const category = btn.dataset.category;
                const span = row.querySelector(`.effect-value[data-category="${category}"]`);

                let value = parseInt(span.textContent);
                if (value > -5) value--;

                span.textContent = value;
                updateHighlight(span);
            });
        });

        document.querySelectorAll('.save-row-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const functionId = btn.dataset.id;
                const row = btn.closest('tr');

                const payload = {
                    function_id: functionId,
                    effects: {}
                };

                row.querySelectorAll('.effect-value').forEach(span => {
                    payload.effects[span.dataset.category] = parseInt(span.textContent);
                });

                const response = await fetch(effectsUpdateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (data.success === true) {
                    row.querySelectorAll('.effect-value').forEach(span => {
                        span.dataset.original = span.textContent;
                        updateHighlight(span);
                    });

                    showToast();
                } else {
                    showNoChangeToast();
                }
            });
        });
    }

    function showNoChangeToast() {
        const toast = document.getElementById('toast-nochange');
        toast.style.opacity = '1';

        setTimeout(() => {
            toast.style.opacity = '0';
        }, 1500);
    }

    document.addEventListener('DOMContentLoaded', initEffects);