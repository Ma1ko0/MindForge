// MindForge — compact seed list of common German words for rhyme suggestions.
// Grouped client-side by phonetic rhyme key. Extend freely; the user's own
// lyrics corpus is merged on top of this at runtime.
(function () {
    if (window.MindForgeWords) return;
    window.MindForgeWords = [
        // -eit / -eid / -ait
        'Zeit', 'Leid', 'weit', 'breit', 'Streit', 'Kleid', 'Seite', 'bereit', 'Freiheit',
        'Wahrheit', 'Klarheit', 'Dunkelheit', 'Ewigkeit', 'Zweisamkeit', 'Einsamkeit',
        // -ein / -ain
        'mein', 'dein', 'sein', 'allein', 'Schein', 'Stein', 'klein', 'fein', 'rein', 'Wein',
        'Pein', 'gemein', 'herein', 'Verein', 'Schrein',
        // -erz
        'Herz', 'Schmerz', 'Scherz', 'Kerze',
        // -eben
        'Leben', 'geben', 'neben', 'eben', 'streben', 'schweben', 'erleben', 'daneben',
        // -acht
        'Nacht', 'Macht', 'gedacht', 'gebracht', 'gelacht', 'sacht', 'Pracht', 'Schlacht', 'erwacht',
        // -ann / -an
        'Mann', 'kann', 'dann', 'wann', 'begann', 'Bann', 'fortan', 'Plan', 'Bahn', 'Wahn',
        // -elt (Welt/Geld/Held)
        'Welt', 'Geld', 'Held', 'Feld', 'stellt', 'fällt', 'zählt', 'erzählt', 'gesellt',
        // -ehr / -eer
        'mehr', 'sehr', 'Meer', 'leer', 'schwer', 'Verkehr', 'Begehr', 'umkehr', 'Heer',
        // -aus
        'Haus', 'raus', 'Maus', 'Applaus', 'heraus', 'hinaus', 'Graus', 'Strauß',
        // -icht
        'nicht', 'Licht', 'Gesicht', 'Gedicht', 'Pflicht', 'Sicht', 'schlicht', 'Verzicht', 'dicht', 'erpicht',
        // -inn / -in
        'Sinn', 'drin', 'bin', 'Gewinn', 'Beginn', 'Kinn', 'hin', 'Unsinn', 'darin',
        // -ut
        'Mut', 'Glut', 'Blut', 'gut', 'Flut', 'Wut', 'Hut', 'tut', 'Armut', 'Demut',
        // -aum
        'Traum', 'Raum', 'Baum', 'Schaum', 'Saum', 'kaum', 'Albtraum',
        // -onne
        'Sonne', 'Wonne', 'Tonne', 'Nonne',
        // -ar / -ahr / -aar
        'Jahr', 'klar', 'Haar', 'Paar', 'war', 'wahr', 'gar', 'Gefahr', 'sogar', 'wunderbar', 'fürwahr',
        // -uss
        'Kuss', 'muss', 'Fluss', 'Schluss', 'Genuss', 'Verdruss', 'Fuß', 'Gruß', 'bewusst',
        // -immt
        'bestimmt', 'stimmt', 'nimmt', 'schwimmt', 'klimmt',
        // -ille
        'Stille', 'Wille', 'Fülle', 'Brille', 'Grille', 'Pille',
        // -ende / -ände
        'Ende', 'Hände', 'Wände', 'sende', 'Wende', 'blende', 'Legende', 'Spende', 'behände',
        // -anz
        'Tanz', 'Glanz', 'ganz', 'Kranz', 'Distanz', 'Substanz', 'Pflanze', 'Romanze',
        // -ück
        'Glück', 'zurück', 'Stück', 'Brücke', 'drück', 'Lücke', 'Mücke', 'Tücke',
        // -und
        'und', 'Mund', 'Grund', 'Hund', 'Stunde', 'gesund', 'Bund', 'rund', 'Sekunde', 'Wunde', 'Runde',
        // -ied / -iet
        'Lied', 'müde', 'Schmied', 'Glied', 'schied', 'Miete', 'biete', 'Friede',
        // -öne / -ön
        'schön', 'Töne', 'Söhne', 'gewöhne', 'Kröne', 'Löhne',
        // -ang
        'lang', 'Klang', 'Gesang', 'sang', 'sprang', 'Drang', 'bang', 'Zwang', 'entlang', 'Anfang', 'Untergang',
        // -immer
        'immer', 'Zimmer', 'Schimmer', 'schlimmer', 'Flimmer', 'Wimmer',
        // -eben/-eden, -ede
        'Rede', 'jede', 'Schwede', 'rede',
        // -agen
        'sagen', 'tragen', 'klagen', 'wagen', 'fragen', 'Wagen', 'plagen', 'jagen', 'verzagen',
        // -ehen / -ehn
        'sehen', 'gehen', 'stehen', 'drehen', 'flehen', 'verstehen', 'geschehen', 'vergehen',
        // -ille/-iel
        'Spiel', 'viel', 'Ziel', 'fiel', 'Stiel', 'Gefühl', 'kühl', 'Stuhl', 'wühl',
        // -aben
        'haben', 'graben', 'Gaben', 'Knaben', 'laben',
        // -ören
        'hören', 'stören', 'gehören', 'betören', 'Chören', 'zerstören',
        // -alt
        'kalt', 'Gewalt', 'Gestalt', 'bald', 'Wald', 'Halt', 'alt', 'verhallt',
        // -ren / -rn
        'fahren', 'Jahren', 'Haaren', 'bewahren', 'erfahren', 'Gefahren', 'sparen',
        // -uft
        'Luft', 'Duft', 'Kluft', 'Vernunft', 'Zukunft',
        // -immel
        'Himmel', 'Schimmel', 'Bimmel', 'Gewimmel',
        // -aller / -alle
        'Halle', 'Falle', 'alle', 'Knalle', 'Welle', 'Stelle', 'schnelle', 'Quelle', 'helle',
        // -erz/-arz
        'Schwarz', 'März', 'Harz',
        // -onst / common
        'sonst', 'umsonst', 'Trost', 'Frost', 'Post',
        // -eer/-eor feelings
        'Träne', 'Sehne', 'Lehne', 'Strähne',
        // emotion-rich extras
        'allein', 'Traum', 'Schmerz', 'Sehnsucht', 'Flucht', 'Wucht', 'Sucht', 'Frucht', 'verflucht',
        'Liebe', 'Triebe', 'Hiebe', 'Diebe', 'bliebe', 'schriebe',
    ];
})();
