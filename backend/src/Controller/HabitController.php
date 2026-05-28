<?php

declare(strict_types=1);

namespace App;

use App\Habit\Habit;
use App\Habit\HabitRepository;

class HabitController extends Controller
{
    private const COLORS = ['blue', 'purple', 'green', 'orange', 'red', 'gray'];
    private const COLOR_LABELS = [
        'blue' => 'Blau', 'purple' => 'Lila', 'green' => 'Grün',
        'orange' => 'Orange', 'red' => 'Rot', 'gray' => 'Grau',
    ];
    private const ICONS = ['repeat', 'dumbbell', 'book', 'pen', 'spa', 'mug-hot', 'glass-water', 'person-running', 'bed', 'heart', 'brain', 'music'];
    private const FREQUENCIES = [
        Habit::FREQ_DAILY    => 'Täglich',
        Habit::FREQ_WEEKDAYS => 'Mo–Fr',
        Habit::FREQ_WEEKEND  => 'Sa–So',
        Habit::FREQ_CUSTOM   => 'Bestimmte Tage',
    ];
    private const WEEKDAY_LABELS = ['1' => 'Mo', '2' => 'Di', '3' => 'Mi', '4' => 'Do', '5' => 'Fr', '6' => 'Sa', '7' => 'So'];

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
        return in_array($i, self::ICONS, true) ? $i : 'repeat';
    }

    private function normalizeFrequency(?string $f): string
    {
        return array_key_exists($f, self::FREQUENCIES) ? $f : Habit::FREQ_DAILY;
    }

    private function normalizeWeekdays(array $days): string
    {
        $valid = array_filter($days, fn ($d) => isset(self::WEEKDAY_LABELS[(string)$d]));
        $valid = array_values(array_unique(array_map('strval', $valid)));
        sort($valid);
        return implode(',', $valid);
    }

    public function indexHtml(): void
    {
        $userId = $this->currentUserId();
        $repo = new HabitRepository($this->pdo);
        $habits = $repo->listByUserId($userId, false);
        $today = (new \DateTime())->format('Y-m-d');

        $html  = '<div class="dashboard-page">';
        $html .= '<div class="dashboard-header mb-4">';
        $html .= '<h1 class="display-6 fw-bold mb-1">Habits</h1>';
        $html .= '<p class="text-muted mb-0">Dinge die du regelmäßig tun willst. Streak-Counter incentiviert dich nicht abzubrechen.</p>';
        $html .= '</div>';

        $html .= '<div class="dashboard-card mb-4">';
        $html .= '<div class="dashboard-card-head"><h2 class="h5 mb-0"><i class="fa-solid fa-plus me-2 text-info"></i>Neues Habit</h2></div>';
        $html .= '<div class="dashboard-card-body">';
        $html .= $this->renderHabitForm(null);
        $html .= '</div></div>';

        $html .= '<div id="habits-list">';
        if (empty($habits)) {
            $html .= '<div class="lists-empty"><div class="lists-empty-icon"><i class="fa-solid fa-repeat"></i></div><h3 class="mb-2">Noch keine Habits</h3><p class="text-muted mb-0">Leg oben dein erstes Habit an. Sport, Lesen, Meditation, Wasser trinken — was auch immer.</p></div>';
        } else {
            foreach ($habits as $h) {
                $html .= $this->renderHabitCard($h, $repo, $today);
            }
        }
        $html .= '</div>';

        $html .= '</div>';
        $this->response->text($html);
    }

    private function renderHabitCard(Habit $h, HabitRepository $repo, string $today): string
    {
        $id = (int)$h->getId();
        $streak = $repo->calculateStreak($h);
        $isScheduled = $h->isScheduledOn($today);
        $isCompleted = $repo->isCompletedOn($id, $today);
        $color = $h->getColor();

        $cls = 'habit-card habit-color-' . htmlspecialchars($color);
        if (!$h->isActive()) $cls .= ' habit-inactive';
        if ($isCompleted) $cls .= ' habit-done';

        $html  = '<div id="habit-' . $id . '" class="' . $cls . '">';

        $html .= '<div class="habit-card-main">';
        $html .= '<div class="habit-icon"><i class="fa-solid fa-' . htmlspecialchars($h->getIcon()) . '"></i></div>';
        $html .= '<div class="habit-card-body">';
        $html .= '<h3 class="habit-card-name">' . htmlspecialchars($h->getName()) . '</h3>';
        $html .= '<div class="habit-card-meta">';
        $html .= '<span><i class="fa-regular fa-calendar me-1"></i>' . htmlspecialchars($this->frequencyLabel($h)) . '</span>';
        $html .= '<span class="habit-streak"><i class="fa-solid fa-fire me-1"></i>' . $streak . ($streak === 1 ? ' Tag' : ' Tage') . '</span>';
        $html .= '</div>';
        if ($h->getDescription() !== '') {
            $html .= '<p class="habit-card-desc mb-0">' . htmlspecialchars($h->getDescription()) . '</p>';
        }
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div class="habit-card-actions">';
        if ($isScheduled && $h->isActive()) {
            if ($isCompleted) {
                $html .= '<button class="btn habit-check-btn done" hx-patch="/habits/' . $id . '/uncheck" hx-target="#habit-' . $id . '" hx-swap="outerHTML" title="Markierung zurücknehmen"><i class="fa-solid fa-circle-check"></i> Erledigt</button>';
            } else {
                $html .= '<button class="btn habit-check-btn" hx-patch="/habits/' . $id . '/check" hx-target="#habit-' . $id . '" hx-swap="outerHTML">Heute erledigt</button>';
            }
        } else if (!$h->isActive()) {
            $html .= '<span class="text-muted small">Pausiert</span>';
        } else {
            $html .= '<span class="text-muted small">Heute nicht geplant</span>';
        }
        $html .= '<div class="habit-actions-row">';
        $html .= '<button class="btn-icon btn-icon-sm" hx-get="/habits/' . $id . '/edit" hx-target="#habit-' . $id . '" hx-swap="outerHTML" title="Bearbeiten"><i class="fa-solid fa-pen"></i></button>';
        $html .= '<button class="btn-icon btn-icon-sm" hx-patch="/habits/' . $id . '/toggle-active" hx-target="#habit-' . $id . '" hx-swap="outerHTML" title="' . ($h->isActive() ? 'Pausieren' : 'Aktivieren') . '"><i class="fa-solid fa-' . ($h->isActive() ? 'pause' : 'play') . '"></i></button>';
        $html .= '<button class="btn-icon btn-icon-sm" hx-delete="/habits/' . $id . '" hx-target="#habit-' . $id . '" hx-swap="outerHTML" hx-confirm="Habit wirklich löschen? Alle Completions gehen verloren." title="Löschen"><i class="fa-solid fa-trash-can"></i></button>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>';
        return $html;
    }

    private function frequencyLabel(Habit $h): string
    {
        if ($h->getFrequency() === Habit::FREQ_CUSTOM) {
            $days = array_filter(array_map('trim', explode(',', $h->getWeekdays())), fn ($d) => $d !== '');
            $labels = array_map(fn ($d) => self::WEEKDAY_LABELS[$d] ?? '', $days);
            return implode(', ', array_filter($labels));
        }
        return self::FREQUENCIES[$h->getFrequency()] ?? 'Täglich';
    }

    private function renderHabitForm(?Habit $existing): string
    {
        $name = $existing ? $existing->getName() : '';
        $desc = $existing ? $existing->getDescription() : '';
        $freq = $existing ? $existing->getFrequency() : Habit::FREQ_DAILY;
        $wd   = $existing ? explode(',', $existing->getWeekdays()) : [];
        $color = $existing ? $existing->getColor() : 'blue';
        $icon = $existing ? $existing->getIcon() : 'repeat';

        $action = $existing
            ? 'hx-put="/habits/' . $existing->getId() . '" hx-target="#habit-' . $existing->getId() . '" hx-swap="outerHTML"'
            : 'hx-post="/habits" hx-target="#habits-list" hx-swap="innerHTML"';

        $html  = '<form class="workflow-form" ' . $action . '>';
        $html .= '<div class="row g-2">';
        $html .= '<div class="col-12 col-md-8"><input class="form-control" type="text" name="name" placeholder="Name (z.B. „Lesen 30 min")" maxlength="120" value="' . htmlspecialchars($name) . '" required></div>';
        $html .= '<div class="col-12 col-md-4">';
        $html .= '<select class="form-select" name="color">';
        foreach (self::COLORS as $c) {
            $sel = $c === $color ? ' selected' : '';
            $html .= '<option value="' . $c . '"' . $sel . '>' . self::COLOR_LABELS[$c] . '</option>';
        }
        $html .= '</select></div>';

        $html .= '<div class="col-12"><textarea class="form-control" name="description" rows="1" placeholder="Beschreibung (optional)">' . htmlspecialchars($desc) . '</textarea></div>';

        $html .= '<div class="col-12 col-md-6">';
        $html .= '<label class="form-label small text-muted mb-1">Häufigkeit</label>';
        $html .= '<select class="form-select" name="frequency" onchange="this.closest(\'form\').querySelector(\'.weekdays-row\').hidden = this.value !== \'custom\'">';
        foreach (self::FREQUENCIES as $val => $lbl) {
            $sel = $val === $freq ? ' selected' : '';
            $html .= '<option value="' . $val . '"' . $sel . '>' . $lbl . '</option>';
        }
        $html .= '</select></div>';

        $html .= '<div class="col-12 col-md-6 weekdays-row"' . ($freq === Habit::FREQ_CUSTOM ? '' : ' hidden') . '>';
        $html .= '<label class="form-label small text-muted mb-1">Wochentage (für Custom)</label>';
        $html .= '<div class="weekday-picker">';
        foreach (self::WEEKDAY_LABELS as $val => $lbl) {
            $checked = in_array($val, $wd, true) ? ' checked' : '';
            $html .= '<label class="weekday-option"><input type="checkbox" name="weekdays[]" value="' . $val . '"' . $checked . ' hidden>' . $lbl . '</label>';
        }
        $html .= '</div></div>';

        $html .= '<div class="col-12"><label class="form-label small text-muted mb-1">Icon</label>';
        $html .= '<div class="icon-picker">';
        foreach (self::ICONS as $i) {
            $html .= '<label class="icon-option"><input type="radio" name="icon" value="' . $i . '"' . ($i === $icon ? ' checked' : '') . ' hidden><i class="fa-solid fa-' . $i . '"></i></label>';
        }
        $html .= '</div></div>';

        $html .= '<div class="col-12 d-flex justify-content-end gap-2">';
        if ($existing) {
            $html .= '<button type="button" class="btn btn-link" hx-get="/habits/' . $existing->getId() . '/cancel" hx-target="#habit-' . $existing->getId() . '" hx-swap="outerHTML">Abbrechen</button>';
        }
        $html .= '<button class="btn btn-primary" type="submit"><i class="fa-solid fa-' . ($existing ? 'floppy-disk' : 'plus') . ' me-2"></i>' . ($existing ? 'Speichern' : 'Anlegen') . '</button>';
        $html .= '</div>';

        $html .= '</div>';
        $html .= '</form>';
        return $html;
    }

    public function createHtml(): void
    {
        $userId = $this->currentUserId();
        $name = trim((string)($this->request->getPostData('name') ?? ''));
        $desc = trim((string)($this->request->getPostData('description') ?? ''));
        $freq = $this->normalizeFrequency((string)($this->request->getPostData('frequency') ?? ''));
        $wd = (array)($this->request->getPostData('weekdays') ?? []);
        $weekdays = $this->normalizeWeekdays($wd);
        $color = $this->normalizeColor((string)($this->request->getPostData('color') ?? ''));
        $icon = $this->normalizeIcon((string)($this->request->getPostData('icon') ?? ''));

        if ($name === '') {
            $this->response->text('<div class="alert alert-danger">Name fehlt.</div>', 400);
            return;
        }
        if ($freq === Habit::FREQ_CUSTOM && $weekdays === '') {
            $this->response->text('<div class="alert alert-danger">Wähle mindestens einen Wochentag.</div>', 400);
            return;
        }

        $repo = new HabitRepository($this->pdo);
        $repo->create($userId, $name, $desc, $freq, $weekdays, $color, $icon);
        // Re-render entire list (simpler than OOB)
        $habits = $repo->listByUserId($userId, false);
        $today = (new \DateTime())->format('Y-m-d');
        $html = '';
        foreach ($habits as $h) {
            $html .= $this->renderHabitCard($h, $repo, $today);
        }
        header('HX-Trigger: dashboard-changed');
        $this->response->text($html);
    }

    public function editHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $habit = (new HabitRepository($this->pdo))->getById((int)$id, $userId);
        if ($habit === null) {
            $this->response->text('', 404);
            return;
        }
        $html  = '<div id="habit-' . (int)$id . '" class="habit-card-editing">';
        $html .= $this->renderHabitForm($habit);
        $html .= '</div>';
        $this->response->text($html);
    }

    public function cancelEditHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $repo = new HabitRepository($this->pdo);
        $habit = $repo->getById((int)$id, $userId);
        if ($habit === null) {
            $this->response->text('');
            return;
        }
        $this->response->text($this->renderHabitCard($habit, $repo, (new \DateTime())->format('Y-m-d')));
    }

    public function updateHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $name = trim((string)($this->request->getPostData('name') ?? ''));
        $desc = trim((string)($this->request->getPostData('description') ?? ''));
        $freq = $this->normalizeFrequency((string)($this->request->getPostData('frequency') ?? ''));
        $wd = (array)($this->request->getPostData('weekdays') ?? []);
        $weekdays = $this->normalizeWeekdays($wd);
        $color = $this->normalizeColor((string)($this->request->getPostData('color') ?? ''));
        $icon = $this->normalizeIcon((string)($this->request->getPostData('icon') ?? ''));
        if ($name === '') {
            $this->response->text('<div class="alert alert-danger">Name fehlt.</div>', 400);
            return;
        }
        $repo = new HabitRepository($this->pdo);
        $repo->update((int)$id, $userId, $name, $desc, $freq, $weekdays, $color, $icon);
        $habit = $repo->getById((int)$id, $userId);
        if ($habit === null) {
            $this->response->text('', 404);
            return;
        }
        $this->response->text($this->renderHabitCard($habit, $repo, (new \DateTime())->format('Y-m-d')));
    }

    public function deleteHtml(string $id): void
    {
        $userId = $this->currentUserId();
        (new HabitRepository($this->pdo))->delete((int)$id, $userId);
        $this->response->text('');
    }

    public function checkHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $repo = new HabitRepository($this->pdo);
        $habit = $repo->getById((int)$id, $userId);
        if ($habit === null) {
            $this->response->text('', 404);
            return;
        }
        $today = (new \DateTime())->format('Y-m-d');
        $repo->markComplete((int)$id, $today);
        header('HX-Trigger: habits-changed, dashboard-changed');
        $this->response->text($this->renderHabitCard($habit, $repo, $today));
    }

    public function uncheckHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $repo = new HabitRepository($this->pdo);
        $habit = $repo->getById((int)$id, $userId);
        if ($habit === null) {
            $this->response->text('', 404);
            return;
        }
        $today = (new \DateTime())->format('Y-m-d');
        $repo->markIncomplete((int)$id, $today);
        header('HX-Trigger: habits-changed, dashboard-changed');
        $this->response->text($this->renderHabitCard($habit, $repo, $today));
    }

    public function toggleActiveHtml(string $id): void
    {
        $userId = $this->currentUserId();
        $repo = new HabitRepository($this->pdo);
        $habit = $repo->getById((int)$id, $userId);
        if ($habit === null) {
            $this->response->text('', 404);
            return;
        }
        $repo->setActive((int)$id, $userId, !$habit->isActive());
        $habit = $repo->getById((int)$id, $userId);
        $this->response->text($this->renderHabitCard($habit, $repo, (new \DateTime())->format('Y-m-d')));
    }

    // ---------------- Dashboard widget ----------------

    public function widgetHtml(): void
    {
        $userId = $this->currentUserId();
        $repo = new HabitRepository($this->pdo);
        $habits = $repo->listByUserId($userId, true);
        $today = (new \DateTime())->format('Y-m-d');
        $todays = array_filter($habits, fn ($h) => $h->isScheduledOn($today));

        if (empty($todays)) {
            $this->response->text('<div class="empty-hint text-muted text-center py-3">Heute kein Habit geplant. <a class="auth-link" hx-get="/habits" hx-target="#main-content" hx-swap="innerHTML">Habits verwalten</a></div>');
            return;
        }

        $html = '<div class="habit-widget-list">';
        foreach ($todays as $h) {
            $id = (int)$h->getId();
            $done = $repo->isCompletedOn($id, $today);
            $streak = $repo->calculateStreak($h);
            $cls = 'habit-widget-row habit-color-' . htmlspecialchars($h->getColor()) . ($done ? ' done' : '');
            $html .= '<div class="' . $cls . '">';
            $html .= '<label class="d-flex align-items-center gap-2 flex-grow-1 mb-0" style="cursor:pointer;">';
            $html .= '<input class="form-check-input" type="checkbox"' . ($done ? ' checked' : '');
            $url = $done ? '/habits/' . $id . '/uncheck' : '/habits/' . $id . '/check';
            $html .= ' hx-patch="' . $url . '" hx-swap="none">';
            $html .= '<i class="fa-solid fa-' . htmlspecialchars($h->getIcon()) . ' habit-widget-icon"></i>';
            $html .= '<span class="habit-widget-name">' . htmlspecialchars($h->getName()) . '</span>';
            $html .= '</label>';
            $html .= '<span class="habit-widget-streak"><i class="fa-solid fa-fire me-1"></i>' . $streak . '</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $this->response->text($html);
    }
}
