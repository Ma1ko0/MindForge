// MindForge — German phonetic rhyme analyzer (client-side).
// German spelling is close to pronunciation, so a rules-based phonetic
// matcher works well. v2 uses a VOWEL-ASSONANCE model (like rap rhyme tools):
//   - groups words by the vowel sequence of their rhyme tail → catches slant
//     rhymes (Leben/reden, Augen/rauchen), not just perfect ones
//   - analyses EVERY word, so internal rhymes (Binnenreime) show up too
//   - perfect rhymes (consonants match too) get extra emphasis
//
// Exposes window.MindForgeRhyme: key, vowelKey, analyze, suggest, buildIndex, lastWord

(function () {
    if (window.MindForgeRhyme) return;

    const VOWELS = 'aeiouäöü';
    const isVowel = (c) => VOWELS.includes(c);

    // High-frequency grammatical words — filtered only for INTERNAL words
    // (to keep internal-rhyme highlighting from lighting up every "der/die/und").
    // Line-end words are never filtered, so end rhymes always count.
    // Deliberately small + free of rhyme-rich words (mein/dein/sein/auf/aus … stay in).
    const STOP = new Set((
        'der die das den dem des ein eine einer einem einen ' +
        'und oder aber doch denn dass weil als ob ' +
        'ich du er es wir ihr mir dir mich dich sich uns euch ihm ihn ihnen ' +
        'im in an am zu von mit bei ' +
        'so wenn dann je kein nicht ist sind war'
    ).split(/\s+/));

    function vowelGroupStarts(w) {
        const starts = [];
        for (let i = 0; i < w.length; i++) {
            if (isVowel(w[i]) && (i === 0 || !isVowel(w[i - 1]))) starts.push(i);
        }
        return starts;
    }

    // Rhyme region: from the nucleus vowel to the end. Words ending in an
    // unstressed schwa syllable (-e, -en, -er …) take the preceding vowel.
    function extractTail(w) {
        const g = vowelGroupStarts(w);
        if (g.length === 0) return w;
        if (g.length === 1) return w.slice(g[0]);
        const lastV = g[g.length - 1];
        const tailFromLast = w.slice(lastV);
        const unstressed = /^e([mnrslt]|nd|nt|st|rn|rt|ln)?$/;
        const nucleus = unstressed.test(tailFromLast) ? g[g.length - 2] : lastV;
        return w.slice(nucleus);
    }

    // Perfect rhyme key — full phonetic tail incl. consonants.
    function normalizePerfect(t) {
        t = t.replace(/ie/g, 'i');
        t = t.replace(/eu/g, 'oi').replace(/äu/g, 'oi');
        t = t.replace(/ei/g, 'ai').replace(/ey/g, 'ai');
        t = t.replace(/ah/g, 'a').replace(/äh/g, 'e').replace(/eh/g, 'e')
             .replace(/ih/g, 'i').replace(/oh/g, 'o').replace(/öh/g, 'ö')
             .replace(/uh/g, 'u').replace(/üh/g, 'ü');
        t = t.replace(/aa/g, 'a').replace(/ee/g, 'e').replace(/oo/g, 'o');
        t = t.replace(/ph/g, 'f').replace(/v/g, 'f');
        t = t.replace(/ß/g, 's');
        t = t.replace(/chs/g, 'ks').replace(/x/g, 'ks').replace(/ck/g, 'k');
        t = t.replace(/d$/, 't').replace(/b$/, 'p').replace(/g$/, 'k');
        t = t.replace(/ä/g, 'e');
        t = t.replace(/([bcdfghjklmnpqrstwz])\1+/g, '$1');
        return t;
    }

    // Vowel-assonance units of a rhyme region. Diphthongs become single units.
    function vowelUnits(region) {
        let r = region;
        r = r.replace(/ie/g, 'i');
        r = r.replace(/eu/g, 'Y').replace(/äu/g, 'Y');   // /ɔɪ/
        r = r.replace(/ei/g, 'A').replace(/ey/g, 'A').replace(/ai/g, 'A'); // /aɪ/
        r = r.replace(/au/g, 'U');                        // /aʊ/
        r = r.replace(/ah/g, 'a').replace(/äh/g, 'e').replace(/eh/g, 'e')
             .replace(/ih/g, 'i').replace(/oh/g, 'o').replace(/uh/g, 'u')
             .replace(/öh/g, 'ö').replace(/üh/g, 'ü');
        r = r.replace(/ä/g, 'e');
        const units = [];
        for (const ch of r) {
            if ('aeiouöüAYU'.includes(ch)) units.push(ch);
        }
        return units;
    }

    function clean(word) {
        return (word || '').toLowerCase().replace(/[^a-zäöüß]/g, '');
    }

    // English words (Denglisch) don't follow German spelling→sound rules, so
    // we look them up in a hand-mapped table that yields a rhyme region already
    // expressed in the German-normalized sound space (e.g. time → "aim").
    function englishRegion(w) {
        const eng = window.MindForgeEnglish;
        return (eng && Object.prototype.hasOwnProperty.call(eng, w)) ? eng[w] : null;
    }

    function key(word) {
        const w = clean(word);
        if (!w) return '';
        const er = englishRegion(w);
        return er !== null ? normalizePerfect(er) : normalizePerfect(extractTail(w));
    }

    function vowelKey(word) {
        const w = clean(word);
        if (!w) return '';
        const er = englishRegion(w);
        return vowelUnits(er !== null ? er : extractTail(w)).join('');
    }

    function lastWord(line) {
        const m = line.toLowerCase().match(/[a-zäöüß]+(?:'[a-zäöüß]+)?(?=[^a-zäöüß]*$)/);
        return m ? m[0].replace(/'/g, '') : '';
    }

    const COLOR_COUNT = 10;

    // Tokenize a line into word / separator tokens, each word carrying its keys.
    function tokenizeLine(line) {
        const tokens = [];
        const re = /([a-zA-ZäöüÄÖÜß]+)|([^a-zA-ZäöüÄÖÜß]+)/g;
        let m;
        let lastWordIdx = -1;
        while ((m = re.exec(line)) !== null) {
            if (m[1]) {
                tokens.push({ text: m[1], isWord: true, pk: key(m[1]), c: clean(m[1]) });
                lastWordIdx = tokens.length - 1;
            } else {
                tokens.push({ text: m[2], isWord: false });
            }
        }
        return { tokens, lastWordIdx };
    }

    function analyze(text) {
        const rawLines = (text || '').split('\n');

        const lines = rawLines.map((line) => {
            if (/^\s*\[.*\]\s*$/.test(line)) {
                return { type: 'section', text: line.trim().replace(/^\[|\]$/g, '') };
            }
            if (line.trim() === '') return { type: 'blank' };
            const t = tokenizeLine(line);
            return { type: 'line', tokens: t.tokens, lastWordIdx: t.lastWordIdx };
        });

        // A word is eligible to rhyme if it has a key and isn't a trivially short
        // word. Stop-words are filtered ONLY when internal (line-end always counts,
        // so end rhymes like "auf"/"rauf" are never suppressed).
        function eligible(t, isEnd) {
            if (!t.isWord || !t.pk || t.c.length < 2) return false;
            if (isEnd) return true;
            return !STOP.has(t.c);
        }

        // Global counts by perfect key (conservative: vowels AND coda must match)
        const pkCount = {};
        lines.forEach((l) => {
            if (l.type !== 'line') return;
            l.tokens.forEach((t, i) => {
                if (eligible(t, i === l.lastWordIdx)) {
                    pkCount[t.pk] = (pkCount[t.pk] || 0) + 1;
                }
            });
        });

        const colorOf = {};
        let colorNext = 0;
        const colorFor = (pk) => {
            if (!(pk in colorOf)) { colorOf[pk] = colorNext % COLOR_COUNT; colorNext++; }
            return colorOf[pk];
        };

        let scheme = {};
        let schemeNext = 0;

        lines.forEach((l) => {
            if (l.type === 'section') { scheme = {}; schemeNext = 0; return; }
            if (l.type !== 'line') return;

            l.tokens.forEach((t, i) => {
                if (!t.isWord) return;
                if (eligible(t, i === l.lastWordIdx) && pkCount[t.pk] > 1) {
                    t.colorIdx = colorFor(t.pk);
                } else {
                    t.colorIdx = null;
                }
            });

            const endTok = l.tokens[l.lastWordIdx];
            if (endTok && endTok.pk && pkCount[endTok.pk] > 1) {
                if (!(endTok.pk in scheme)) { scheme[endTok.pk] = String.fromCharCode(65 + (schemeNext % 26)); schemeNext++; }
                l.endScheme = scheme[endTok.pk];
                l.endColor = colorFor(endTok.pk);
            }
        });

        return { lines };
    }

    function buildIndex(words) {
        const idx = {};
        (words || []).forEach((w) => {
            const k = key(w);
            if (!k) return;
            (idx[k] = idx[k] || []).push(w);
        });
        return idx;
    }

    function suggest(word, index, limit) {
        const k = key(word);
        if (!k || !index[k]) return [];
        const wl = (word || '').toLowerCase();
        const seen = {};
        const out = [];
        for (const cand of index[k]) {
            const c = cand.toLowerCase();
            if (c === wl || seen[c]) continue;
            seen[c] = true;
            out.push(cand);
            if (out.length >= (limit || 12)) break;
        }
        return out;
    }

    window.MindForgeRhyme = { key, vowelKey, analyze, suggest, buildIndex, lastWord, COLOR_COUNT };
})();
