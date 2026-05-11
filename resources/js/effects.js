function showToast() {
    const toast = document.getElementById('toast');
    toast.style.opacity = '1';

    setTimeout(() => {
        toast.style.opacity = '0';
    }, 1500);
}

function showNoChangeToast() {
    const toast = document.getElementById('toast-nochange');
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
    document.querySelectorAll('.effect-value').forEach(span => {
        updateHighlight(span);
    });
}

document.addEventListener('DOMContentLoaded', initEffects);
