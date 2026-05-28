<?php

declare(strict_types=1);

namespace App;

use App\Note\Note;
use App\Note\NoteRepository;
use App\Project\ProjectRepository;
use App\TodoItem\TodoItemRepository;
use App\TodoList\TodoListRepository;
use App\Workflow\WorkflowRepository;

class NoteController extends Controller
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
        $html .= '<h1 class="display-6 fw-bold mb-1">Notizen</h1>';
        $html .= '<p class="text-muted mb-0">Dein zweites Gehirn — alles, was du nicht vergessen willst.</p>';
        $html .= '</div>';
        $html .= '<button class="btn btn-primary" hx-post="/notes" hx-target="#main-content" hx-swap="innerHTML"><i class="fa-solid fa-plus me-2"></i>Neue Notiz</button>';
        $html .= '</div>';

        $html .= '<div class="dashboard-card mb-4">';
        $html .= '<div class="dashboard-card-body">';
        $html .= '<div class="input-group due-input-group">';
        $html .= '<span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>';
        $html .= '<input class="form-control" type="search" name="q" placeholder="In Titel und Inhalt suchen …" ';
        $html .= 'value="' . htmlspecialchars($search) . '" ';
        $html .= 'hx-get="/notes/search" hx-trigger="keyup changed delay:250ms, search" hx-target="#notes-list" hx-swap="innerHTML">';
        $html .= '</div>';
        $html .= '</div></div>';

        $html .= '<div id="notes-list" class="notes-grid">';
        $html .= $this->renderNoteList($userId, $search);
        $html .= '</div>';

        $html .= '</div>';
        $this->response->text($html);
    }

    public function searchHtml(): void
    {
        $userId = $this->currentUserId();
        $search = (string)$this->request->getQuery('q');
        $this->response->text($this->renderNoteList($userId, $search));
    }

    private function renderNoteList(int $userId, string $search): string
    {
        $repo = new NoteRepository($this->pdo);
        $notes = $repo->listByUserId($userId, $search === '' ? null : $search);
        if (empty($notes)) {
            $msg = $search === '' ? 'Noch keine Notizen — leg deine erste an.' : 'Keine Notiz passt zu „' . htmlspecialchars($search) . '".';
            return '<div class="lists-empty"><div class="lists-empty-icon"><i class="fa-regular fa-file-lines"></i></div><h3 class="mb-2">Nichts gefunden</h3><p class="text-muted mb-0">' . $msg . '</p></div>';
        }
        $html = '';
        foreach ($notes as $note) {
            $html .= $this->renderNoteCard($note);
        }
        return $html;
    }

    private function renderNoteCard(Note $note): string
    {
        $title = trim($note->getTitle()) !== '' ? $note->getTitle() : 'Ohne Titel';
        $excerpt = $this->makeExcerpt($note->getContent(), 140);
        $updated = $this->humanTimeAgo((string)$note->getUpdatedAt());

        $html  = '<div class="note-card" hx-get="/notes/' . $note->getId() . '" hx-target="#main-content" hx-swap="innerHTML">';
        $html .= '<div class="note-card-head">';
        $html .= '<h3 class="note-card-title">' . htmlspecialchars($title) . '</h3>';
        $html .= '<button type="button" class="list-card-delete" onclick="event.stopPropagation()" hx-delete="/notes/' . $note->getId() . '" hx-target="closest .note-card" hx-swap="outerHTML" hx-confirm="Notiz wirklich löschen?" title="Löschen"><i class="fa-solid fa-trash-can"></i></button>';
        $html .= '</div>';
        if ($note->getProjectId() !== null) {
            $project = (new ProjectRepository($this->pdo))->getById($note->getProjectId(), $note->getUserId());
            if ($project !== null) {
                $html .= '<a class="project-chip project-color-' . htmlspecialchars($project->getColor()) . '" onclick="event.stopPropagation()" hx-get="/projects/' . $project->getId() . '" hx-target="#main-content" hx-swap="innerHTML"><i class="fa-solid fa-' . htmlspecialchars($project->getIcon()) . ' me-1"></i>' . htmlspecialchars($project->getName()) . '</a>';
            }
        }
        if ($excerpt !== '') {
            $html .= '<p class="note-card-excerpt">' . htmlspecialchars($excerpt) . '</p>';
        } else {
            $html .= '<p class="note-card-excerpt text-muted fst-italic">Leer …</p>';
        }
        $html .= '<div class="note-card-meta"><i class="fa-regular fa-clock me-1"></i>' . htmlspecialchars($updated) . '</div>';
        $html .= '</div>';
        return $html;
    }

    public function viewHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $note = (new NoteRepository($this->pdo))->getById((int)$id, $userId);
        if ($note === null) {
            $this->response->text('<p class="alert alert-warning">Notiz nicht gefunden.</p>', 404);
            return;
        }

        $rendered = $this->resolveReferences($note->getContent(), $userId);
        $rendered = \App\Tags::linkifyMarkdown($rendered);
        $title = trim($note->getTitle()) !== '' ? $note->getTitle() : 'Ohne Titel';

        $html  = '<div class="dashboard-page">';

        $html .= '<div class="dashboard-header mb-4 d-flex align-items-center gap-3">';
        $html .= '<button class="btn-icon" hx-get="/notes" hx-target="#main-content" hx-swap="innerHTML" title="Zurück"><i class="fa-solid fa-arrow-left"></i></button>';
        $html .= '<div class="flex-grow-1 min-w-0">';
        $html .= '<h1 class="display-6 fw-bold mb-1 text-truncate">' . htmlspecialchars($title) . '</h1>';
        $html .= '<p class="text-muted mb-0">Zuletzt bearbeitet: ' . htmlspecialchars($this->humanTimeAgo((string)$note->getUpdatedAt())) . '</p>';
        $html .= '</div>';
        $html .= '<div class="d-flex gap-2">';
        $html .= '<button class="btn btn-primary" hx-get="/notes/' . $note->getId() . '/edit" hx-target="#main-content" hx-swap="innerHTML"><i class="fa-solid fa-pen me-2"></i>Bearbeiten</button>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div class="dashboard-card mb-4">';
        $html .= '<div class="dashboard-card-body">';
        $html .= '<div class="note-markdown">';
        $html .= '<textarea hidden data-md-source>' . htmlspecialchars($rendered) . '</textarea>';
        $html .= '<div class="note-rendered">' . htmlspecialchars(mb_substr($note->getContent(), 0, 200)) . ' …</div>';
        $html .= '</div>';
        $html .= '</div></div>';

        // Backlinks
        $backlinks = (new NoteRepository($this->pdo))->backlinks($note->getId(), $userId, $note->getTitle());
        if (!empty($backlinks)) {
            $html .= '<div class="dashboard-card">';
            $html .= '<div class="dashboard-card-head"><h2 class="h5 mb-0"><i class="fa-solid fa-link me-2 text-info"></i>Referenziert in</h2></div>';
            $html .= '<div class="dashboard-card-body">';
            $html .= '<ul class="recent-list list-unstyled mb-0">';
            foreach ($backlinks as $bl) {
                $blTitle = trim($bl->getTitle()) !== '' ? $bl->getTitle() : 'Ohne Titel';
                $html .= '<li class="recent-item">';
                $html .= '<div class="recent-item-content">';
                $html .= '<a class="recent-item-link" hx-get="/notes/' . $bl->getId() . '" hx-target="#main-content" hx-swap="innerHTML">' . htmlspecialchars($blTitle) . '</a>';
                $html .= '<span class="recent-item-meta">' . htmlspecialchars($this->humanTimeAgo((string)$bl->getUpdatedAt())) . '</span>';
                $html .= '</div>';
                $html .= '</li>';
            }
            $html .= '</ul>';
            $html .= '</div></div>';
        }

        $html .= '</div>';
        $this->response->text($html);
    }

    public function editHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $note = (new NoteRepository($this->pdo))->getById((int)$id, $userId);
        if ($note === null) {
            $this->response->text('<p class="alert alert-warning">Notiz nicht gefunden.</p>', 404);
            return;
        }
        $this->response->text($this->renderEditor($note));
    }

    public function dailyHtml(): void
    {
        $userId = $this->currentUserId();
        $today = (new \DateTime())->format('Y-m-d');
        $repo = new NoteRepository($this->pdo);

        $note = $repo->findByTitle($today, $userId);
        if ($note === null) {
            $content = $this->buildDailyTemplate($userId, $today);
            $id = $repo->create($userId, $today, $content);
        } else {
            $id = $note->getId();
        }
        $this->viewHtml((string)$id);
    }

    private function buildDailyTemplate(int $userId, string $date): string
    {
        $dt = new \DateTime($date);
        $days = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
        $months = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
        $friendly = $days[(int)$dt->format('w')] . ', ' . $dt->format('j') . '. ' . $months[(int)$dt->format('n') - 1] . ' ' . $dt->format('Y');

        $md  = "# " . $friendly . "\n\n";

        // Today's due tasks
        $itemRepo = new TodoItemRepository($this->pdo);
        $tasks = $itemRepo->getItemsDueOnDate($userId, $date);
        $md .= "## ☀️ Heute fällig\n\n";
        if (empty($tasks)) {
            $md .= "_Keine Aufgaben mit Fälligkeit heute._\n\n";
        } else {
            foreach ($tasks as $t) {
                $check = (int)$t['isChecked'] === 1 ? '[x]' : '[ ]';
                $md .= "- " . $check . " " . (string)$t['content']
                    . "  ·  *[[Liste: " . (string)$t['listName'] . "]]*\n";
            }
            $md .= "\n";
        }

        // Today's workflow blocks
        $workflowRepo = new WorkflowRepository($this->pdo);
        $blocks = $workflowRepo->getBlocksForDay($userId, $date);
        $md .= "## 📅 Heutige Workflow-Blöcke\n\n";
        if (empty($blocks)) {
            $md .= "_Noch kein Plan für heute. [Tag planen](/workflow)_\n\n";
        } else {
            foreach ($blocks as $b) {
                $start = substr((string)$b['startTime'], 0, 5);
                $end   = substr((string)$b['endTime'], 0, 5);
                $md .= "- **" . $start . "–" . $end . "** " . (string)$b['title'] . "\n";
            }
            $md .= "\n";
        }

        // Journal
        $md .= "## 🧠 Gedanken & Notizen\n\n_Was geht dir durch den Kopf? Schreib's auf …_\n\n";

        // Evening review
        $md .= "## 🌙 Tages-Review\n\n";
        $md .= "- Was lief gut?\n";
        $md .= "- Was würde ich beim nächsten Mal anders machen?\n";
        $md .= "- Worauf bin ich heute stolz?\n";

        return $md;
    }

    public function createHtml(): void
    {
        $userId = $this->currentUserId();
        $repo = new NoteRepository($this->pdo);
        try {
            $id = $repo->create($userId, 'Neue Notiz', '');
            $note = $repo->getById($id, $userId);
            if ($note === null) {
                $this->response->text('<p>Fehler beim Anlegen.</p>', 500);
                return;
            }
            $this->response->text($this->renderEditor($note));
        } catch (\Throwable $ex) {
            $this->logger->logging('Note create failed: ' . $ex->getMessage(), ERROR);
            $this->response->text('<p>Fehler: ' . htmlspecialchars($ex->getMessage()) . '</p>', 500);
        }
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
        $repo = new NoteRepository($this->pdo);
        $ok = $repo->update((int)$id, $userId, $title, $content);
        if (!$ok) {
            $this->response->text('<p>Konnte nicht speichern.</p>', 500);
            return;
        }
        $repo->setProjectId((int)$id, $userId, $projectId);
        // After save: show view mode
        $this->viewHtml($id);
    }

    public function deleteHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $ok = (new NoteRepository($this->pdo))->delete((int)$id, $userId);
        if (!$ok) {
            $this->response->text('<p>Löschen fehlgeschlagen.</p>', 500);
            return;
        }
        // Card-row will be removed via outerHTML swap on the trigger element.
        $this->response->text('');
    }

    private function renderEditor(Note $note): string
    {
        $title = $note->getTitle();
        $content = $note->getContent();
        $id = $note->getId();
        $currentProjectId = $note->getProjectId();
        $projects = (new ProjectRepository($this->pdo))->listByUserId($note->getUserId());

        $html  = '<div class="dashboard-page">';

        $html .= '<form class="note-editor" hx-put="/notes/' . $id . '" hx-target="#main-content" hx-swap="innerHTML">';

        $html .= '<div class="dashboard-header mb-4 d-flex align-items-center gap-3">';
        $html .= '<button type="button" class="btn-icon" hx-get="/notes/' . $id . '" hx-target="#main-content" hx-swap="innerHTML" title="Abbrechen"><i class="fa-solid fa-arrow-left"></i></button>';
        $html .= '<input class="form-control form-control-lg flex-grow-1 note-title-input" type="text" name="title" maxlength="200" placeholder="Titel" value="' . htmlspecialchars($title) . '" required>';
        $html .= '<button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-2"></i>Speichern</button>';
        $html .= '</div>';

        if (!empty($projects)) {
            $html .= '<div class="dashboard-card mb-4"><div class="dashboard-card-body">';
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

        $html .= '<div class="dashboard-card">';
        $html .= '<div class="dashboard-card-head"><h2 class="h5 mb-0"><i class="fa-solid fa-pen me-2 text-info"></i>Inhalt (Markdown)</h2></div>';
        $html .= '<div class="dashboard-card-body">';
        $html .= '<textarea class="form-control note-editor-textarea" name="content" rows="20" placeholder="Schreib hier …&#10;&#10;Tipp: [[Andere Notiz]] verlinkt zu einer anderen Notiz. Markdown wird beim Anzeigen formatiert.">' . htmlspecialchars($content) . '</textarea>';
        $html .= '<div class="form-text mt-2">Markdown unterstützt · <code>[[Titel]]</code> verlinkt eine andere Notiz · STRG+S speichert</div>';
        $html .= '</div></div>';

        $html .= '</form>';

        // STRG+S Shortcut
        $html .= '<script>(function(){var f=document.querySelector(".note-editor");if(!f)return;document.addEventListener("keydown",function e(ev){if((ev.ctrlKey||ev.metaKey)&&ev.key==="s"){if(!document.body.contains(f)){document.removeEventListener("keydown",e);return;}ev.preventDefault();f.requestSubmit();}});})();</script>';

        $html .= '</div>';
        return $html;
    }

    private function resolveReferences(string $markdown, int $userId): string
    {
        $noteRepo = new NoteRepository($this->pdo);
        $listRepo = new TodoListRepository($this->pdo);
        return preg_replace_callback(
            '/\[\[([^\]\n]+)\]\]/u',
            function ($matches) use ($noteRepo, $listRepo, $userId) {
                $raw = trim($matches[1]);

                // [[Liste: Name]] → todo list
                if (preg_match('/^Liste:\s*(.+)$/iu', $raw, $m)) {
                    $listName = trim($m[1]);
                    try {
                        $list = $listRepo->getTodoListByName($listName, $userId);
                        return '[📋 ' . $list->getName() . '](/todo/lists/' . $list->getId() . ')';
                    } catch (\Throwable) {
                        return '<span class="ref-broken" title="Liste nicht gefunden">[[' . htmlspecialchars($raw) . ']]</span>';
                    }
                }

                $note = $noteRepo->findByTitle($raw, $userId);
                if ($note !== null) {
                    return '[' . $note->getTitle() . '](/notes/' . $note->getId() . ')';
                }
                return '<span class="ref-broken" title="Notiz nicht gefunden">[[' . htmlspecialchars($raw) . ']]</span>';
            },
            $markdown
        );
    }

    private function makeExcerpt(string $content, int $maxLen): string
    {
        // strip markdown noise lightly
        $text = preg_replace('/[#*_`>~\-]+/u', ' ', $content);
        $text = preg_replace('/\[\[([^\]]+)\]\]/u', '$1', $text);
        $text = preg_replace('/!\[[^\]]*\]\([^\)]*\)/u', '', $text);
        $text = preg_replace('/\[([^\]]+)\]\([^\)]*\)/u', '$1', $text);
        $text = preg_replace('/\s+/u', ' ', (string)$text);
        $text = trim((string)$text);
        if (mb_strlen($text) <= $maxLen) {
            return $text;
        }
        return mb_substr($text, 0, $maxLen - 1) . '…';
    }

    private function humanTimeAgo(string $datetime): string
    {
        try {
            $then = new \DateTime($datetime);
            $now = new \DateTime();
            $diff = $now->getTimestamp() - $then->getTimestamp();
            if ($diff < 60) return 'gerade eben';
            if ($diff < 3600) return floor($diff / 60) . ' Min';
            if ($diff < 86400) return floor($diff / 3600) . ' Std';
            if ($diff < 604800) return floor($diff / 86400) . ' Tg';
            return $then->format('d.m.Y');
        } catch (\Throwable) {
            return $datetime;
        }
    }
}
