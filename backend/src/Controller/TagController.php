<?php

declare(strict_types=1);

namespace App;

class TagController extends Controller
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
        $counts = $this->collectTagCounts($userId);

        $html  = '<div class="dashboard-page">';
        $html .= '<div class="dashboard-header mb-4">';
        $html .= '<h1 class="display-6 fw-bold mb-1">Tags</h1>';
        $html .= '<p class="text-muted mb-0">Schreib <code>#tag</code> irgendwo in Notizen, Aufgaben oder Workflow-Blöcken und sie tauchen hier auf.</p>';
        $html .= '</div>';

        $html .= '<div class="dashboard-card">';
        $html .= '<div class="dashboard-card-body">';

        if (empty($counts)) {
            $html .= '<div class="empty-hint text-muted text-center py-4">Noch keine Tags. Schreib <code>#meinTag</code> in eine Notiz oder Aufgabe.</div>';
        } else {
            arsort($counts);
            $html .= '<div class="tag-cloud">';
            foreach ($counts as $tag => $count) {
                $html .= '<a class="tag-chip tag-cloud-item" hx-get="/tags/' . rawurlencode($tag) . '" hx-target="#main-content" hx-swap="innerHTML">';
                $html .= '<span class="tag-cloud-count">' . $count . 'x </span>';
                $html .= '#' . htmlspecialchars($tag);
                $html .= '</a>';
            }
            $html .= '</div>';
        }

        $html .= '</div></div>';
        $html .= '</div>';
        $this->response->text($html);
    }

    public function viewHtml(string $tag): void
    {
        $userId = $this->currentUserId();
        $tag = mb_strtolower(rawurldecode($tag));
        // sanitize — only allow letters/numbers/_/-
        $tag = preg_replace('/[^a-z0-9_\-äöüß]/u', '', $tag) ?: '';
        if ($tag === '') {
            $this->response->text('<div class="alert alert-warning">Ungültiger Tag.</div>', 400);
            return;
        }

        $regexp = '(^|[^a-zA-Z0-9_])#' . $tag . '($|[^a-zA-Z0-9_\\-])';

        $notes = (new DatabaseQueryBuilder($this->pdo))
            ->select(['id', 'title', 'content'])
            ->table('Notes')
            ->where('userId', '=', $userId)
            ->whereRaw('content REGEXP :tag_re', [':tag_re' => $regexp])
            ->orderBy('updated_at', 'DESC')
            ->limit(50)
            ->get();

        $items = (new DatabaseQueryBuilder($this->pdo))
            ->select(['i.id', 'i.content', 'i.listId', 'i.isChecked', 'l.name AS listName'])
            ->table('TodoItems i')
            ->join('TodoLists l', 'i.listId', '=', 'l.id')
            ->where('l.userId', '=', $userId)
            ->whereRaw('i.content REGEXP :tag_re_i', [':tag_re_i' => $regexp])
            ->orderBy('i.created_at', 'DESC')
            ->limit(50)
            ->get();

        $blocks = (new DatabaseQueryBuilder($this->pdo))
            ->select(['id', 'title', 'description', 'blockDate', 'startTime', 'isDone'])
            ->table('WorkflowBlocks')
            ->where('userId', '=', $userId)
            ->whereRaw('(title REGEXP :tag_re_t OR description REGEXP :tag_re_d)', [
                ':tag_re_t' => $regexp,
                ':tag_re_d' => $regexp,
            ])
            ->orderBy('blockDate', 'DESC')
            ->limit(50)
            ->get();

        $totalCount = count($notes) + count($items) + count($blocks);

        $html  = '<div class="dashboard-page">';
        $html .= '<div class="dashboard-header mb-4 d-flex align-items-center gap-3">';
        $html .= '<button class="btn-icon" hx-get="/tags" hx-target="#main-content" hx-swap="innerHTML" title="Zurück"><i class="fa-solid fa-arrow-left"></i></button>';
        $html .= '<div>';
        $html .= '<h1 class="display-6 fw-bold mb-1">#' . htmlspecialchars($tag) . '</h1>';
        $html .= '<p class="text-muted mb-0">' . $totalCount . ' Treffer über alle Module.</p>';
        $html .= '</div>';
        $html .= '</div>';

        if ($totalCount === 0) {
            $html .= '<div class="lists-empty"><div class="lists-empty-icon"><i class="fa-solid fa-hashtag"></i></div><h3 class="mb-2">Keine Treffer</h3><p class="text-muted mb-0">Schreib <code>#' . htmlspecialchars($tag) . '</code> in eine Notiz oder Aufgabe.</p></div>';
            $html .= '</div>';
            $this->response->text($html);
            return;
        }

        if (!empty($notes)) {
            $html .= $this->renderSection('Notizen', 'note-sticky');
            $html .= '<div class="notes-grid mb-4">';
            foreach ($notes as $n) {
                $title = (string)$n['title'] ?: 'Ohne Titel';
                $excerpt = mb_substr(preg_replace('/\s+/u', ' ', (string)$n['content']), 0, 140);
                $html .= '<div class="note-card" hx-get="/notes/' . (int)$n['id'] . '" hx-target="#main-content" hx-swap="innerHTML">';
                $html .= '<div class="note-card-head"><h3 class="note-card-title">' . htmlspecialchars($title) . '</h3></div>';
                $html .= '<p class="note-card-excerpt">' . htmlspecialchars($excerpt) . '</p>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        if (!empty($items)) {
            $html .= $this->renderSection('Aufgaben', 'list-check');
            $html .= '<div class="dashboard-card mb-4"><div class="dashboard-card-body"><div class="d-grid gap-2">';
            foreach ($items as $it) {
                $checked = (int)$it['isChecked'] === 1;
                $cls = 'item' . ($checked ? ' checked' : '');
                $html .= '<div class="' . $cls . '" style="cursor:pointer" hx-get="/todo/lists/' . (int)$it['listId'] . '" hx-target="#main-content" hx-swap="innerHTML">';
                $html .= '<div class="item-main">';
                $html .= '<span class="item-content">' . Tags::linkifyHtml((string)$it['content']) . '</span>';
                $html .= '<span class="text-muted small">in ' . htmlspecialchars((string)$it['listName']) . '</span>';
                $html .= '</div>';
                $html .= '</div>';
            }
            $html .= '</div></div></div>';
        }

        if (!empty($blocks)) {
            $html .= $this->renderSection('Workflow-Blöcke', 'sun');
            $html .= '<div class="workflow-timeline mb-4">';
            foreach ($blocks as $b) {
                $color = (string)($b['color'] ?? 'blue');
                $start = substr((string)$b['startTime'], 0, 5);
                $date = (string)$b['blockDate'];
                $cls = 'workflow-block workflow-color-' . htmlspecialchars($color) . ((int)$b['isDone'] === 1 ? ' done' : '');
                $html .= '<div class="' . $cls . '" style="cursor:pointer" hx-get="/workflow/day/' . $date . '" hx-target="#main-content" hx-swap="innerHTML">';
                $html .= '<div class="workflow-block-time">' . htmlspecialchars($start) . '<br><span class="muted">' . htmlspecialchars($date) . '</span></div>';
                $html .= '<div class="workflow-block-body">';
                $html .= '<div class="workflow-block-head"><span class="workflow-block-title">' . Tags::linkifyHtml((string)$b['title']) . '</span></div>';
                if (!empty($b['description'])) {
                    $html .= '<p class="workflow-block-desc">' . Tags::linkifyHtml((string)$b['description']) . '</p>';
                }
                $html .= '</div>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';
        $this->response->text($html);
    }

    private function renderSection(string $label, string $icon): string
    {
        return '<h2 class="h5 mb-3"><i class="fa-solid fa-' . $icon . ' me-2 text-info"></i>' . htmlspecialchars($label) . '</h2>';
    }

    /**
     * Scan all user's content sources and tally tag occurrences.
     *
     * @return array<string, int>
     */
    private function collectTagCounts(int $userId): array
    {
        $counts = [];

        $rows = (new DatabaseQueryBuilder($this->pdo))
            ->select(['content'])
            ->table('Notes')
            ->where('userId', '=', $userId)
            ->whereRaw("content REGEXP '#[[:alnum:]_-]'")
            ->get();
        foreach ($rows as $row) {
            foreach (Tags::extract((string)$row['content']) as $t) {
                $counts[$t] = ($counts[$t] ?? 0) + 1;
            }
        }

        $rows = (new DatabaseQueryBuilder($this->pdo))
            ->select(['i.content'])
            ->table('TodoItems i')
            ->join('TodoLists l', 'i.listId', '=', 'l.id')
            ->where('l.userId', '=', $userId)
            ->whereRaw("i.content REGEXP '#[[:alnum:]_-]'")
            ->get();
        foreach ($rows as $row) {
            foreach (Tags::extract((string)$row['content']) as $t) {
                $counts[$t] = ($counts[$t] ?? 0) + 1;
            }
        }

        $rows = (new DatabaseQueryBuilder($this->pdo))
            ->select(['title', 'description'])
            ->table('WorkflowBlocks')
            ->where('userId', '=', $userId)
            ->whereRaw("(title REGEXP '#[[:alnum:]_-]' OR description REGEXP '#[[:alnum:]_-]')")
            ->get();
        foreach ($rows as $row) {
            $combined = (string)$row['title'] . ' ' . (string)($row['description'] ?? '');
            foreach (Tags::extract($combined) as $t) {
                $counts[$t] = ($counts[$t] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
