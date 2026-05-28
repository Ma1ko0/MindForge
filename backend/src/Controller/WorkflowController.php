<?php

declare(strict_types=1);

namespace App;

use App\Note\NoteRepository;
use App\TodoItem\TodoItemRepository;
use App\TodoList\TodoListRepository;
use App\Workflow\WorkflowRepository;

class WorkflowController extends Controller
{
    private const COLORS = ['blue', 'purple', 'green', 'orange', 'red', 'gray'];
    private const COLOR_LABELS = [
        'blue' => 'Blau', 'purple' => 'Lila', 'green' => 'Grün',
        'orange' => 'Orange', 'red' => 'Rot', 'gray' => 'Grau',
    ];

    private function currentUserId(): int
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (empty($userId)) {
            $this->response->text('<p>Nicht eingeloggt.</p>', 401);
        }
        return (int)$userId;
    }

    private function validDate(string $date): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            try {
                $dt = new \DateTime($date);
                return $dt->format('Y-m-d');
            } catch (\Throwable) {}
        }
        return (new \DateTime())->format('Y-m-d');
    }

    private function normalizeTime(?string $t): string
    {
        $t = trim((string)$t);
        if (preg_match('/^\d{2}:\d{2}$/', $t)) return $t . ':00';
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $t)) return $t;
        return '00:00:00';
    }

    private function normalizeColor(?string $c): string
    {
        return in_array($c, self::COLORS, true) ? $c : 'blue';
    }

    public function indexHtml(): void
    {
        $today = (new \DateTime())->format('Y-m-d');
        $this->dayHtml($today);
    }

    public function dayHtml(string $date): void
    {
        $userId = $this->currentUserId();
        $date = $this->validDate($date);

        $repo = new WorkflowRepository($this->pdo);
        $blocks = $repo->getBlocksForDay($userId, $date);
        $stats = $repo->countBlocksForDay($userId, $date);
        $templates = $repo->listTemplates($userId);

        $itemRepo = new TodoItemRepository($this->pdo);
        $listRepo = new TodoListRepository($this->pdo);
        $noteRepo = new NoteRepository($this->pdo);
        $userLists = $listRepo->getTodoListsByUserId($userId);
        $openTodos = [];
        foreach ($userLists as $l) {
            foreach ($itemRepo->getTodoItemsByListId($l->getId()) as $it) {
                if (!$it->getIsChecked()) {
                    $openTodos[] = ['id' => $it->getId(), 'content' => $it->getContent(), 'listName' => $l->getName()];
                }
            }
        }
        $userNotes = $noteRepo->listByUserId($userId);

        $dt = new \DateTime($date);
        $todayDt = new \DateTime((new \DateTime())->format('Y-m-d'));
        $isToday = $dt->format('Y-m-d') === $todayDt->format('Y-m-d');
        $prev = (clone $dt)->modify('-1 day')->format('Y-m-d');
        $next = (clone $dt)->modify('+1 day')->format('Y-m-d');
        $heading = $this->germanDayLabel($dt, $isToday);

        $html  = '<div class="dashboard-page">';

        $html .= '<div class="dashboard-header mb-4 d-flex align-items-center justify-content-between gap-3 flex-wrap">';
        $html .= '<div>';
        $html .= '<h1 class="display-6 fw-bold mb-1">' . htmlspecialchars($heading) . '</h1>';
        $html .= '<p class="text-muted mb-0">' . $stats['done'] . ' von ' . $stats['total'] . ' Blöcken erledigt</p>';
        $html .= '</div>';
        $html .= '<div class="d-flex align-items-center gap-2">';
        $html .= '<button class="btn-icon" hx-get="/workflow/day/' . $prev . '" hx-target="#main-content" hx-swap="innerHTML" title="Tag zurück"><i class="fa-solid fa-chevron-left"></i></button>';
        $html .= '<button class="btn-icon" hx-get="/workflow" hx-target="#main-content" hx-swap="innerHTML" title="Heute"><i class="fa-solid fa-house"></i></button>';
        $html .= '<button class="btn-icon" hx-get="/workflow/day/' . $next . '" hx-target="#main-content" hx-swap="innerHTML" title="Tag vor"><i class="fa-solid fa-chevron-right"></i></button>';
        $html .= '<button class="btn btn-outline-light" hx-get="/workflow/templates" hx-target="#main-content" hx-swap="innerHTML"><i class="fa-solid fa-layer-group me-2"></i>Templates</button>';
        $html .= '</div>';
        $html .= '</div>';

        // Add block form
        $html .= '<div class="dashboard-card mb-4">';
        $html .= '<div class="dashboard-card-head"><h2 class="h5 mb-0"><i class="fa-solid fa-plus me-2 text-info"></i>Block hinzufügen</h2></div>';
        $html .= '<div class="dashboard-card-body">';
        $html .= $this->renderBlockForm($date, null, $openTodos, $userNotes);
        $html .= '</div></div>';

        // Apply template
        if (!empty($templates)) {
            $html .= '<div class="dashboard-card mb-4">';
            $html .= '<div class="dashboard-card-head"><h2 class="h5 mb-0"><i class="fa-solid fa-layer-group me-2 text-info"></i>Template anwenden</h2></div>';
            $html .= '<div class="dashboard-card-body">';
            $html .= '<form class="quick-add-form" hx-post="/workflow/templates/apply" hx-target="#main-content" hx-swap="innerHTML">';
            $html .= '<input type="hidden" name="date" value="' . $date . '">';
            $html .= '<div class="d-flex gap-2">';
            $html .= '<select class="form-select" name="templateId" required>';
            foreach ($templates as $t) {
                $html .= '<option value="' . (int)$t['id'] . '">' . htmlspecialchars((string)$t['name']) . ' (' . (int)$t['blockCount'] . ' Blöcke)</option>';
            }
            $html .= '</select>';
            $html .= '<button class="btn btn-primary" type="submit"><i class="fa-solid fa-wand-magic-sparkles me-2"></i>Anwenden</button>';
            $html .= '</div>';
            $html .= '</form>';
            $html .= '</div></div>';
        }

        // Blocks
        $html .= '<div id="blocks-list">';
        $html .= $this->renderBlocksList($blocks);
        $html .= '</div>';

        $html .= '</div>';
        $this->response->text($html);
    }

    private function renderBlocksList(array $blocks): string
    {
        if (empty($blocks)) {
            return '<div class="lists-empty"><div class="lists-empty-icon"><i class="fa-regular fa-calendar"></i></div><h3 class="mb-2">Noch keine Blöcke</h3><p class="text-muted mb-0">Plane deinen Tag — füge oben einen Block hinzu oder wende ein Template an.</p></div>';
        }
        $html = '<div class="workflow-timeline">';
        foreach ($blocks as $b) {
            $html .= $this->renderBlockCard($b);
        }
        $html .= '</div>';
        return $html;
    }

    private function renderBlockCard(array $b): string
    {
        $id = (int)$b['id'];
        $done = (bool)$b['isDone'];
        $color = (string)($b['color'] ?? 'blue');
        $start = substr((string)$b['startTime'], 0, 5);
        $end   = substr((string)$b['endTime'], 0, 5);
        $title = (string)$b['title'];
        $desc  = (string)($b['description'] ?? '');

        $cls = 'workflow-block workflow-color-' . htmlspecialchars($color) . ($done ? ' done' : '');
        $html  = '<div id="block-' . $id . '" class="' . $cls . '">';
        $html .= '<div class="workflow-block-time">' . htmlspecialchars($start) . '<br><span class="muted">' . htmlspecialchars($end) . '</span></div>';
        $html .= '<div class="workflow-block-body">';
        $html .= '<div class="workflow-block-head">';
        $html .= '<label class="d-flex align-items-start gap-2 mb-0 flex-grow-1" style="cursor:pointer;">';
        $html .= '<input class="form-check-input mt-1" type="checkbox"' . ($done ? ' checked' : '');
        $html .= ' hx-patch="/workflow/blocks/' . $id . '/toggle" hx-target="#block-' . $id . '" hx-swap="outerHTML">';
        $html .= '<span class="workflow-block-title">' . \App\Tags::linkifyHtml($title) . '</span>';
        $html .= '</label>';
        $html .= '<div class="workflow-block-actions">';
        $html .= '<button class="btn-icon btn-icon-sm" hx-get="/workflow/blocks/' . $id . '/edit" hx-target="#block-' . $id . '" hx-swap="outerHTML" title="Bearbeiten"><i class="fa-solid fa-pen"></i></button>';
        $html .= '<button class="btn-icon btn-icon-sm" hx-delete="/workflow/blocks/' . $id . '" hx-target="#block-' . $id . '" hx-swap="outerHTML" hx-confirm="Block wirklich löschen?" title="Löschen"><i class="fa-solid fa-trash-can"></i></button>';
        $html .= '</div>';
        $html .= '</div>';

        if ($desc !== '') {
            $html .= '<p class="workflow-block-desc">' . \App\Tags::linkifyHtml($desc) . '</p>';
        }

        $links = '';
        if (!empty($b['linkedTodoItemId']) && !empty($b['linkedTodoContent'])) {
            $listId = (int)$b['linkedTodoListId'];
            $checked = (int)($b['linkedTodoChecked'] ?? 0) === 1;
            $linkCls = 'workflow-link' . ($checked ? ' checked' : '');
            $links .= '<a class="' . $linkCls . '" hx-get="/todo/lists/' . $listId . '" hx-target="#main-content" hx-swap="innerHTML"><i class="fa-solid fa-list-check me-1"></i>' . htmlspecialchars((string)$b['linkedTodoContent']) . '</a>';
        }
        if (!empty($b['linkedNoteId']) && !empty($b['linkedNoteTitle'])) {
            $links .= '<a class="workflow-link" hx-get="/notes/' . (int)$b['linkedNoteId'] . '" hx-target="#main-content" hx-swap="innerHTML"><i class="fa-solid fa-note-sticky me-1"></i>' . htmlspecialchars((string)$b['linkedNoteTitle']) . '</a>';
        }
        if ($links !== '') {
            $html .= '<div class="workflow-block-links">' . $links . '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }

    private function renderBlockForm(string $date, ?array $existing, array $openTodos, array $userNotes, ?int $editId = null): string
    {
        $start = $existing['startTime'] ?? '09:00:00';
        $end   = $existing['endTime']   ?? '10:00:00';
        $title = $existing['title']     ?? '';
        $desc  = $existing['description'] ?? '';
        $color = $existing['color']     ?? 'blue';
        $tid   = $existing['linkedTodoItemId'] ?? null;
        $nid   = $existing['linkedNoteId'] ?? null;

        $action = $editId === null
            ? 'hx-post="/workflow/blocks" hx-target="#blocks-list" hx-swap="innerHTML"'
            : 'hx-put="/workflow/blocks/' . $editId . '" hx-target="#block-' . $editId . '" hx-swap="outerHTML"';

        $html = '<form class="workflow-form" ' . $action . '>';
        $html .= '<input type="hidden" name="date" value="' . htmlspecialchars($date) . '">';

        $html .= '<div class="row g-2">';
        $html .= '<div class="col-12"><input class="form-control" type="text" name="title" placeholder="Titel" maxlength="200" value="' . htmlspecialchars($title) . '" required></div>';
        $html .= '<div class="col-6 col-md-3">';
        $html .= '<div class="input-group due-input-group"><span class="input-group-text"><i class="fa-regular fa-clock"></i></span>';
        $html .= '<input class="form-control" type="time" name="startTime" value="' . substr($start, 0, 5) . '" required>';
        $html .= '</div></div>';
        $html .= '<div class="col-6 col-md-3">';
        $html .= '<div class="input-group due-input-group"><span class="input-group-text">bis</span>';
        $html .= '<input class="form-control" type="time" name="endTime" value="' . substr($end, 0, 5) . '" required>';
        $html .= '</div></div>';
        $html .= '<div class="col-12 col-md-3">';
        $html .= '<select class="form-select" name="color">';
        foreach (self::COLORS as $c) {
            $sel = $c === $color ? ' selected' : '';
            $html .= '<option value="' . $c . '"' . $sel . '>' . self::COLOR_LABELS[$c] . '</option>';
        }
        $html .= '</select></div>';
        $html .= '<div class="col-12 col-md-3"><button class="btn btn-primary w-100" type="submit">' . ($editId === null ? '<i class="fa-solid fa-plus me-2"></i>Hinzufügen' : '<i class="fa-solid fa-floppy-disk me-2"></i>Speichern') . '</button></div>';
        $html .= '<div class="col-12"><textarea class="form-control" name="description" rows="2" placeholder="Beschreibung (optional)">' . htmlspecialchars($desc) . '</textarea></div>';

        if (!empty($openTodos) || !empty($userNotes)) {
            $html .= '<div class="col-12 col-md-6">';
            $html .= '<select class="form-select" name="linkedTodoItemId">';
            $html .= '<option value="">— Keine Aufgabe verknüpfen —</option>';
            foreach ($openTodos as $t) {
                $sel = (int)$t['id'] === (int)$tid ? ' selected' : '';
                $label = mb_substr((string)$t['content'], 0, 60) . ' (' . (string)$t['listName'] . ')';
                $html .= '<option value="' . (int)$t['id'] . '"' . $sel . '>📋 ' . htmlspecialchars($label) . '</option>';
            }
            $html .= '</select></div>';

            $html .= '<div class="col-12 col-md-6">';
            $html .= '<select class="form-select" name="linkedNoteId">';
            $html .= '<option value="">— Keine Notiz verknüpfen —</option>';
            foreach ($userNotes as $n) {
                $sel = (int)$n->getId() === (int)$nid ? ' selected' : '';
                $title = trim($n->getTitle()) !== '' ? $n->getTitle() : 'Ohne Titel';
                $html .= '<option value="' . (int)$n->getId() . '"' . $sel . '>📝 ' . htmlspecialchars($title) . '</option>';
            }
            $html .= '</select></div>';
        }

        if ($editId !== null) {
            $html .= '<div class="col-12 d-flex justify-content-end"><button type="button" class="btn btn-link" hx-get="/workflow/blocks/' . $editId . '/cancel" hx-target="#block-' . $editId . '" hx-swap="outerHTML">Abbrechen</button></div>';
        }

        $html .= '</div>';
        $html .= '</form>';
        return $html;
    }

    public function createBlockHtml(): void
    {
        $userId = $this->currentUserId();
        $date = $this->validDate((string)($this->request->getPostData('date') ?? ''));
        $startTime = $this->normalizeTime((string)($this->request->getPostData('startTime') ?? ''));
        $endTime   = $this->normalizeTime((string)($this->request->getPostData('endTime') ?? ''));
        $title     = trim((string)($this->request->getPostData('title') ?? ''));
        $desc      = trim((string)($this->request->getPostData('description') ?? ''));
        $color     = $this->normalizeColor((string)($this->request->getPostData('color') ?? ''));
        $tid       = (int)($this->request->getPostData('linkedTodoItemId') ?? 0) ?: null;
        $nid       = (int)($this->request->getPostData('linkedNoteId') ?? 0) ?: null;

        if ($title === '') {
            $this->response->text('<div class="alert alert-danger">Titel fehlt.</div>', 400);
            return;
        }

        $repo = new WorkflowRepository($this->pdo);
        $repo->createBlock($userId, $date, $startTime, $endTime, $title, $desc, $color, $tid, $nid);
        $blocks = $repo->getBlocksForDay($userId, $date);
        $this->response->text($this->renderBlocksList($blocks));
    }

    public function editBlockHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $repo = new WorkflowRepository($this->pdo);
        $b = $repo->getBlockById((int)$id, $userId);
        if ($b === null) {
            $this->response->text('<div class="alert alert-warning">Block nicht gefunden.</div>', 404);
            return;
        }

        $listRepo = new TodoListRepository($this->pdo);
        $itemRepo = new TodoItemRepository($this->pdo);
        $noteRepo = new NoteRepository($this->pdo);
        $userLists = $listRepo->getTodoListsByUserId($userId);
        $openTodos = [];
        foreach ($userLists as $l) {
            foreach ($itemRepo->getTodoItemsByListId($l->getId()) as $it) {
                if (!$it->getIsChecked() || (int)$b['linkedTodoItemId'] === (int)$it->getId()) {
                    $openTodos[] = ['id' => $it->getId(), 'content' => $it->getContent(), 'listName' => $l->getName()];
                }
            }
        }
        $userNotes = $noteRepo->listByUserId($userId);

        $html  = '<div id="block-' . (int)$id . '" class="workflow-block-editor">';
        $html .= $this->renderBlockForm((string)$b['blockDate'], $b, $openTodos, $userNotes, (int)$id);
        $html .= '</div>';
        $this->response->text($html);
    }

    public function cancelEditBlockHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $repo = new WorkflowRepository($this->pdo);
        $b = $repo->getBlockById((int)$id, $userId);
        if ($b === null) {
            $this->response->text('');
            return;
        }
        // re-fetch with joins for full data
        $blocks = $repo->getBlocksForDay($userId, (string)$b['blockDate']);
        foreach ($blocks as $row) {
            if ((int)$row['id'] === (int)$id) {
                $this->response->text($this->renderBlockCard($row));
                return;
            }
        }
        $this->response->text('');
    }

    public function updateBlockHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $startTime = $this->normalizeTime((string)($this->request->getPostData('startTime') ?? ''));
        $endTime   = $this->normalizeTime((string)($this->request->getPostData('endTime') ?? ''));
        $title     = trim((string)($this->request->getPostData('title') ?? ''));
        $desc      = trim((string)($this->request->getPostData('description') ?? ''));
        $color     = $this->normalizeColor((string)($this->request->getPostData('color') ?? ''));
        $tid       = (int)($this->request->getPostData('linkedTodoItemId') ?? 0) ?: null;
        $nid       = (int)($this->request->getPostData('linkedNoteId') ?? 0) ?: null;
        if ($title === '') {
            $this->response->text('<div class="alert alert-danger">Titel fehlt.</div>', 400);
            return;
        }
        $repo = new WorkflowRepository($this->pdo);
        $repo->updateBlock((int)$id, $userId, $startTime, $endTime, $title, $desc, $color, $tid, $nid);

        $b = $repo->getBlockById((int)$id, $userId);
        if ($b === null) {
            $this->response->text('');
            return;
        }
        $blocks = $repo->getBlocksForDay($userId, (string)$b['blockDate']);
        foreach ($blocks as $row) {
            if ((int)$row['id'] === (int)$id) {
                $this->response->text($this->renderBlockCard($row));
                return;
            }
        }
        $this->response->text('');
    }

    public function toggleBlockHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $repo = new WorkflowRepository($this->pdo);
        $b = $repo->getBlockById((int)$id, $userId);
        if ($b === null) {
            $this->response->text('', 404);
            return;
        }
        $newState = (int)$b['isDone'] !== 1;
        $repo->setBlockDone((int)$id, $userId, $newState);

        $blocks = $repo->getBlocksForDay($userId, (string)$b['blockDate']);
        foreach ($blocks as $row) {
            if ((int)$row['id'] === (int)$id) {
                $this->response->text($this->renderBlockCard($row));
                return;
            }
        }
        $this->response->text('');
    }

    public function deleteBlockHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $ok = (new WorkflowRepository($this->pdo))->deleteBlock((int)$id, $userId);
        if (!$ok) {
            $this->response->text('<div class="alert alert-danger">Löschen fehlgeschlagen.</div>', 500);
            return;
        }
        $this->response->text('');
    }

    // ---------------- Templates UI ----------------

    public function templatesHtml(): void
    {
        $userId = $this->currentUserId();
        $repo = new WorkflowRepository($this->pdo);
        $templates = $repo->listTemplates($userId);

        $html  = '<div class="dashboard-page">';
        $html .= '<div class="dashboard-header mb-4 d-flex align-items-center justify-content-between gap-3 flex-wrap">';
        $html .= '<div>';
        $html .= '<button class="btn-icon mb-2" hx-get="/workflow" hx-target="#main-content" hx-swap="innerHTML" title="Zurück"><i class="fa-solid fa-arrow-left"></i></button>';
        $html .= '<h1 class="display-6 fw-bold mb-1">Workflow-Templates</h1>';
        $html .= '<p class="text-muted mb-0">Speichere wiederkehrende Tages-Routinen.</p>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div class="dashboard-card mb-4">';
        $html .= '<div class="dashboard-card-head"><h2 class="h5 mb-0"><i class="fa-solid fa-plus me-2 text-info"></i>Neues Template</h2></div>';
        $html .= '<div class="dashboard-card-body">';
        $html .= '<form class="quick-add-form" hx-post="/workflow/templates" hx-target="#main-content" hx-swap="innerHTML">';
        $html .= '<div class="row g-2">';
        $html .= '<div class="col-12 col-md-5"><input class="form-control" type="text" name="name" placeholder="Template-Name (z.B. „Standard-Arbeitstag")" required></div>';
        $html .= '<div class="col-12 col-md-5"><input class="form-control" type="text" name="description" placeholder="Beschreibung (optional)"></div>';
        $html .= '<div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-plus"></i></button></div>';
        $html .= '</div>';
        $html .= '</form>';
        $html .= '</div></div>';

        if (empty($templates)) {
            $html .= '<div class="lists-empty"><div class="lists-empty-icon"><i class="fa-solid fa-layer-group"></i></div><h3 class="mb-2">Noch keine Templates</h3><p class="text-muted mb-0">Lege oben dein erstes Template an — z.B. „Morgenroutine" oder „Deep Work-Tag".</p></div>';
        } else {
            $html .= '<div class="lists-grid">';
            foreach ($templates as $t) {
                $html .= '<div class="list-card" hx-get="/workflow/templates/' . (int)$t['id'] . '" hx-target="#main-content" hx-swap="innerHTML">';
                $html .= '<div class="list-card-head">';
                $html .= '<h3 class="list-card-title">' . htmlspecialchars((string)$t['name']) . '</h3>';
                $html .= '<button type="button" class="list-card-delete" onclick="event.stopPropagation()" hx-delete="/workflow/templates/' . (int)$t['id'] . '" hx-target="closest .list-card" hx-swap="outerHTML" hx-confirm="Template wirklich löschen?" title="Löschen"><i class="fa-solid fa-trash-can"></i></button>';
                $html .= '</div>';
                if (!empty($t['description'])) {
                    $html .= '<div class="text-muted small mb-2">' . htmlspecialchars((string)$t['description']) . '</div>';
                }
                $html .= '<div class="list-card-meta"><span><i class="fa-regular fa-clock me-1"></i>' . (int)$t['blockCount'] . ' Blöcke</span></div>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';
        $this->response->text($html);
    }

    public function createTemplateHtml(): void
    {
        $userId = $this->currentUserId();
        $name = trim((string)($this->request->getPostData('name') ?? ''));
        $desc = trim((string)($this->request->getPostData('description') ?? ''));
        if ($name === '') {
            $this->response->text('<div class="alert alert-danger">Name fehlt.</div>', 400);
            return;
        }
        $repo = new WorkflowRepository($this->pdo);
        $id = $repo->createTemplate($userId, $name, $desc);
        $this->templateHtml((string)$id);
    }

    public function templateHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $repo = new WorkflowRepository($this->pdo);
        $t = $repo->getTemplate((int)$id, $userId);
        if ($t === null) {
            $this->response->text('<div class="alert alert-warning">Template nicht gefunden.</div>', 404);
            return;
        }
        $blocks = $repo->getTemplateBlocks((int)$id);

        $html  = '<div class="dashboard-page">';

        $html .= '<div class="dashboard-header mb-4 d-flex align-items-center gap-3">';
        $html .= '<button class="btn-icon" hx-get="/workflow/templates" hx-target="#main-content" hx-swap="innerHTML" title="Zurück"><i class="fa-solid fa-arrow-left"></i></button>';
        $html .= '<div class="flex-grow-1">';
        $html .= '<h1 class="display-6 fw-bold mb-1">' . htmlspecialchars((string)$t['name']) . '</h1>';
        if (!empty($t['description'])) {
            $html .= '<p class="text-muted mb-0">' . htmlspecialchars((string)$t['description']) . '</p>';
        }
        $html .= '</div>';
        $html .= '</div>';

        // Add template-block form
        $html .= '<div class="dashboard-card mb-4">';
        $html .= '<div class="dashboard-card-head"><h2 class="h5 mb-0"><i class="fa-solid fa-plus me-2 text-info"></i>Block hinzufügen</h2></div>';
        $html .= '<div class="dashboard-card-body">';
        $html .= '<form class="workflow-form" hx-post="/workflow/templates/' . (int)$id . '/blocks" hx-target="#template-blocks" hx-swap="innerHTML">';
        $html .= '<div class="row g-2">';
        $html .= '<div class="col-12"><input class="form-control" type="text" name="title" placeholder="Titel" maxlength="200" required></div>';
        $html .= '<div class="col-6 col-md-3"><div class="input-group due-input-group"><span class="input-group-text"><i class="fa-regular fa-clock"></i></span><input class="form-control" type="time" name="startTime" value="09:00" required></div></div>';
        $html .= '<div class="col-6 col-md-3"><div class="input-group due-input-group"><span class="input-group-text">bis</span><input class="form-control" type="time" name="endTime" value="10:00" required></div></div>';
        $html .= '<div class="col-12 col-md-3"><select class="form-select" name="color">';
        foreach (self::COLORS as $c) {
            $html .= '<option value="' . $c . '">' . self::COLOR_LABELS[$c] . '</option>';
        }
        $html .= '</select></div>';
        $html .= '<div class="col-12 col-md-3"><button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-plus"></i></button></div>';
        $html .= '<div class="col-12"><textarea class="form-control" name="description" rows="2" placeholder="Beschreibung (optional)"></textarea></div>';
        $html .= '</div>';
        $html .= '</form>';
        $html .= '</div></div>';

        $html .= '<div class="dashboard-card">';
        $html .= '<div class="dashboard-card-head"><h2 class="h5 mb-0"><i class="fa-solid fa-list me-2 text-info"></i>Blöcke</h2></div>';
        $html .= '<div class="dashboard-card-body">';
        $html .= '<div id="template-blocks">';
        $html .= $this->renderTemplateBlocks($blocks, (int)$id);
        $html .= '</div>';
        $html .= '</div></div>';

        $html .= '</div>';
        $this->response->text($html);
    }

    private function renderTemplateBlocks(array $blocks, int $templateId): string
    {
        if (empty($blocks)) {
            return '<div class="empty-hint text-muted text-center py-4">Noch keine Blöcke im Template.</div>';
        }
        $html = '<div class="workflow-timeline">';
        foreach ($blocks as $b) {
            $color = (string)($b['color'] ?? 'blue');
            $start = substr((string)$b['startTime'], 0, 5);
            $end   = substr((string)$b['endTime'], 0, 5);
            $html .= '<div class="workflow-block workflow-color-' . htmlspecialchars($color) . '">';
            $html .= '<div class="workflow-block-time">' . htmlspecialchars($start) . '<br><span class="muted">' . htmlspecialchars($end) . '</span></div>';
            $html .= '<div class="workflow-block-body">';
            $html .= '<div class="workflow-block-head">';
            $html .= '<span class="workflow-block-title">' . htmlspecialchars((string)$b['title']) . '</span>';
            $html .= '<div class="workflow-block-actions">';
            $html .= '<button class="btn-icon btn-icon-sm" hx-delete="/workflow/templates/' . $templateId . '/blocks/' . (int)$b['id'] . '" hx-target="#template-blocks" hx-swap="innerHTML" hx-confirm="Block entfernen?" title="Entfernen"><i class="fa-solid fa-trash-can"></i></button>';
            $html .= '</div>';
            $html .= '</div>';
            if (!empty($b['description'])) {
                $html .= '<p class="workflow-block-desc">' . htmlspecialchars((string)$b['description']) . '</p>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    public function addTemplateBlockHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $repo = new WorkflowRepository($this->pdo);
        $t = $repo->getTemplate((int)$id, $userId);
        if ($t === null) {
            $this->response->text('<div class="alert alert-warning">Template nicht gefunden.</div>', 404);
            return;
        }
        $title = trim((string)($this->request->getPostData('title') ?? ''));
        $start = $this->normalizeTime((string)($this->request->getPostData('startTime') ?? ''));
        $end   = $this->normalizeTime((string)($this->request->getPostData('endTime') ?? ''));
        $desc  = trim((string)($this->request->getPostData('description') ?? ''));
        $color = $this->normalizeColor((string)($this->request->getPostData('color') ?? ''));
        if ($title === '') {
            $this->response->text('<div class="alert alert-danger">Titel fehlt.</div>', 400);
            return;
        }
        $repo->addTemplateBlock((int)$id, $start, $end, $title, $desc, $color);
        $this->response->text($this->renderTemplateBlocks($repo->getTemplateBlocks((int)$id), (int)$id));
    }

    public function deleteTemplateBlockHtml(string $templateId, string $blockId): void
    {
        $userId = $this->currentUserId();
        $repo = new WorkflowRepository($this->pdo);
        $t = $repo->getTemplate((int)$templateId, $userId);
        if ($t === null) {
            $this->response->text('', 404);
            return;
        }
        $repo->deleteTemplateBlock((int)$blockId, (int)$templateId);
        $this->response->text($this->renderTemplateBlocks($repo->getTemplateBlocks((int)$templateId), (int)$templateId));
    }

    public function deleteTemplateHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $ok = (new WorkflowRepository($this->pdo))->deleteTemplate((int)$id, $userId);
        $this->response->text($ok ? '' : '<div class="alert alert-danger">Löschen fehlgeschlagen.</div>', $ok ? 200 : 500);
    }

    public function applyTemplateHtml(): void
    {
        $userId = $this->currentUserId();
        $tid  = (int)($this->request->getPostData('templateId') ?? 0);
        $date = $this->validDate((string)($this->request->getPostData('date') ?? ''));
        if ($tid <= 0) {
            $this->response->text('<div class="alert alert-danger">Template fehlt.</div>', 400);
            return;
        }
        $repo = new WorkflowRepository($this->pdo);
        if ($repo->getTemplate($tid, $userId) === null) {
            $this->response->text('<div class="alert alert-warning">Template nicht gefunden.</div>', 404);
            return;
        }
        $repo->applyTemplateToDate($tid, $userId, $date);
        $this->dayHtml($date);
    }

    private function germanDayLabel(\DateTime $dt, bool $isToday): string
    {
        $days = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
        $months = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
        $label = $days[(int)$dt->format('w')] . ', ' . $dt->format('j') . '. ' . $months[(int)$dt->format('n') - 1];
        if ($isToday) {
            return 'Heute · ' . $label;
        }
        return $label . ' ' . $dt->format('Y');
    }
}
