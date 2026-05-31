<?php

declare(strict_types=1);

namespace App\Lyrics;

use App\DatabaseQueryBuilder;
use Repository;

class LyricsRepository extends Repository
{
    protected const TABLENAME_LYRICS = "Lyrics";

    public function listByUserId(int $userId, ?string $search = null, ?int $projectId = null, int $limit = 200): array
    {
        if ($userId <= 0) {
            return [];
        }
        $limit = max(1, min(500, $limit));

        $builder = (new DatabaseQueryBuilder($this->getConnection()))
            ->select(['id', 'userId', 'projectId', 'title', 'content', 'created_at', 'updated_at'])
            ->table(self::TABLENAME_LYRICS)
            ->where('userId', '=', $userId);

        if ($projectId !== null) {
            $builder->where('projectId', '=', $projectId);
        }
        if ($search !== null && trim($search) !== '') {
            $like = '%' . trim($search) . '%';
            $builder->where('title', 'LIKE', $like)->orWhere('content', 'LIKE', $like);
        }

        $rows = $builder->orderBy('updated_at', 'DESC')->limit($limit)->get();
        return array_map([$this, 'mapRow'], $rows);
    }

    public function getById(int $id, int $userId): ?Lyrics
    {
        if ($id <= 0 || $userId <= 0) {
            return null;
        }
        $rows = (new DatabaseQueryBuilder($this->getConnection()))
            ->select(['id', 'userId', 'projectId', 'title', 'content', 'created_at', 'updated_at'])
            ->table(self::TABLENAME_LYRICS)
            ->where('id', '=', $id)
            ->where('userId', '=', $userId)
            ->limit(1)
            ->get();
        return $rows ? $this->mapRow($rows[0]) : null;
    }

    public function create(int $userId, string $title, string $content, ?int $projectId = null): int
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException("User ID must be positive");
        }
        $builder = (new DatabaseQueryBuilder($this->getConnection()))
            ->insert([
                'userId' => $userId,
                'projectId' => $projectId,
                'title' => $title,
                'content' => $content,
            ])
            ->table(self::TABLENAME_LYRICS);
        $builder->execute();
        return (int)$builder->getLastInsertId();
    }

    public function update(int $id, int $userId, string $title, string $content): bool
    {
        if ($id <= 0 || $userId <= 0) {
            return false;
        }
        return (new DatabaseQueryBuilder($this->getConnection()))
            ->update(['title' => $title, 'content' => $content])
            ->table(self::TABLENAME_LYRICS)
            ->where('id', '=', $id)
            ->where('userId', '=', $userId)
            ->execute();
    }

    public function setProjectId(int $id, int $userId, ?int $projectId): bool
    {
        return (new DatabaseQueryBuilder($this->getConnection()))
            ->update(['projectId' => $projectId])
            ->table(self::TABLENAME_LYRICS)
            ->where('id', '=', $id)
            ->where('userId', '=', $userId)
            ->execute();
    }

    public function delete(int $id, int $userId): bool
    {
        if ($id <= 0 || $userId <= 0) {
            return false;
        }
        return (new DatabaseQueryBuilder($this->getConnection()))
            ->delete()
            ->table(self::TABLENAME_LYRICS)
            ->where('id', '=', $id)
            ->where('userId', '=', $userId)
            ->execute();
    }

    /** All lyrics content concatenated — used to build a personal rhyme corpus. */
    public function getAllContentForUser(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }
        $rows = (new DatabaseQueryBuilder($this->getConnection()))
            ->select(['content'])
            ->table(self::TABLENAME_LYRICS)
            ->where('userId', '=', $userId)
            ->get();
        return implode("\n", array_map(fn ($r) => (string)($r['content'] ?? ''), $rows));
    }

    private function mapRow(array $row): Lyrics
    {
        return new Lyrics(
            (int)$row['id'],
            (int)$row['userId'],
            (string)$row['title'],
            (string)($row['content'] ?? ''),
            $row['created_at'] ?? null,
            $row['updated_at'] ?? null,
            isset($row['projectId']) ? (int)$row['projectId'] : null,
        );
    }
}
