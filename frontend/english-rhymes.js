// MindForge — Denglisch support.
// Maps common English words to a rhyme region expressed in the GERMAN-normalized
// sound space, so English words rhyme correctly with each other AND with German
// words (e.g. time→"aim"=Reim, light→"ait"=Zeit, pain→"ain"=mein).
//
// Values are written German-phonetically; they pass through the same normalizer
// as German words. Extend freely — keys must be lowercase.
(function () {
    if (window.MindForgeEnglish) return;

    const groups = {
        // /aɪt/  → matches Zeit, weit, Leid, Streit
        'ait': ['light', 'lights', 'night', 'nights', 'fight', 'fights', 'right', 'rights',
            'sight', 'tight', 'bright', 'white', 'might', 'flight', 'tonight', 'alright',
            'late', 'wait', 'great', 'state', 'hate', 'create', 'weight', 'straight', 'fate', 'gate'],
        // /aɪm/  → matches Reim
        'aim': ['time', 'times', 'rhyme', 'rhymes', 'crime', 'climb', 'prime', 'lifetime',
            'name', 'names', 'game', 'games', 'same', 'fame', 'flame', 'flames', 'blame', 'shame', 'frame'],
        // /aɪn/  → matches mein, dein, sein, Wein, allein
        'ain': ['mine', 'line', 'lines', 'shine', 'fine', 'sign', 'signs', 'design', 'divine', 'wine',
            'pain', 'rain', 'brain', 'brains', 'train', 'main', 'again', 'insane', 'plane', 'plain', 'chain'],
        // /aɪ/  → matches Mai, bei, frei, zwei
        'ai': ['high', 'cry', 'try', 'fly', 'sky', 'why', 'bye', 'my', 'guy', 'eye', 'eyes',
            'lie', 'lies', 'die', 'goodbye', 'apply', 'reply', 'supply',
            'day', 'days', 'way', 'ways', 'say', 'says', 'play', 'stay', 'may', 'grey', 'gray', 'away', 'okay', 'today'],
        // /aɪnd/ → among themselves
        'aint': ['mind', 'minds', 'find', 'kind', 'behind', 'blind', 'grind', 'remind', 'signed'],
        // /aʊ/  → matches Frau, rau, genau, schlau
        'au': ['now', 'how', 'wow', 'allow', 'somehow'],
        // /aʊn/ → matches braun, Zaun
        'aun': ['down', 'town', 'crown', 'brown', 'sound', 'around', 'ground', 'found', 'crowd', 'loud'],
        // /uː/  → matches du, Kuh, Schuh
        'u': ['you', 'true', 'blue', 'do', 'through', 'view', 'new', 'crew', 'too', 'two', 'who',
            'flew', 'knew', 'few', 'cool', 'fool', 'rule', 'school'],
        // /iː/  → matches die, sie, wie, Knie
        'i': ['free', 'me', 'see', 'be', 'key', 'three', 'tree', 'we', 'he', 'she', 'sea', 'agree'],
        // /iːm/ → among themselves
        'im': ['dream', 'dreams', 'team', 'teams', 'scheme', 'cream', 'stream', 'extreme', 'supreme'],
        // /iːl/ → among themselves
        'il': ['real', 'feel', 'deal', 'steal', 'steel', 'heal', 'reveal', 'ideal'],
        // /oʊ/  → matches so, Floh, roh
        'o': ['flow', 'slow', 'know', 'show', 'go', 'low', 'grow', 'though', 'though', 'glow', 'snow', 'throw'],
        // /oʊl/ → among themselves
        'ol': ['soul', 'souls', 'control', 'role', 'goal', 'whole', 'roll', 'gold', 'cold', 'old', 'hold', 'told', 'soul'],
        // /oʊn/ → among themselves
        'on': ['alone', 'phone', 'zone', 'stone', 'known', 'grown', 'thrown', 'shown'],
        // /ɑːrt/ → matches Art, Start, hart, zart
        'art': ['heart', 'hearts', 'start', 'part', 'apart', 'smart', 'chart'],
        // /ɛr/  → matches mehr, sehr, schwer
        'er': ['there', 'care', 'air', 'hair', 'swear', 'wear', 'share', 'fair', 'rare', 'aware', 'everywhere', 'nowhere'],
        // /ʌv/  → matches brav (→ af)
        'av': ['love', 'above', 'of'],
        // /aɪər/ → among themselves
        'aia': ['fire', 'desire', 'higher', 'liar', 'tired', 'inspire', 'entire'],
        // /ʌn/   → loosely with Mann, kann, dann
        'an': ['one', 'done', 'fun', 'run', 'sun', 'none', 'son', 'won', 'gun', 'someone', 'everyone'],
    };

    const map = {};
    for (const region in groups) {
        for (const word of groups[region]) {
            map[word] = region;
        }
    }
    window.MindForgeEnglish = map;
})();
