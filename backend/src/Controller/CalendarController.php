<?php

declare(strict_types=1);

namespace App;

use App\TodoItem\TodoItemRepository;

class CalendarController extends Controller
{
    private const MONTHS_DE = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];

    private function currentUserId(): int
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (empty($userId)) {
            $this->response->text('<p>Nicht eingeloggt.</p>', 401);
        }
        return (int)$userId;
    }

    public function indexHtml(?string $year = null, ?string $month = null): void
    {
        $userId = $this->currentUserId();
        $now = new \DateTime();
        $y = $year !== null ? (int)$year : (int)$now->format('Y');
        $m = $month !== null ? (int)$month : (int)$now->format('n');
        if ($m < 1 || $m > 12) { $m = (int)$now->format('n'); }
        if ($y < 1970 || $y > 2999) { $y = (int)$now->format('Y'); }

        $itemRepo = new TodoItemRepository($this->pdo);
        $items = $itemRepo->getItemsForMonthByUserId($userId, $y, $m);

        $byDay = [];
        foreach ($items as $row) {
            $day = substr((string)$row['due_date'], 0, 10);
            $byDay[$day][] = $row;
        }

        $html  = '<div class="dashboard-page">';

        $html .= '<div class="dashboard-header mb-4 d-flex align-items-center justify-content-between gap-3 flex-wrap">';
        $html .= '<div>';
        $html .= '<h1 class="display-6 fw-bold mb-1">' . self::MONTHS_DE[$m - 1] . ' ' . $y . '</h1>';
        $html .= '<p class="text-muted mb-0">' . count($items) . ' fällige Aufgaben in diesem Monat</p>';
        $html .= '</div>';
        $html .= '<div class="d-flex align-items-center gap-2">';
        [$py, $pm] = self::shiftMonth($y, $m, -1);
        [$ny, $nm] = self::shiftMonth($y, $m,  1);
        $html .= '<button class="btn-icon" hx-get="/calendar/' . $py . '/' . $pm . '" hx-target="#main-content" hx-swap="innerHTML" title="Vorheriger Monat"><i class="fa-solid fa-chevron-left"></i></button>';
        $html .= '<button class="btn-icon" hx-get="/calendar" hx-target="#main-content" hx-swap="innerHTML" title="Heute"><i class="fa-solid fa-house"></i></button>';
        $html .= '<button class="btn-icon" hx-get="/calendar/' . $ny . '/' . $nm . '" hx-target="#main-content" hx-swap="innerHTML" title="Nächster Monat"><i class="fa-solid fa-chevron-right"></i></button>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div class="dashboard-card">';
        $html .= '<div class="dashboard-card-body p-0">';
        $html .= $this->renderMonthGrid($y, $m, $byDay, $now);
        $html .= '</div></div>';

        $html .= '</div>';
        $this->response->text($html);
    }

    private function renderMonthGrid(int $year, int $month, array $byDay, \DateTime $now): string
    {
        $first = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
        // ISO weekday: 1 = Mo … 7 = So. We want Mo-first grid.
        $startOffset = (int)$first->format('N') - 1; // days before the 1st
        $daysInMonth = (int)$first->format('t');
        $totalCells = (int)ceil(($startOffset + $daysInMonth) / 7) * 7;

        $today = $now->format('Y-m-d');

        $weekdays = ['Mo','Di','Mi','Do','Fr','Sa','So'];
        $html = '<div class="calendar-grid">';
        foreach ($weekdays as $wd) {
            $html .= '<div class="calendar-weekday">' . $wd . '</div>';
        }

        for ($cell = 0; $cell < $totalCells; $cell++) {
            $dayNum = $cell - $startOffset + 1;
            if ($dayNum < 1 || $dayNum > $daysInMonth) {
                $html .= '<div class="calendar-cell calendar-cell-empty"></div>';
                continue;
            }
            $date = sprintf('%04d-%02d-%02d', $year, $month, $dayNum);
            $isToday = $date === $today;
            $cls = 'calendar-cell' . ($isToday ? ' calendar-cell-today' : '');
            $html .= '<div class="' . $cls . '">';
            $html .= '<div class="calendar-cell-day">' . $dayNum . '</div>';
            $items = $byDay[$date] ?? [];
            if (!empty($items)) {
                $html .= '<div class="calendar-cell-items">';
                foreach (array_slice($items, 0, 3) as $row) {
                    $checked = (bool)$row['isChecked'];
                    $chipCls = 'calendar-chip' . ($checked ? ' checked' : '');
                    $time = substr((string)$row['due_date'], 11, 5);
                    $listId = (int)$row['listId'];
                    $title = htmlspecialchars((string)$row['content']) . ' (' . htmlspecialchars((string)$row['listName']) . ')';
                    $html .= '<a class="' . $chipCls . '" title="' . $title . '" hx-get="/todo/lists/' . $listId . '" hx-target="#main-content" hx-swap="innerHTML">';
                    $html .= '<span class="calendar-chip-time">' . $time . '</span> ';
                    $html .= '<span class="calendar-chip-text">' . htmlspecialchars((string)$row['content']) . '</span>';
                    $html .= '</a>';
                }
                if (count($items) > 3) {
                    $html .= '<div class="calendar-chip-more">+' . (count($items) - 3) . ' weitere</div>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    private static function shiftMonth(int $year, int $month, int $delta): array
    {
        $m = $month + $delta;
        $y = $year;
        while ($m < 1) { $m += 12; $y--; }
        while ($m > 12) { $m -= 12; $y++; }
        return [$y, $m];
    }
}
