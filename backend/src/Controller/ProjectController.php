<?php

declare(strict_types=1);

namespace App;

use App\Note\NoteRepository;
use App\Project\Project;
use App\Project\ProjectRepository;
use App\TodoList\TodoListRepository;

class ProjectController extends Controller
{
    private const COLORS = ['blue', 'purple', 'green', 'orange', 'red', 'gray'];
    private const COLOR_LABELS = [
        'blue' => 'Blau', 'purple' => 'Lila', 'green' => 'Grün',
        'orange' => 'Orange', 'red' => 'Rot', 'gray' => 'Grau',
    ];
    private const ICONS = ['folder','code','music','book','briefcase','lightbulb','rocket','paint-brush','graduation-cap','dumbbell','heart','flask'];

    private function currentUserId(): int
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (empty($userId)) {
            $this->response->text('<p>Nicht eingeloggt.</p>', 401);
        }
        return (int)$userId;
    }

    private function normalizeColor(?string $c): string
    {
        return in_array($c, self::COLORS, true) ? $c : 'blue';
    }

    private function normalizeIcon(?string $i): string
    {
        return in_array($i, self::ICONS, true) ? $i : 'folder';
    }

    public function indexHtml(): void
    {
        $userId = $this->currentUserId();
        $projects = (new ProjectRepository($this->pdo))->listByUserIdWithStats($userId);

        $html  = '<div class="dashboard-page">';

        $html .= '<div class="dashboard-header mb-4 d-flex align-items-center justify-content-between gap-3 flex-wrap">';
        $html .= '<div>';
        $html .= '<h1 class="display-6 fw-bold mb-1">Projekte</h1>';
        $html .= '<p class="text-muted mb-0">Bündle Listen, Notizen und alles drumrum nach Vorhaben.</p>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div class="dashboard-card mb-4">';
        $html .= '<div class="dashboard-card-head"><h2 class="h5 mb-0"><i class="fa-solid fa-plus me-2 text-info"></i>Neues Projekt</h2></div>';
        $html .= '<div class="dashboard-card-body">';
        $html .= $this->renderProjectForm(null);
        $html .= '</div></div>';

        $html .= '<div id="projects-grid" class="lists-grid">';
        if (empty($projects)) {
            $html .= '<div class="lists-empty" style="grid-column:1/-1"><div class="lists-empty-icon"><i class="fa-solid fa-folder-open"></i></div><h3 class="mb-2">Noch keine Projekte</h3><p class="text-muted mb-0">Lege oben dein erstes Projekt an.</p></div>';
        } else {
            foreach ($projects as $p) {
                $html .= $this->renderProjectCard($p);
            }
        }
        $html .= '</div>';

        $html .= '</div>';
        $this->response->text($html);
    }

    public function viewHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $project = (new ProjectRepository($this->pdo))->getById((int)$id, $userId);
        if ($project === null) {
            $this->response->text('<div class="alert alert-warning">Projekt nicht gefunden.</div>', 404);
            return;
        }

        $listRepo = new TodoListRepository($this->pdo);
        $noteRepo = new NoteRepository($this->pdo);
        $lists = $listRepo->getTodoListsByUserId($userId, $project->getId());
        $notes = $noteRepo->listByUserId($userId, null, $project->getId());

        $html  = '<div class="dashboard-page">';

        $html .= '<div class="dashboard-header mb-4 d-flex align-items-center gap-3">';
        $html .= '<button class="btn-icon" hx-get="/projects" hx-target="#main-content" hx-swap="innerHTML" title="Zurück"><i class="fa-solid fa-arrow-left"></i></button>';
        $html .= '<div class="project-icon-large project-color-' . htmlspecialchars($project->getColor()) . '"><i class="fa-solid fa-' . htmlspecialchars($project->getIcon()) . '"></i></div>';
        $html .= '<div class="flex-grow-1 min-w-0">';
        $html .= '<h1 class="display-6 fw-bold mb-1 text-truncate">' . htmlspecialchars($project->getName()) . '</h1>';
        if ($project->getDescription() !== '') {
            $html .= '<p class="text-muted mb-0">' . htmlspecialchars($project->getDescription()) . '</p>';
        }
        $html .= '</div>';
        $html .= '<div class="d-flex gap-2">';
        $html .= '<button class="btn btn-outline-light" hx-get="/projects/' . $project->getId() . '/edit" hx-target="#main-content" hx-swap="innerHTML"><i class="fa-solid fa-pen me-2"></i>Bearbeiten</button>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div class="dashboard-card mb-4">';
        $html .= '<div class="dashboard-card-head d-flex align-items-center justify-content-between"><h2 class="h5 mb-0"><i class="fa-solid fa-list-check me-2 text-info"></i>Listen</h2>';
        $html .= '<form class="d-flex gap-2" hx-post="/projects/' . $project->getId() . '/lists" hx-target="#project-lists" hx-swap="beforeend">';
        $html .= '<input class="form-control form-control-sm" type="text" name="name" placeholder="Neue Liste" required>';
        $html .= '<button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-plus"></i></button>';
        $html .= '</form>';
        $html .= '</div>';
        $html .= '<div class="dashboard-card-body">';
        $html .= '<div id="project-lists" class="lists-grid">';
        if (empty($lists)) {
            $html .= '<div class="empty-hint text-muted text-center py-3" style="grid-column:1/-1">Noch keine Listen in diesem Projekt.</div>';
        } else {
            foreach ($lists as $list) {
                $stats = $listRepo->getListStats($list->getId());
                $html .= $this->renderListCardForProject($list, $stats);
            }
        }
        $html .= '</div>';
        $html .= '</div></div>';

        $html .= '<div class="dashboard-card">';
        $html .= '<div class="dashboard-card-head d-flex align-items-center justify-content-between"><h2 class="h5 mb-0"><i class="fa-solid fa-note-sticky me-2 text-info"></i>Notizen</h2>';
        $html .= '<button class="btn btn-sm btn-primary" hx-post="/projects/' . $project->getId() . '/notes" hx-target="#main-content" hx-swap="innerHTML"><i class="fa-solid fa-plus me-2"></i>Neue Notiz</button>';
        $html .= '</div>';
        $html .= '<div class="dashboard-card-body">';
        if (empty($notes)) {
            $html .= '<div class="empty-hint text-muted text-center py-3">Noch keine Notizen in diesem Projekt.</div>';
        } else {
            $html .= '<div class="notes-grid">';
            foreach ($notes as $note) {
                $html .= $this->renderNoteCardForProject($note);
            }
            $html .= '</div>';
        }
        $html .= '</div></div>';

        $html .= '</div>';
        $this->response->text($html);
    }

    public function editHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $project = (new ProjectRepository($this->pdo))->getById((int)$id, $userId);
        if ($project === null) {
            $this->response->text('<div class="alert alert-warning">Projekt nicht gefunden.</div>', 404);
            return;
        }
        $html  = '<div class="dashboard-page">';
        $html .= '<div class="dashboard-header mb-4 d-flex align-items-center gap-3">';
        $html .= '<button class="btn-icon" hx-get="/projects/' . $project->getId() . '" hx-target="#main-content" hx-swap="innerHTML" title="Zurück"><i class="fa-solid fa-arrow-left"></i></button>';
        $html .= '<h1 class="display-6 fw-bold mb-1">Projekt bearbeiten</h1>';
        $html .= '</div>';
        $html .= '<div class="dashboard-card">';
        $html .= '<div class="dashboard-card-body">';
        $html .= $this->renderProjectForm($project);
        $html .= '<div class="mt-3 d-flex justify-content-end">';
        $html .= '<button class="btn btn-outline-danger" hx-delete="/projects/' . $project->getId() . '" hx-target="#main-content" hx-swap="innerHTML" hx-confirm="Projekt wirklich löschen? Listen und Notizen bleiben erhalten, verlieren aber die Verknüpfung."><i class="fa-solid fa-trash-can me-2"></i>Projekt löschen</button>';
        $html .= '</div>';
        $html .= '</div></div>';
        $html .= '</div>';
        $this->response->text($html);
    }

    public function createHtml(): void
    {
        $userId = $this->currentUserId();
        $name = trim((string)($this->request->getPostData('name') ?? ''));
        $description = trim((string)($this->request->getPostData('description') ?? ''));
        $color = $this->normalizeColor((string)($this->request->getPostData('color') ?? ''));
        $icon  = $this->normalizeIcon((string)($this->request->getPostData('icon') ?? ''));
        if ($name === '') {
            $this->response->text('<div class="alert alert-danger">Name fehlt.</div>', 400);
            return;
        }
        $repo = new ProjectRepository($this->pdo);
        $id = $repo->create($userId, $name, $description, $color, $icon);
        $this->viewHtml((string)$id);
    }

    public function updateHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $name = trim((string)($this->request->getPostData('name') ?? ''));
        $description = trim((string)($this->request->getPostData('description') ?? ''));
        $color = $this->normalizeColor((string)($this->request->getPostData('color') ?? ''));
        $icon  = $this->normalizeIcon((string)($this->request->getPostData('icon') ?? ''));
        if ($name === '') {
            $this->response->text('<div class="alert alert-danger">Name fehlt.</div>', 400);
            return;
        }
        $repo = new ProjectRepository($this->pdo);
        $repo->update((int)$id, $userId, $name, $description, $color, $icon);
        $this->viewHtml($id);
    }

    public function deleteHtml(string $id): void
    {
        $userId = $this->currentUserId();
        (new ProjectRepository($this->pdo))->delete((int)$id, $userId);
        $this->indexHtml();
    }

    public function createListHtml(string $projectId): void
    {
        $userId = $this->currentUserId();
        $name = trim((string)($this->request->getPostData('name') ?? ''));
        if ($name === '') {
            $this->response->text('<div class="alert alert-danger">Listenname fehlt.</div>', 400);
            return;
        }
        $projectRepo = new ProjectRepository($this->pdo);
        $project = $projectRepo->getById((int)$projectId, $userId);
        if ($project === null) {
            $this->response->text('<div class="alert alert-warning">Projekt nicht gefunden.</div>', 404);
            return;
        }
        $listRepo = new TodoListRepository($this->pdo);
        $listRepo->createTodoList($name, $userId, $project->getId());
        $list = $listRepo->getTodoListByName($name, $userId);
        $stats = $listRepo->getListStats($list->getId());
        $this->response->text($this->renderListCardForProject($list, $stats));
    }

    public function createNoteHtml(string $projectId): void
    {
        $userId = $this->currentUserId();
        $projectRepo = new ProjectRepository($this->pdo);
        $project = $projectRepo->getById((int)$projectId, $userId);
        if ($project === null) {
            $this->response->text('<div class="alert alert-warning">Projekt nicht gefunden.</div>', 404);
            return;
        }
        $noteRepo = new NoteRepository($this->pdo);
        $id = $noteRepo->create($userId, 'Neue Notiz', '', $project->getId());

        // Hand off rendering to NoteController so we reuse the editor template
        $noteController = new NoteController($this->request, $this->pdo, $this->logger);
        $noteController->editHtml((string)$id);
    }

    // ---------------- Helpers ----------------

    private function renderProjectForm(?Project $existing): string
    {
        $name = $existing ? $existing->getName() : '';
        $desc = $existing ? $existing->getDescription() : '';
        $color = $existing ? $existing->getColor() : 'blue';
        $icon  = $existing ? $existing->getIcon() : 'folder';

        $action = $existing
            ? 'hx-put="/projects/' . $existing->getId() . '" hx-target="#main-content" hx-swap="innerHTML"'
            : 'hx-post="/projects" hx-target="#main-content" hx-swap="innerHTML"';

        $html = '<form class="workflow-form" ' . $action . '>';
        $html .= '<div class="row g-2">';
        $html .= '<div class="col-12 col-md-8"><input class="form-control" type="text" name="name" placeholder="Projektname" maxlength="100" value="' . htmlspecialchars($name) . '" required></div>';
        $html .= '<div class="col-12 col-md-4">';
        $html .= '<select class="form-select" name="color">';
        foreach (self::COLORS as $c) {
            $sel = $c === $color ? ' selected' : '';
            $html .= '<option value="' . $c . '"' . $sel . '>' . self::COLOR_LABELS[$c] . '</option>';
        }
        $html .= '</select></div>';
        $html .= '<div class="col-12"><textarea class="form-control" name="description" rows="2" placeholder="Beschreibung (optional)">' . htmlspecialchars($desc) . '</textarea></div>';
        $html .= '<div class="col-12"><label class="form-label small text-muted mb-1">Icon</label>';
        $html .= '<div class="icon-picker">';
        foreach (self::ICONS as $i) {
            $sel = $i === $icon ? ' selected' : '';
            $html .= '<label class="icon-option' . $sel . '"><input type="radio" name="icon" value="' . $i . '"' . ($i === $icon ? ' checked' : '') . ' hidden><i class="fa-solid fa-' . $i . '"></i></label>';
        }
        $html .= '</div></div>';
        $html .= '<div class="col-12 d-flex justify-content-end"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-' . ($existing ? 'floppy-disk' : 'plus') . ' me-2"></i>' . ($existing ? 'Speichern' : 'Anlegen') . '</button></div>';
        $html .= '</div>';
        $html .= '</form>';
        return $html;
    }

    private function renderProjectCard(array $row): string
    {
        $id = (int)$row['id'];
        $name = (string)$row['name'];
        $desc = (string)($row['description'] ?? '');
        $color = (string)($row['color'] ?? 'blue');
        $icon = (string)($row['icon'] ?? 'folder');
        $listCount = (int)($row['list_count'] ?? 0);
        $noteCount = (int)($row['note_count'] ?? 0);
        $openCount = (int)($row['open_count'] ?? 0);

        $html  = '<div class="list-card project-card project-color-' . htmlspecialchars($color) . '" hx-get="/projects/' . $id . '" hx-target="#main-content" hx-swap="innerHTML">';
        $html .= '<div class="list-card-head">';
        $html .= '<div class="d-flex align-items-center gap-2 min-w-0">';
        $html .= '<div class="project-icon"><i class="fa-solid fa-' . htmlspecialchars($icon) . '"></i></div>';
        $html .= '<h3 class="list-card-title mb-0">' . htmlspecialchars($name) . '</h3>';
        $html .= '</div>';
        $html .= '</div>';
        if ($desc !== '') {
            $html .= '<p class="note-card-excerpt mb-2">' . htmlspecialchars($desc) . '</p>';
        }
        $html .= '<div class="list-card-meta">';
        $html .= '<span><i class="fa-solid fa-list-check me-1"></i>' . $listCount . ' Listen</span>';
        $html .= '<span><i class="fa-regular fa-circle me-1"></i>' . $openCount . ' offen</span>';
        $html .= '<span><i class="fa-solid fa-note-sticky me-1"></i>' . $noteCount . ' Notizen</span>';
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }

    private function renderListCardForProject(\App\TodoList\TodoList $list, array $stats): string
    {
        $total = (int)$stats['total'];
        $done  = (int)$stats['done'];
        $open  = max(0, $total - $done);
        $pct   = $total > 0 ? (int)round(($done / $total) * 100) : 0;

        $html  = '<div class="list-card" hx-get="/todo/lists/' . $list->getId() . '" hx-target="#main-content" hx-swap="innerHTML">';
        $html .= '<div class="list-card-head"><h3 class="list-card-title">' . htmlspecialchars($list->getName()) . '</h3></div>';
        $html .= '<div class="list-card-meta">';
        if ($total === 0) {
            $html .= '<span class="list-card-empty">Noch keine Aufgaben</span>';
        } else {
            $html .= '<span><i class="fa-regular fa-circle me-1 text-info"></i>' . $open . ' offen</span>';
            $html .= '<span><i class="fa-solid fa-check me-1 text-success"></i>' . $done . ' erledigt</span>';
        }
        $html .= '</div>';
        $html .= '<div class="list-card-progress"><div class="list-card-progress-bar" style="width:' . $pct . '%"></div></div>';
        $html .= '<div class="list-card-progress-label">' . $pct . '%</div>';
        $html .= '</div>';
        return $html;
    }

    private function renderNoteCardForProject(\App\Note\Note $note): string
    {
        $title = trim($note->getTitle()) !== '' ? $note->getTitle() : 'Ohne Titel';
        $excerpt = mb_strlen($note->getContent()) > 140 ? mb_substr($note->getContent(), 0, 139) . '…' : $note->getContent();
        $html  = '<div class="note-card" hx-get="/notes/' . $note->getId() . '" hx-target="#main-content" hx-swap="innerHTML">';
        $html .= '<div class="note-card-head"><h3 class="note-card-title">' . htmlspecialchars($title) . '</h3></div>';
        $html .= '<p class="note-card-excerpt">' . htmlspecialchars($excerpt) . '</p>';
        $html .= '</div>';
        return $html;
    }
}
