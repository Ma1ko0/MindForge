// MindForge — Lyrics UI wiring: live rhyme rendering + suggestions.
// Activates whenever a lyrics view/editor is swapped into the DOM.
(function () {
    if (window.__mindforgeLyricsInit) return;
    window.__mindforgeLyricsInit = true;

    const R = () => window.MindForgeRhyme;

    let suggestionIndex = null;   // key -> [words]
    let corpusLoaded = false;

    function escapeHtml(s) {
        return (s || '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    }

    function buildBaseIndex() {
        const seed = window.MindForgeWords || [];
        suggestionIndex = R().buildIndex(seed);
    }

    function mergeWords(words) {
        if (!suggestionIndex) buildBaseIndex();
        (words || []).forEach((w) => {
            const k = R().key(w);
            if (!k) return;
            const arr = (suggestionIndex[k] = suggestionIndex[k] || []);
            if (!arr.some((x) => x.toLowerCase() === w.toLowerCase())) arr.push(w);
        });
    }

    function ensureCorpus() {
        if (corpusLoaded) return Promise.resolve();
        corpusLoaded = true;
        return fetch('/lyrics/corpus', { headers: { 'Accept': 'application/json' } })
            .then((r) => r.ok ? r.json() : { words: [] })
            .then((data) => mergeWords((data && data.words) || []))
            .catch(() => {});
    }

    function renderLineTokens(tokens, showColors) {
        let html = '';
        tokens.forEach((t) => {
            if (!t.isWord) { html += escapeHtml(t.text); return; }
            if (showColors && t.colorIdx !== null && t.colorIdx !== undefined) {
                const cls = 'rhyme-word rhyme-c' + t.colorIdx + (t.perfect ? ' rhyme-perfect' : '');
                html += '<span class="' + cls + '">' + escapeHtml(t.text) + '</span>';
            } else {
                html += escapeHtml(t.text);
            }
        });
        return html;
    }

    function renderAnalysis(container, text, showColors) {
        const result = R().analyze(text);
        let html = '';
        result.lines.forEach((line) => {
            if (line.type === 'section') {
                html += '<div class="lyrics-section"><i class="fa-solid fa-music me-2"></i>' + escapeHtml(line.text) + '</div>';
            } else if (line.type === 'blank') {
                html += '<div class="lyrics-blank"></div>';
            } else {
                html += '<div class="lyrics-line">';
                html += '<span class="lyrics-line-text">' + renderLineTokens(line.tokens, showColors) + '</span>';
                if (line.endScheme) {
                    const schemeColor = (line.endColor !== null && line.endColor !== undefined) ? ' rhyme-c' + line.endColor : '';
                    html += '<span class="lyrics-scheme' + schemeColor + '">' + line.endScheme + '</span>';
                }
                html += '</div>';
            }
        });
        container.innerHTML = html || '<div class="text-muted">Noch nichts geschrieben …</div>';
    }

    function currentLineLastWord(textarea) {
        const pos = textarea.selectionStart;
        const val = textarea.value;
        const lineStart = val.lastIndexOf('\n', pos - 1) + 1;
        let lineEnd = val.indexOf('\n', pos);
        if (lineEnd === -1) lineEnd = val.length;
        const line = val.slice(lineStart, lineEnd);
        return R().lastWord(line);
    }

    function renderSuggestions(box, hint, word) {
        if (!word) {
            box.hidden = true;
            if (hint) hint.textContent = '';
            return;
        }
        const sugg = R().suggest(word, suggestionIndex || {}, 14);
        if (hint) hint.textContent = 'Reime auf „' + word + '"';
        if (sugg.length === 0) {
            box.hidden = false;
            box.innerHTML = '<span class="text-muted small">Keine Vorschläge für „' + escapeHtml(word) + '" — schreib mehr, dein Wortschatz wächst mit.</span>';
            return;
        }
        box.hidden = false;
        box.innerHTML = '<div class="lyrics-suggest-label small text-muted mb-1">Reimt auf „' + escapeHtml(word) + '":</div>'
            + sugg.map((w) => '<span class="rhyme-suggest-chip">' + escapeHtml(w) + '</span>').join('');
    }

    function debounce(fn, ms) {
        let t;
        return function () {
            clearTimeout(t);
            const args = arguments;
            t = setTimeout(() => fn.apply(null, args), ms);
        };
    }

    function rhymeDefault() {
        return !window.MindForgeSettings || window.MindForgeSettings.rhymeColorsDefault();
    }

    function initViewMode(root) {
        const source = root.querySelector('[data-lyrics-source]');
        const rendered = root.querySelector('[data-lyrics-rendered]');
        const toggle = root.querySelector('[data-rhyme-toggle]');
        if (!source || !rendered) return;

        if (toggle) toggle.checked = rhymeDefault();
        const draw = () => renderAnalysis(rendered, source.value, !toggle || toggle.checked);
        draw();
        if (toggle) toggle.addEventListener('change', draw);
    }

    function initEditMode(root) {
        const input = root.querySelector('[data-lyrics-input]');
        const rendered = root.querySelector('[data-lyrics-rendered]');
        const suggestBox = root.querySelector('[data-lyrics-suggest]');
        const hint = root.querySelector('[data-rhyme-suggest-hint]');
        if (!input || !rendered) return;

        if (!suggestionIndex) buildBaseIndex();
        ensureCorpus();

        const redraw = () => {
            renderAnalysis(rendered, input.value, rhymeDefault());
            if (suggestBox) renderSuggestions(suggestBox, hint, currentLineLastWord(input));
        };
        const debounced = debounce(redraw, 200);

        input.addEventListener('input', debounced);
        input.addEventListener('keyup', debounce(() => {
            if (suggestBox) renderSuggestions(suggestBox, hint, currentLineLastWord(input));
        }, 150));
        input.addEventListener('click', () => {
            if (suggestBox) renderSuggestions(suggestBox, hint, currentLineLastWord(input));
        });

        redraw();
    }

    function activate(root) {
        if (!root || !root.querySelector || !R()) return;
        if (root.matches && root.matches('[data-lyrics-view]')) initViewMode(root);
        else if (root.querySelector('[data-lyrics-view]')) initViewMode(root.querySelector('[data-lyrics-view]'));
        if (root.matches && root.matches('[data-lyrics-editor]')) initEditMode(root);
        else if (root.querySelector('[data-lyrics-editor]')) initEditMode(root.querySelector('[data-lyrics-editor]'));
    }

    // Attach to document (exists even when this script runs in <head>);
    // htmx:afterSwap bubbles up to document.
    document.addEventListener('htmx:afterSwap', function (e) {
        if (e.detail && e.detail.target) activate(e.detail.target);
    });

    // Initial pass once the DOM is ready (in case a lyrics page is already present)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            if (R()) activate(document.body);
        });
    } else if (R()) {
        activate(document.body);
    }
})();
