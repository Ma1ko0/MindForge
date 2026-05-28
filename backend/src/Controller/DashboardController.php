<?php

declare(strict_types=1);

namespace App;

use App\TodoItem\TodoItemRepository;
use App\TodoList\TodoListRepository;
use App\User\UserRepository;
use App\User\UserNotFoundException;
use App\Workflow\WorkflowRepository;

class DashboardController extends Controller
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
        $username = $_SESSION['username'] ?? 'user';

        $userRepo = new UserRepository($this->pdo);
        try {
            $user = $userRepo->getUserById($userId);
            $username = $user->getUsername();
        } catch (UserNotFoundException) {
            // fall back to session username
        }

        $listRepo = new TodoListRepository($this->pdo);
        $lists = $listRepo->getTodoListsByUserId($userId);

        $html  = '<div class="dashboard-page">';
        $html .= '<div class="dashboard-header mb-4">';
        $html .= '<h1 class="display-6 fw-bold mb-1">Hallo, ' . htmlspecialchars($username) . ' 👋</h1>';
        $html .= '<p class="text-muted mb-0">' . htmlspecialchars($this->todayLabel()) . '</p>';
        $html .= '</div>';

        $html .= '<div id="dashboard-stats" class="dashboard-stats mb-4" hx-get="/dashboard/stats" hx-trigger="load, dashboard-changed from:body" hx-swap="innerHTML"></div>';

        $html .= '<div class="dashboard-card mb-4">';
        $html .= '<div class="dashboard-card-head d-flex align-items-center justify-content-between gap-2 flex-wrap">';
        $html .= '<h2 class="h5 mb-0"><i class="fa-solid fa-sun me-2 text-info"></i>Heute</h2>';
        $html .= '<button class="btn btn-sm btn-outline-light" hx-get="/workflow" hx-target="#main-content" hx-swap="innerHTML"><i class="fa-solid fa-arrow-right me-1"></i>Tag planen</button>';
        $html .= '</div>';
        $html .= '<div class="dashboard-card-body">';
        $html .= '<div id="today-blocks" hx-get="/dashboard/today" hx-trigger="load, dashboard-changed from:body" hx-swap="innerHTML"></div>';
        $html .= '</div></div>';

        $html .= '<div class="dashboard-card mb-4">';
        $html .= '<div class="dashboard-card-head d-flex align-items-center justify-content-between gap-2 flex-wrap">';
        $html .= '<h2 class="h5 mb-0"><i class="fa-solid fa-repeat me-2 text-info"></i>Habits</h2>';
        $html .= '<button class="btn btn-sm btn-outline-light" hx-get="/habits" hx-target="#main-content" hx-swap="innerHTML"><i class="fa-solid fa-arrow-right me-1"></i>Verwalten</button>';
        $html .= '</div>';
        $html .= '<div class="dashboard-card-body">';
        $html .= '<div id="habits-widget" hx-get="/habits/widget" hx-trigger="load, habits-changed from:body" hx-swap="innerHTML"></div>';
        $html .= '</div></div>';

        $html .= '<div class="dashboard-grid">';
        $html .= '<div class="dashboard-card">';
        $html .= '<div class="dashboard-card-head"><h2 class="h5 mb-0"><i class="fa-solid fa-bolt me-2 text-info"></i>Schnell hinzufügen</h2></div>';
        $html .= '<div class="dashboard-card-body">';
        $html .= $this->renderQuickAddForm($lists);
        $html .= '</div></div>';

        $html .= '<div class="dashboard-card">';
        $html .= '<div class="dashboard-card-head"><h2 class="h5 mb-0"><i class="fa-solid fa-clock-rotate-left me-2 text-info"></i>Zuletzt hinzugefügt</h2></div>';
        $html .= '<div class="dashboard-card-body">';
        $html .= '<div id="dashboard-recent" hx-get="/dashboard/recent" hx-trigger="load, dashboard-changed from:body" hx-swap="innerHTML"></div>';
        $html .= '</div></div>';
        $html .= '</div>';

        $html .= '</div>';
        $this->response->text($html);
    }

    public function statsHtml(): void
    {
        $userId = $this->currentUserId();
        $itemRepo = new TodoItemRepository($this->pdo);
        $listRepo = new TodoListRepository($this->pdo);

        $open = $itemRepo->countOpenByUserId($userId);
        $done = $itemRepo->countCompletedTodayByUserId($userId);
        $lists = $listRepo->countByUserId($userId);

        $html  = $this->renderStatCard('fa-list-check', 'Offene Aufgaben', (string)$open, 'info');
        $html .= $this->renderStatCard('fa-check', 'Heute erledigt', (string)$done, 'success');
        $html .= $this->renderStatCard('fa-folder', 'Listen', (string)$lists, 'primary');
        $this->response->text($html);
    }

    public function recentHtml(): void
    {
        $userId = $this->currentUserId();
        $itemRepo = new TodoItemRepository($this->pdo);
        $items = $itemRepo->getRecentByUserId($userId, 5);

        if (empty($items)) {
            $this->response->text('<div class="empty-hint text-muted text-center py-4">Noch nichts hinzugefügt.</div>');
            return;
        }

        $html = '<ul class="recent-list list-unstyled mb-0">';
        foreach ($items as $row) {
            $checked = (bool)$row['isChecked'];
            $cls = $checked ? ' checked' : '';
            $html .= '<li class="recent-item' . $cls . '">';
            $html .= '<div class="recent-item-content">';
            $html .= '<span class="recent-item-text">' . htmlspecialchars((string)$row['content']) . '</span>';
            $html .= '<span class="recent-item-meta">in <a class="recent-item-link" hx-get="/todo/lists/' . (int)$row['listId'] . '" hx-target="#main-content" hx-swap="innerHTML">' . htmlspecialchars((string)$row['listName']) . '</a> · ' . htmlspecialchars($this->humanTimeAgo((string)$row['created_at'])) . '</span>';
            $html .= '</div>';
            if ($checked) {
                $html .= '<i class="fa-solid fa-circle-check text-success"></i>';
            } else {
                $html .= '<i class="fa-regular fa-circle text-muted"></i>';
            }
            $html .= '</li>';
        }
        $html .= '</ul>';
        $this->response->text($html);
    }

    public function quickAddHtml(): void
    {
        $userId = $this->currentUserId();
        $content = trim((string)($this->request->getPostData('content') ?? ''));
        $listId  = (int)($this->request->getPostData('listId') ?? 0);

        if ($content === '') {
            $this->response->text('<div class="alert alert-danger py-2 mb-0">Bitte einen Inhalt angeben.</div>', 400);
            return;
        }
        if ($listId <= 0) {
            $this->response->text('<div class="alert alert-danger py-2 mb-0">Bitte eine Liste auswählen.</div>', 400);
            return;
        }

        $listRepo = new TodoListRepository($this->pdo);
        if (!$listRepo->userOwnsList($listId, $userId)) {
            $this->response->text('<div class="alert alert-danger py-2 mb-0">Liste nicht gefunden.</div>', 404);
            return;
        }

        $dueDate = TodoItemController::normalizeDueDateInput((string)($this->request->getPostData('due_date') ?? ''));
        try {
            $itemRepo = new TodoItemRepository($this->pdo);
            $itemRepo->createTodoItem($content, false, $listId, $dueDate);
        } catch (\Throwable $ex) {
            $this->logger->logging('Quick-add failed: ' . $ex->getMessage(), ERROR);
            $this->response->text('<div class="alert alert-danger py-2 mb-0">Fehler beim Anlegen.</div>', 500);
            return;
        }

        header('HX-Trigger: dashboard-changed');
        $lists = $listRepo->getTodoListsByUserId($userId);
        $this->response->text($this->renderQuickAddForm($lists, '✓ hinzugefügt'));
    }

    public function todayHtml(): void
    {
        $userId = $this->currentUserId();
        $today  = (new \DateTime())->format('Y-m-d');
        $repo   = new WorkflowRepository($this->pdo);
        $blocks = $repo->getBlocksForDay($userId, $today);
        $this->response->text($this->renderTodayBlocks($blocks));
    }

    public function toggleTodayHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $repo   = new WorkflowRepository($this->pdo);
        $b = $repo->getBlockById((int)$id, $userId);
        if ($b !== null) {
            $repo->setBlockDone((int)$id, $userId, (int)$b['isDone'] !== 1);
        }
        $today  = (new \DateTime())->format('Y-m-d');
        $blocks = $repo->getBlocksForDay($userId, $today);
        $this->response->text($this->renderTodayBlocks($blocks));
    }

    private function renderTodayBlocks(array $blocks): string
    {
        if (empty($blocks)) {
            return '<div class="empty-hint text-muted text-center py-3">Heute noch nichts geplant. <a class="auth-link" hx-get="/workflow" hx-target="#main-content" hx-swap="innerHTML">Tag planen</a></div>';
        }
        $total = count($blocks);
        $done = 0;
        foreach ($blocks as $b) {
            if ((int)$b['isDone'] === 1) $done++;
        }
        $pct = $total > 0 ? (int)round(($done / $total) * 100) : 0;

        $html  = '<div class="today-meta d-flex align-items-center gap-3 mb-3">';
        $html .= '<span class="text-muted small">' . $done . ' von ' . $total . ' erledigt · ' . $pct . '%</span>';
        $html .= '<div class="flex-grow-1 list-card-progress" style="margin:0">';
        $html .= '<div class="list-card-progress-bar" style="width:' . $pct . '%"></div>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div class="workflow-timeline">';
        foreach ($blocks as $b) {
            $html .= $this->renderTodayBlock($b);
        }
        $html .= '</div>';
        return $html;
    }

    private function renderTodayBlock(array $b): string
    {
        $id = (int)$b['id'];
        $done = (int)$b['isDone'] === 1;
        $color = (string)($b['color'] ?? 'blue');
        $start = substr((string)$b['startTime'], 0, 5);
        $end   = substr((string)$b['endTime'], 0, 5);
        $title = (string)$b['title'];
        $desc  = (string)($b['description'] ?? '');

        $cls = 'workflow-block workflow-color-' . htmlspecialchars($color) . ($done ? ' done' : '');
        $html  = '<div class="' . $cls . '">';
        $html .= '<div class="workflow-block-time">' . htmlspecialchars($start) . '<br><span class="muted">' . htmlspecialchars($end) . '</span></div>';
        $html .= '<div class="workflow-block-body">';
        $html .= '<label class="d-flex align-items-start gap-2 mb-0" style="cursor:pointer;">';
        $html .= '<input class="form-check-input mt-1" type="checkbox"' . ($done ? ' checked' : '');
        $html .= ' hx-patch="/dashboard/today/' . $id . '/toggle" hx-target="#today-blocks" hx-swap="innerHTML">';
        $html .= '<span class="workflow-block-title">' . \App\Tags::linkifyHtml($title) . '</span>';
        $html .= '</label>';

        if ($desc !== '') {
            $html .= '<p class="workflow-block-desc small mb-0">' . \App\Tags::linkifyHtml($desc) . '</p>';
        }

        $links = '';
        if (!empty($b['linkedTodoItemId']) && !empty($b['linkedTodoContent'])) {
            $listId  = (int)$b['linkedTodoListId'];
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

    private function renderQuickAddForm(array $lists, ?string $flash = null): string
    {
        $html = '<form id="quick-add-form" class="quick-add-form" hx-post="/dashboard/quick-add" hx-target="#quick-add-form" hx-swap="outerHTML">';
        $html .= '<input class="form-control mb-2" type="text" name="content" placeholder="Neue Aufgabe …" required autofocus>';
        if (empty($lists)) {
            $html .= '<div class="text-muted small mb-2">Erstelle erst eine Liste unter „ToDo-Listen".</div>';
            $html .= '<button class="btn btn-primary w-100" type="submit" disabled>Hinzufügen</button>';
        } else {
            $html .= '<div class="input-group due-input-group mb-2">';
            $html .= '<span class="input-group-text"><i class="fa-regular fa-calendar me-1"></i>Fällig</span>';
            $html .= '<input class="form-control" type="datetime-local" name="due_date" title="Fällig am (optional)">';
            $html .= '</div>';
            $html .= '<div class="d-flex gap-2">';
            $html .= '<select class="form-select" name="listId" required>';
            foreach ($lists as $list) {
                $html .= '<option value="' . $list->getId() . '">' . htmlspecialchars($list->getName()) . '</option>';
            }
            $html .= '</select>';
            $html .= '<button class="btn btn-primary" type="submit"><i class="fa-solid fa-plus"></i></button>';
            $html .= '</div>';
        }
        if ($flash !== null) {
            $html .= '<div class="text-success small mt-2">' . htmlspecialchars($flash) . '</div>';
        }
        $html .= '</form>';
        return $html;
    }

    private function renderStatCard(string $icon, string $label, string $value, string $color): string
    {
        $html  = '<div class="stat-card stat-' . htmlspecialchars($color) . '">';
        $html .= '<div class="stat-icon"><i class="fa-solid ' . htmlspecialchars($icon) . '"></i></div>';
        $html .= '<div class="stat-body">';
        $html .= '<div class="stat-value">' . htmlspecialchars($value) . '</div>';
        $html .= '<div class="stat-label">' . htmlspecialchars($label) . '</div>';
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }

    private function todayLabel(): string
    {
        $days = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
        $months = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
        $now = new \DateTime();
        return $days[(int)$now->format('w')] . ', ' . $now->format('j') . '. ' . $months[(int)$now->format('n') - 1] . ' ' . $now->format('Y');
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
