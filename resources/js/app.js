import './bootstrap';

function parseSearchData() {
    const el = document.getElementById('ui-search-data');
    if (!el?.textContent) {
        return [];
    }
    try {
        return JSON.parse(el.textContent);
    } catch {
        return [];
    }
}

function initSearchPalette() {
    const overlay = document.getElementById('ui-search-overlay');
    const input = document.getElementById('ui-search-input');
    const results = document.getElementById('ui-search-results');
    const triggers = [
        document.getElementById('ui-search-trigger'),
        document.getElementById('ui-search-trigger-mobile'),
    ].filter(Boolean);

    if (!overlay || !input || !results) {
        return;
    }

    const items = parseSearchData();
    let activeIndex = 0;
    let filtered = items;

    function render() {
        const q = input.value.trim().toLowerCase();
        filtered = !q
            ? items
            : items.filter((row) => row.label.toLowerCase().includes(q));

        activeIndex = 0;
        results.innerHTML = filtered
            .map(
                (row, i) => `
                <a href="${row.href}" data-ui-search-idx="${i}" class="flex items-center gap-3 px-5 py-2.5 text-zinc-200 no-underline transition hover:bg-white/[0.04] ${i === activeIndex ? 'bg-white/[0.06]' : ''}">
                    <span class="truncate text-[13px]">${escapeHtml(row.label)}</span>
                </a>`,
            )
            .join('');

        if (filtered.length === 0) {
            results.innerHTML = '<div class="px-5 py-8 text-center text-[13px] text-zinc-500">No results</div>';
        }
    }

    function escapeHtml(s) {
        return String(s)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;');
    }

    function updateActiveClasses() {
        results.querySelectorAll('[data-ui-search-idx]').forEach((node) => {
            const idx = Number(node.getAttribute('data-ui-search-idx'));
            node.classList.toggle('bg-white/[0.06]', idx === activeIndex);
        });
    }

    function open() {
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        input.value = '';
        render();
        requestAnimationFrame(() => input.focus());
    }

    function close() {
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
    }

    triggers.forEach((t) => t.addEventListener('click', open));

    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            close();
        }
    });

    document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            if (overlay.classList.contains('hidden')) {
                open();
            } else {
                close();
            }
            return;
        }

        if (overlay.classList.contains('hidden')) {
            return;
        }

        if (e.key === 'Escape') {
            e.preventDefault();
            close();
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIndex = Math.min(activeIndex + 1, Math.max(0, filtered.length - 1));
            updateActiveClasses();
        }

        if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIndex = Math.max(activeIndex - 1, 0);
            updateActiveClasses();
        }

        if (e.key === 'Enter' && filtered[activeIndex]) {
            e.preventDefault();
            window.location.href = filtered[activeIndex].href;
        }
    });

    input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        filtered = !q ? items : items.filter((row) => row.label.toLowerCase().includes(q));
        activeIndex = 0;
        render();
    });

    results.addEventListener('mousemove', (e) => {
        const link = e.target.closest('[data-ui-search-idx]');
        if (!link) {
            return;
        }
        activeIndex = Number(link.getAttribute('data-ui-search-idx'));
        updateActiveClasses();
    });
}

document.addEventListener('DOMContentLoaded', initSearchPalette);
