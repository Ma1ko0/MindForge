<?php

declare(strict_types=1);

namespace App;

class SearchController extends Controller
{
    private function currentUserId(): int
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (empty($userId)) {
            $this->response->text('', 401);
        }
        return (int)$userId;
    }

    public function searchHtml(): void
    {
        $userId = $this->currentUserId();
        $q = trim((string)$this->request->getQuery('q'));
        if ($q === '' || mb_strlen($q) < 2) {
            $this->response->text('<div class="search-hint">Tipp mindestens 2 Zeichen ein um zu suchen.</div>');
            return;
        }

        $like = '%' . $q . '%';
        $sections = [];

        // Notes
        $notes = (new DatabaseQueryBuilder($this->pdo))
            ->select(['id', 'title', 'content'])
            ->table('Notes')
            ->where('userId', '=', $userId)
            ->where('title', 'LIKE', $like)
            ->orWhere('content', 'LIKE', $like)
            ->orderBy('updated_at', 'DESC')
            ->limit(6)
            ->get();
        if (!empty($notes)) {
            $items = '';
            foreach ($notes as $n) {
                $items .= $this->renderResult(
                    '/notes/' . (int)$n['id'],
                    'note-sticky',
                    (string)$n['title'] ?: 'Ohne Titel',
                    $this->snippet((string)($n['content'] ?? ''), $q),
                    'Notiz'
                );
            }
            $sections[] = ['label' => 'Notizen', 'items' => $items];
        }

        // Projects
        $projects = (new DatabaseQueryBuilder($this->pdo))
            ->select(['id', 'name', 'description', 'color', 'icon'])
            ->table('Projects')
            ->where('userId', '=', $userId)
            ->where('name', 'LIKE', $like)
            ->orWhere('description', 'LIKE', $like)
            ->orderBy('name', 'ASC')
            ->limit(6)
            ->get();
        if (!empty($projects)) {
            $items = '';
            foreach ($projects as $p) {
                $items .= $this->renderResult(
                    '/projects/' . (int)$p['id'],
                    (string)($p['icon'] ?? 'folder'),
                    (string)$p['name'],
                    (string)($p['description'] ?? ''),
                    'Projekt'
                );
            }
            $sections[] = ['label' => 'Projekte', 'items' => $items];
        }

        // Todo Lists
        $lists = (new DatabaseQueryBuilder($this->pdo))
            ->select(['id', 'name'])
            ->table('TodoLists')
            ->where('userId', '=', $userId)
            ->where('name', 'LIKE', $like)
            ->orderBy('name', 'ASC')
            ->limit(6)
            ->get();
        if (!empty($lists)) {
            $items = '';
            foreach ($lists as $l) {
                $items .= $this->renderResult(
                    '/todo/lists/' . (int)$l['id'],
                    'list-check',
                    (string)$l['name'],
                    '',
                    'Liste'
                );
            }
            $sections[] = ['label' => 'Listen', 'items' => $items];
        }

        // Todo Items
        $items = (new DatabaseQueryBuilder($this->pdo))
            ->select(['i.id', 'i.content', 'i.listId', 'i.isChecked', 'l.name AS listName'])
            ->table('TodoItems i')
            ->join('TodoLists l', 'i.listId', '=', 'l.id')
            ->where('l.userId', '=', $userId)
            ->where('i.content', 'LIKE', $like)
            ->orderBy('i.created_at', 'DESC')
            ->limit(6)
            ->get();
        if (!empty($items)) {
            $rendered = '';
            foreach ($items as $it) {
                $checked = (int)$it['isChecked'] === 1;
                $rendered .= $this->renderResult(
                    '/todo/lists/' . (int)$it['listId'],
                    $checked ? 'circle-check' : 'circle',
                    (string)$it['content'],
                    'in ' . (string)$it['listName'],
                    'Aufgabe'
                );
            }
            $sections[] = ['label' => 'Aufgaben', 'items' => $rendered];
        }

        // Workflow Blocks
        $blocks = (new DatabaseQueryBuilder($this->pdo))
            ->select(['id', 'title', 'description', 'blockDate', 'startTime'])
            ->table('WorkflowBlocks')
            ->where('userId', '=', $userId)
            ->where('title', 'LIKE', $like)
            ->orWhere('description', 'LIKE', $like)
            ->orderBy('blockDate', 'DESC')
            ->limit(6)
            ->get();
        if (!empty($blocks)) {
            $rendered = '';
            foreach ($blocks as $b) {
                $date = (string)$b['blockDate'];
                $time = substr((string)$b['startTime'], 0, 5);
                $rendered .= $this->renderResult(
                    '/workflow/day/' . $date,
                    'sun',
                    (string)$b['title'],
                    $date . ' · ' . $time,
                    'Block'
                );
            }
            $sections[] = ['label' => 'Heute / Workflow', 'items' => $rendered];
        }

        if (empty($sections)) {
            $this->response->text('<div class="search-hint">Keine Treffer für „' . htmlspecialchars($q) . '".</div>');
            return;
        }

        $html = '';
        foreach ($sections as $s) {
            $html .= '<div class="search-section">';
            $html .= '<div class="search-section-label">' . htmlspecialchars($s['label']) . '</div>';
            $html .= $s['items'];
            $html .= '</div>';
        }
        $this->response->text($html);
    }

    private function renderResult(string $url, string $icon, string $title, string $subtitle, string $badge): string
    {
        $html  = '<a class="search-result" data-search-result hx-get="' . htmlspecialchars($url) . '" hx-target="#main-content" hx-swap="innerHTML">';
        $html .= '<i class="fa-solid fa-' . htmlspecialchars($icon) . ' search-result-icon"></i>';
        $html .= '<div class="search-result-body">';
        $html .= '<div class="search-result-title">' . htmlspecialchars($title) . '</div>';
        if ($subtitle !== '') {
            $html .= '<div class="search-result-sub">' . htmlspecialchars($subtitle) . '</div>';
        }
        $html .= '</div>';
        $html .= '<span class="search-result-badge">' . htmlspecialchars($badge) . '</span>';
        $html .= '</a>';
        return $html;
    }

    private function snippet(string $content, string $query): string
    {
        $content = preg_replace('/\s+/u', ' ', $content);
        $content = trim((string)$content);
        if ($content === '') {
            return '';
        }
        $pos = mb_stripos($content, $query);
        if ($pos === false) {
            return mb_substr($content, 0, 120) . (mb_strlen($content) > 120 ? '…' : '');
        }
        $start = max(0, $pos - 40);
        $snip = mb_substr($content, $start, 120);
        return ($start > 0 ? '…' : '') . $snip . (mb_strlen($content) > $start + 120 ? '…' : '');
    }
}
