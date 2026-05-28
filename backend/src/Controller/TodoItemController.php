<?php

declare(strict_types=1);

namespace App;

use App\TodoItem\TodoItemRepository;
use App\TodoList\TodoListRepository;

class TodoItemController extends Controller
{
	private function currentUserId(): int
	{
		$userId = $_SESSION['user_id'] ?? null;
		if (empty($userId)) {
			$this->response->text('<p>Nicht eingeloggt.</p>', 401);
		}
		return (int)$userId;
	}

	private function assertListBelongsToUser(int $listId, int $userId): void
	{
		$listRepo = new TodoListRepository($this->pdo);
		if (!$listRepo->userOwnsList($listId, $userId)) {
			$this->response->text('<p>Liste nicht gefunden.</p>', 404);
		}
	}

	public function pageHtml() {
		$userId = $this->currentUserId();
		$projects = (new \App\Project\ProjectRepository($this->pdo))->listByUserId($userId);

		$html  = '<div class="dashboard-page">';
		$html .= '<div class="dashboard-header mb-4">';
		$html .= '<h1 class="display-6 fw-bold mb-1">ToDo-Listen</h1>';
		$html .= '<p class="text-muted mb-0">Erstelle und verwalte deine Listen.</p>';
		$html .= '</div>';

		$html .= '<div class="dashboard-card mb-4">';
		$html .= '<div class="dashboard-card-head"><h2 class="h5 mb-0"><i class="fa-solid fa-plus me-2 text-info"></i>Neue Liste</h2></div>';
		$html .= '<div class="dashboard-card-body">';
		$html .= '<form class="quick-add-form" hx-post="/todo/lists" hx-target="#lists-container" hx-swap="beforeend" hx-on::after-request="if(event.detail.successful) this.reset()">';
		$html .= '<div class="row g-2">';
		$html .= '<div class="col-12 col-md"><input class="form-control" type="text" name="name" placeholder="Listenname" required></div>';
		if (!empty($projects)) {
			$html .= '<div class="col-12 col-md-4"><select class="form-select" name="projectId">';
			$html .= '<option value="">— Kein Projekt —</option>';
			foreach ($projects as $p) {
				$html .= '<option value="' . $p->getId() . '">' . htmlspecialchars($p->getName()) . '</option>';
			}
			$html .= '</select></div>';
		}
		$html .= '<div class="col-auto"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-plus"></i></button></div>';
		$html .= '</div>';
		$html .= '</form>';
		$html .= '</div></div>';

		$html .= '<div id="lists-container" class="lists-grid" hx-get="/todo/lists" hx-trigger="load" hx-target="#lists-container"></div>';

		$html .= '</div>';
		$this->response->text($html);
	}

	public function getTodoListsHtml() {
		$userId = $this->currentUserId();
		$lists = [];
		try {
			$todoListRepository = new TodoListRepository($this->pdo);
			$lists = $todoListRepository->getTodoListsWithStatsByUserId($userId);
		} catch (\Exception $ex) {
			$this->response->text('<p>Error loading lists</p>');
			return;
		}
		if (empty($lists)) {
			$this->response->text($this->renderListsEmptyState());
			return;
		}
		$html = '';
		foreach ($lists as $row) {
			$html .= $this->renderListCardFromRow($row);
		}
		$this->response->text($html);
	}

	private function renderListCardFromRow(array $row): string {
		$id    = (int)$row['id'];
		$name  = (string)$row['name'];
		$total = (int)($row['total'] ?? 0);
		$done  = (int)($row['done'] ?? 0);
		$open  = max(0, $total - $done);
		$pct   = $total > 0 ? (int)round(($done / $total) * 100) : 0;
		$projectId   = isset($row['projectId']) ? (int)$row['projectId'] : 0;
		$projectName = (string)($row['project_name'] ?? '');
		$projectColor= (string)($row['project_color'] ?? 'blue');
		$projectIcon = (string)($row['project_icon'] ?? 'folder');

		$html  = '<div class="list-card" hx-get="/todo/lists/' . $id . '" hx-target="#main-content" hx-swap="innerHTML">';
		$html .= '<div class="list-card-head">';
		$html .= '<h3 class="list-card-title">' . htmlspecialchars($name) . '</h3>';
		$html .= '<button type="button" class="list-card-delete" onclick="event.stopPropagation()" hx-delete="/todo/lists/' . $id . '" hx-target="closest .list-card" hx-swap="outerHTML" title="Liste löschen"><i class="fa-solid fa-trash-can"></i></button>';
		$html .= '</div>';

		if ($projectId > 0 && $projectName !== '') {
			$html .= '<a class="project-chip project-color-' . htmlspecialchars($projectColor) . '" onclick="event.stopPropagation()" hx-get="/projects/' . $projectId . '" hx-target="#main-content" hx-swap="innerHTML"><i class="fa-solid fa-' . htmlspecialchars($projectIcon) . ' me-1"></i>' . htmlspecialchars($projectName) . '</a>';
		}

		$html .= '<div class="list-card-meta">';
		if ($total === 0) {
			$html .= '<span class="list-card-empty">Noch keine Aufgaben</span>';
		} else {
			$html .= '<span><i class="fa-regular fa-circle me-1 text-info"></i>' . $open . ' offen</span>';
			$html .= '<span><i class="fa-solid fa-check me-1 text-success"></i>' . $done . ' erledigt</span>';
		}
		$html .= '</div>';

		$html .= '<div class="list-card-progress">';
		$html .= '<div class="list-card-progress-bar" style="width:' . $pct . '%"></div>';
		$html .= '</div>';
		$html .= '<div class="list-card-progress-label">' . $pct . '%</div>';

		$html .= '</div>';
		return $html;
	}

	private function renderListCard(\App\TodoList\TodoList $list): string {
		$stats = (new TodoListRepository($this->pdo))->getListStats($list->getId());
		$row = [
			'id' => $list->getId(),
			'name' => $list->getName(),
			'projectId' => $list->getProjectId(),
			'total' => $stats['total'],
			'done' => $stats['done'],
		];
		if ($list->getProjectId() !== null) {
			$project = (new \App\Project\ProjectRepository($this->pdo))->getById($list->getProjectId(), $list->getUserId());
			if ($project !== null) {
				$row['project_name']  = $project->getName();
				$row['project_color'] = $project->getColor();
				$row['project_icon']  = $project->getIcon();
			}
		}
		return $this->renderListCardFromRow($row);
	}

	private function renderListsEmptyState(): string {
		$html  = '<div id="lists-empty" class="lists-empty">';
		$html .= '<div class="lists-empty-icon"><i class="fa-regular fa-rectangle-list"></i></div>';
		$html .= '<h3 class="mb-2">Noch keine Listen</h3>';
		$html .= '<p class="text-muted mb-0">Erstell oben deine erste Liste, um loszulegen.</p>';
		$html .= '</div>';
		return $html;
	}

	public function createTodoListHtml() {
		$userId = $this->currentUserId();
		$name = trim((string)($this->request->getPostData('name') ?? ''));
		$projectId = (int)($this->request->getPostData('projectId') ?? 0) ?: null;
		if ($name === '') {
			$this->response->text('<p>Listenname darf nicht leer sein.</p>', 400);
		}
		try {
			$todoListRepository = new TodoListRepository($this->pdo);
			$success = $todoListRepository->createTodoList($name, $userId, $projectId);
			if (!$success) {
				$this->response->text('<p>Error creating list Unknown Error</p>');
			}
			$list = $todoListRepository->getTodoListByName($name, $userId);

			$html  = '<div id="lists-empty" hx-swap-oob="delete"></div>';
			$html .= $this->renderListCard($list);
			$this->response->text($html);
		} catch (\Exception $ex) {
			$this->response->text('<p>Error creating list' .$ex->getMessage() . ' </p>');
		}
	}

	public function deleteTodoListHtml($listId) {
		$userId = $this->currentUserId();
		try {
			$todoListRepository = new TodoListRepository($this->pdo);
			$deleted = $todoListRepository->deleteTodoList((int)$listId, $userId);
			if (!$deleted) {
				$this->response->text('<p>Error deleting list</p>', 400);
				return;
			}
			$this->response->text('');
		} catch (\Exception $ex) {
			$this->response->text('<p>Error deleting list</p>' . $ex->getMessage(), 500);
		}
	}

	public function getTodoListHtml($listId) {
		$userId = $this->currentUserId();
		$this->assertListBelongsToUser((int)$listId, $userId);
		$items = [];
		$listName = '';
		try {
			$todoItemRepository = new TodoItemRepository($this->pdo);
			$items = $todoItemRepository->getTodoItemsByListId((int)$listId);
			$listRepo = new TodoListRepository($this->pdo);
			$stats = $listRepo->getListStats((int)$listId);
			// fetch list name via the existing query
			$all = $listRepo->getTodoListsByUserId($userId);
			foreach ($all as $l) {
				if ($l->getId() === (int)$listId) { $listName = $l->getName(); break; }
			}
		} catch (\Exception $ex) {
			$this->response->text('<p>Error loading items</p>' . $ex->getMessage());
			return;
		}

		$total = $stats['total'];
		$done  = $stats['done'];
		$pct   = $total > 0 ? (int)round(($done / $total) * 100) : 0;

		$html  = '<div class="dashboard-page">';

		$html .= '<div class="dashboard-header mb-4 d-flex align-items-center gap-3">';
		$html .= '<button class="btn btn-icon" hx-get="/todo" hx-target="#main-content" hx-swap="innerHTML" title="Zurück"><i class="fa-solid fa-arrow-left"></i></button>';
		$html .= '<div class="flex-grow-1">';
		$html .= '<h1 class="display-6 fw-bold mb-1">' . htmlspecialchars($listName) . '</h1>';
		$html .= '<p class="text-muted mb-0">' . $done . ' von ' . $total . ' erledigt · ' . $pct . '%</p>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '<div class="dashboard-card mb-4">';
		$html .= '<div class="dashboard-card-head"><h2 class="h5 mb-0"><i class="fa-solid fa-plus me-2 text-info"></i>Aufgabe hinzufügen</h2></div>';
		$html .= '<div class="dashboard-card-body">';
		$html .= '<form class="quick-add-form" hx-post="/todo/lists/' . $listId . '/items" hx-target="#items-list" hx-swap="beforeend" hx-on::after-request="if(event.detail.successful) this.reset()">';
		$html .= '<div class="d-flex gap-2 flex-wrap flex-md-nowrap">';
		$html .= '<input class="form-control flex-grow-1" type="text" name="content" placeholder="Was ist zu tun?" required>';
		$html .= '<div class="input-group due-input-group" style="max-width:260px">';
		$html .= '<span class="input-group-text"><i class="fa-regular fa-calendar me-1"></i>Fällig</span>';
		$html .= '<input class="form-control" type="datetime-local" name="due_date" title="Fällig am (optional)">';
		$html .= '</div>';
		$html .= '<button class="btn btn-primary" type="submit"><i class="fa-solid fa-plus"></i></button>';
		$html .= '</div>';
		$html .= '<div class="form-text">Fälligkeit ist optional.</div>';
		$html .= '</form>';
		$html .= '</div></div>';

		$html .= '<div class="dashboard-card">';
		$html .= '<div class="dashboard-card-head"><h2 class="h5 mb-0"><i class="fa-solid fa-list-check me-2 text-info"></i>Aufgaben</h2></div>';
		$html .= '<div class="dashboard-card-body">';
		$html .= '<div id="items-list" class="d-grid gap-2">';
		if (empty($items)) {
			$html .= '<div class="empty-hint text-muted text-center py-4">Noch keine Aufgaben. Füge oben deine erste hinzu.</div>';
		}
		foreach ($items as $item) {
			if ($item->getId() === null) {
				continue;
			}
			$html .= $this->renderItem($item, (int)$listId);
		}
		$html .= '</div>';
		$html .= '</div></div>';

		$html .= '</div>';
		$this->response->text($html);
	}

	public function createTodoItemHtml($listId) {
		$userId = $this->currentUserId();
		$this->assertListBelongsToUser((int)$listId, $userId);
		$content = trim((string)($this->request->getPostData('content') ?? ''));
		if ($content === '') {
			$this->response->text('<p>Inhalt darf nicht leer sein.</p>', 400);
		}
		$dueDate = self::normalizeDueDateInput((string)($this->request->getPostData('due_date') ?? ''));
		try {
			$todoItemRepository = new TodoItemRepository($this->pdo);
			$id = $todoItemRepository->createTodoItem($content, false, (int)$listId, $dueDate);
			$item = new \App\TodoItem\TodoItem($id, $content, false, (int)$listId, $dueDate);
			$this->response->text($this->renderItem($item, (int)$listId));
		} catch (\Exception $ex) {
			$this->response->text('<p>Error adding item</p>' . $ex->getMessage());
		}
	}

	public function toggleTodoItemHtml($listId, $itemId) {
		$userId = $this->currentUserId();
		$this->assertListBelongsToUser((int)$listId, $userId);
		try {
			$todoItemRepository = new TodoItemRepository($this->pdo);
			$item = $todoItemRepository->getTodoItemById((int)$itemId);
			if ($item === null || $item->getListId() !== (int)$listId) {
				$this->response->text('<p>Item nicht gefunden.</p>', 404);
				return;
			}
			$newState = !$item->getIsChecked();
			$todoItemRepository->setIsChecked((int)$itemId, $newState);
			$item->setIsChecked($newState);
			$this->response->text($this->renderItem($item, (int)$listId));
		} catch (\Exception $ex) {
			$this->response->text('<p>Fehler beim Aktualisieren</p>' . $ex->getMessage(), 500);
		}
	}

	private function renderItem(\App\TodoItem\TodoItem $item, int $listId): string {
		$checked = $item->getIsChecked();
		$checkedClass = $checked ? ' checked' : '';
		$checkedAttr  = $checked ? ' checked' : '';
		$html  = '<div class="item' . $checkedClass . '">';
		$html .= '<label class="d-flex align-items-center gap-3 flex-grow-1 mb-0" style="cursor:pointer;">';
		$html .= '<input class="form-check-input" type="checkbox"' . $checkedAttr;
		$html .= ' hx-patch="/todo/lists/' . $listId . '/items/' . $item->getId() . '"';
		$html .= ' hx-target="closest .item" hx-swap="outerHTML">';
		$html .= '<div class="item-main">';
		$html .= '<span class="item-content">' . \App\Tags::linkifyHtml($item->getContent()) . '</span>';
		if ($item->getDueDate()) {
			$html .= self::renderDueBadge($item->getDueDate(), $checked);
		}
		$html .= '</div>';
		$html .= '</label>';
		$html .= '<button type="button" class="btn btn-sm btn-outline-danger" hx-delete="/todo/lists/' . $listId . '/items/' . $item->getId() . '" hx-target="closest .item" hx-swap="outerHTML"><i class="fa-solid fa-trash-can"></i></button>';
		$html .= '</div>';
		return $html;
	}

	public static function renderDueBadge(string $dueDate, bool $checked): string
	{
		try {
			$due = new \DateTime($dueDate);
		} catch (\Throwable) {
			return '';
		}
		$now = new \DateTime();
		$diff = $due->getTimestamp() - $now->getTimestamp();

		$class = 'due-badge';
		if ($checked) {
			$class .= ' due-done';
		} elseif ($diff < 0) {
			$class .= ' due-overdue';
		} elseif ($due->format('Y-m-d') === $now->format('Y-m-d')) {
			$class .= ' due-today';
		} elseif ($diff < 3 * 86400) {
			$class .= ' due-soon';
		} else {
			$class .= ' due-future';
		}

		$today = $now->format('Y-m-d');
		$tomorrow = (clone $now)->modify('+1 day')->format('Y-m-d');
		$dueDay = $due->format('Y-m-d');

		if ($dueDay === $today) {
			$label = 'Heute · ' . $due->format('H:i');
		} elseif ($dueDay === $tomorrow) {
			$label = 'Morgen · ' . $due->format('H:i');
		} else {
			$label = $due->format('d.m.Y · H:i');
		}

		return '<span class="' . $class . '"><i class="fa-regular fa-clock me-1"></i>' . htmlspecialchars($label) . '</span>';
	}

	public static function normalizeDueDateInput(?string $input): ?string
	{
		if ($input === null) {
			return null;
		}
		$input = trim($input);
		if ($input === '') {
			return null;
		}
		// datetime-local => "2026-05-28T14:30" => "2026-05-28 14:30:00"
		$input = str_replace('T', ' ', $input);
		if (strlen($input) === 16) {
			$input .= ':00';
		}
		return $input;
	}

	public function deleteTodoItemHtml($listId, $itemId) {
		$userId = $this->currentUserId();
		$this->assertListBelongsToUser((int)$listId, $userId);
		try {
			$todoItemRepository = new TodoItemRepository($this->pdo);
			$deleted = $todoItemRepository->deleteTodoItem((int)$itemId);
			if (!$deleted) {
				$this->response->text('<p>Error deleting item</p>', 400);
				return;
			}
			$this->response->text('');
		} catch (\Exception $ex) {
			$this->response->text('<p>Error deleting item</p>' . $ex->getMessage(), 500);
		}
	}
}
