<?php

declare(strict_types=1);

namespace App;

use App\Lyrics\Lyrics;
use App\Lyrics\LyricsRepository;
use App\Project\ProjectRepository;

class LyricsController extends Controller
{
    private function currentUserId(): int
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (empty($userId)) {
            $this->response->text('<p>Nicht eingeloggt.</p>', 401);
        }
        return (int)$userId;
    }

    public function indexHtml(): void
    {
        $userId = $this->currentUserId();
        $search = (string)$this->request->getQuery('q');

        $html  = '<div class="dashboard-page">';
        $html .= '<div class="dashboard-header mb-4 d-flex align-items-center justify-content-between gap-3 flex-wrap">';
        $html .= '<div>';
        $html .= '<h1 class="display-6 fw-bold mb-1">Lyrics</h1>';
        $html .= '<p class="text-muted mb-0">Songtexte mit Reim-Analyse und Sektionen.</p>';
        $html .= '</div>';
        $html .= '<button class="btn btn-primary" hx-post="/lyrics" hx-target="#main-content" hx-swap="innerHTML"><i class="fa-solid fa-plus me-2"></i>Neuer Song</button>';
        $html .= '</div>';

        $html .= '<div class="dashboard-card mb-4"><div class="dashboard-card-body">';
        $html .= '<div class="input-group due-input-group">';
        $html .= '<span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>';
        $html .= '<input class="form-control" type="search" name="q" placeholder="In Titel und Text suchen …" ';
        $html .= 'value="' . htmlspecialchars($search) . '" ';
        $html .= 'hx-get="/lyrics/search" hx-trigger="keyup changed delay:250ms, search" hx-target="#lyrics-list" hx-swap="innerHTML">';
        $html .= '</div>';
        $html .= '</div></div>';

        $html .= '<div id="lyrics-list" class="notes-grid">';
        $html .= $this->renderList($userId, $search);
        $html .= '</div>';

        $html .= '</div>';
        $this->response->text($html);
    }

    public function searchHtml(): void
    {
        $userId = $this->currentUserId();
        $search = (string)$this->request->getQuery('q');
        $this->response->text($this->renderList($userId, $search));
    }

    private function renderList(int $userId, string $search): string
    {
        $repo = new LyricsRepository($this->pdo);
        $songs = $repo->listByUserId($userId, $search === '' ? null : $search);
        if (empty($songs)) {
            $msg = $search === '' ? 'Noch keine Songs — schreib deinen ersten.' : 'Kein Song passt zu „' . htmlspecialchars($search) . '".';
            return '<div class="lists-empty"><div class="lists-empty-icon"><i class="fa-solid fa-music"></i></div><h3 class="mb-2">Nichts hier</h3><p class="text-muted mb-0">' . $msg . '</p></div>';
        }
        $html = '';
        foreach ($songs as $song) {
            $html .= $this->renderCard($song);
        }
        return $html;
    }

    private function renderCard(Lyrics $song): string
    {
        $title = trim($song->getTitle()) !== '' ? $song->getTitle() : 'Ohne Titel';
        $lineCount = $song->getContent() === '' ? 0 : count(array_filter(explode("\n", $song->getContent()), fn ($l) => trim($l) !== '' && !preg_match('/^\s*\[.*\]\s*$/', $l)));

        $html  = '<div class="note-card lyrics-card" hx-get="/lyrics/' . $song->getId() . '" hx-target="#main-content" hx-swap="innerHTML">';
        $html .= '<div class="note-card-head">';
        $html .= '<h3 class="note-card-title"><i class="fa-solid fa-music me-2 text-info"></i>' . htmlspecialchars($title) . '</h3>';
        $html .= '<button type="button" class="list-card-delete" onclick="event.stopPropagation()" hx-delete="/lyrics/' . $song->getId() . '" hx-target="closest .note-card" hx-swap="outerHTML" hx-confirm="Song wirklich löschen?" title="Löschen"><i class="fa-solid fa-trash-can"></i></button>';
        $html .= '</div>';
        $html .= '<div class="note-card-meta"><i class="fa-solid fa-align-left me-1"></i>' . $lineCount . ' Zeilen</div>';
        $html .= '</div>';
        return $html;
    }

    public function viewHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $song = (new LyricsRepository($this->pdo))->getById((int)$id, $userId);
        if ($song === null) {
            $this->response->text('<p class="alert alert-warning">Song nicht gefunden.</p>', 404);
            return;
        }
        $title = trim($song->getTitle()) !== '' ? $song->getTitle() : 'Ohne Titel';

        $html  = '<div class="dashboard-page" data-lyrics-view>';
        $html .= '<div class="dashboard-header mb-4 d-flex align-items-center gap-3">';
        $html .= '<button class="btn-icon" hx-get="/lyrics" hx-target="#main-content" hx-swap="innerHTML" title="Zurück"><i class="fa-solid fa-arrow-left"></i></button>';
        $html .= '<div class="flex-grow-1 min-w-0">';
        $html .= '<h1 class="display-6 fw-bold mb-1 text-truncate"><i class="fa-solid fa-music me-2 text-info"></i>' . htmlspecialchars($title) . '</h1>';
        $html .= '</div>';
        $html .= '<button class="btn btn-primary" hx-get="/lyrics/' . $song->getId() . '/edit" hx-target="#main-content" hx-swap="innerHTML"><i class="fa-solid fa-pen me-2"></i>Bearbeiten</button>';
        $html .= '</div>';

        $html .= '<div class="dashboard-card">';
        $html .= '<div class="dashboard-card-head d-flex align-items-center justify-content-between">';
        $html .= '<h2 class="h5 mb-0"><i class="fa-solid fa-wand-magic-sparkles me-2 text-info"></i>Reim-Analyse</h2>';
        $html .= '<label class="form-check form-switch mb-0 small text-muted"><input class="form-check-input" type="checkbox" data-rhyme-toggle checked> Reimfarben</label>';
        $html .= '</div>';
        $html .= '<div class="dashboard-card-body">';
        $html .= '<textarea hidden data-lyrics-source>' . htmlspecialchars($song->getContent()) . '</textarea>';
        $html .= '<div class="lyrics-rendered" data-lyrics-rendered></div>';
        $html .= '</div></div>';

        $html .= '</div>';
        $this->response->text($html);
    }

    public function editHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $song = (new LyricsRepository($this->pdo))->getById((int)$id, $userId);
        if ($song === null) {
            $this->response->text('<p class="alert alert-warning">Song nicht gefunden.</p>', 404);
            return;
        }
        $this->response->text($this->renderEditor($song));
    }

    public function createHtml(): void
    {
        $userId = $this->currentUserId();
        $repo = new LyricsRepository($this->pdo);
        $id = $repo->create($userId, 'Neuer Song', "[Verse]\n\n\n[Chorus]\n");
        $song = $repo->getById($id, $userId);
        if ($song === null) {
            $this->response->text('<p>Fehler beim Anlegen.</p>', 500);
            return;
        }
        $this->response->text($this->renderEditor($song));
    }

    public function updateHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $title = trim((string)($this->request->getPostData('title') ?? ''));
        $content = (string)($this->request->getPostData('content') ?? '');
        $projectId = (int)($this->request->getPostData('projectId') ?? 0) ?: null;
        if ($title === '') {
            $title = 'Ohne Titel';
        }
        if (mb_strlen($title) > 200) {
            $title = mb_substr($title, 0, 200);
        }
        $repo = new LyricsRepository($this->pdo);
        if (!$repo->update((int)$id, $userId, $title, $content)) {
            $this->response->text('<p>Konnte nicht speichern.</p>', 500);
            return;
        }
        $repo->setProjectId((int)$id, $userId, $projectId);
        $this->viewHtml($id);
    }

    public function deleteHtml(string $id): void
    {
        $userId = $this->currentUserId();
        if (!(new LyricsRepository($this->pdo))->delete((int)$id, $userId)) {
            $this->response->text('<p>Löschen fehlgeschlagen.</p>', 500);
            return;
        }
        $this->response->text('');
    }

    /** JSON: all distinct words from the user's lyrics — personal rhyme corpus. */
    public function corpusJson(): void
    {
        $userId = $this->currentUserId();
        $content = (new LyricsRepository($this->pdo))->getAllContentForUser($userId);
        // extract words (letters incl. umlauts), lowercase, unique, drop very short
        preg_match_all('/[a-zA-ZäöüÄÖÜß]{3,}/u', $content, $m);
        $words = array_values(array_unique(array_map('mb_strtolower', $m[0] ?? [])));
        $this->response->json(['words' => $words]);
    }

    private function renderEditor(Lyrics $song): string
    {
        $title = $song->getTitle();
        $content = $song->getContent();
        $id = $song->getId();
        $currentProjectId = $song->getProjectId();
        $projects = (new ProjectRepository($this->pdo))->listByUserId($song->getUserId());

        $html  = '<div class="dashboard-page" data-lyrics-editor>';

        $html .= '<form class="lyrics-editor-form" hx-put="/lyrics/' . $id . '" hx-target="#main-content" hx-swap="innerHTML">';

        $html .= '<div class="dashboard-header mb-4 d-flex align-items-center gap-3">';
        $html .= '<button type="button" class="btn-icon" hx-get="/lyrics/' . $id . '" hx-target="#main-content" hx-swap="innerHTML" title="Abbrechen"><i class="fa-solid fa-arrow-left"></i></button>';
        $html .= '<input class="form-control form-control-lg flex-grow-1 note-title-input" type="text" name="title" maxlength="200" placeholder="Songtitel" value="' . htmlspecialchars($title) . '" required>';
        $html .= '<button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-2"></i>Speichern</button>';
        $html .= '</div>';

        if (!empty($projects)) {
            $html .= '<div class="dashboard-card mb-3"><div class="dashboard-card-body">';
            $html .= '<label class="form-label small text-muted mb-1">Projekt</label>';
            $html .= '<select class="form-select" name="projectId">';
            $html .= '<option value=""' . ($currentProjectId === null ? ' selected' : '') . '>— Kein Projekt —</option>';
            foreach ($projects as $p) {
                $sel = (int)$p->getId() === (int)$currentProjectId ? ' selected' : '';
                $html .= '<option value="' . $p->getId() . '"' . $sel . '>' . htmlspecialchars($p->getName()) . '</option>';
            }
            $html .= '</select>';
            $html .= '</div></div>';
        }

        $html .= '<div class="lyrics-split">';

        // Left: textarea
        $html .= '<div class="dashboard-card lyrics-split-pane">';
        $html .= '<div class="dashboard-card-head"><h2 class="h6 mb-0"><i class="fa-solid fa-pen me-2 text-info"></i>Text</h2></div>';
        $html .= '<div class="dashboard-card-body">';
        $html .= '<textarea class="form-control lyrics-textarea" name="content" data-lyrics-input rows="22" placeholder="[Verse]&#10;Zeile eins …&#10;Zeile zwei …&#10;&#10;[Chorus]&#10;…">' . htmlspecialchars($content) . '</textarea>';
        $html .= '<div class="form-text mt-2"><code>[Verse]</code>, <code>[Chorus]</code>, <code>[Bridge]</code> markieren Sektionen · Reime werden live analysiert</div>';
        $html .= '</div></div>';

        // Right: live analysis
        $html .= '<div class="dashboard-card lyrics-split-pane">';
        $html .= '<div class="dashboard-card-head d-flex align-items-center justify-content-between">';
        $html .= '<h2 class="h6 mb-0"><i class="fa-solid fa-wand-magic-sparkles me-2 text-info"></i>Live-Analyse</h2>';
        $html .= '<span class="lyrics-rhyme-hint small text-muted" data-rhyme-suggest-hint></span>';
        $html .= '</div>';
        $html .= '<div class="dashboard-card-body">';
        $html .= '<div class="lyrics-rendered" data-lyrics-rendered></div>';
        $html .= '<div class="lyrics-suggest" data-lyrics-suggest hidden></div>';
        $html .= '</div></div>';

        $html .= '</div>'; // split

        $html .= '</form>';

        // STRG+S
        $html .= '<script>(function(){var f=document.querySelector(".lyrics-editor-form");if(!f)return;document.addEventListener("keydown",function e(ev){if((ev.ctrlKey||ev.metaKey)&&ev.key==="s"){if(!document.body.contains(f)){document.removeEventListener("keydown",e);return;}ev.preventDefault();f.requestSubmit();}});})();</script>';

        $html .= '</div>';
        return $html;
    }
}
