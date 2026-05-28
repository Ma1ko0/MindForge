<?php

declare(strict_types=1);

namespace App\Note;

use App\DatabaseQueryBuilder;
use Repository;

class NoteRepository extends Repository
{
    protected const TABLENAME_NOTE = "Notes";

    public function listByUserId(int $userId, ?string $search = null, ?int $projectId = null, int $limit = 100): array
    {
        if ($userId <= 0) {
            return [];
        }
        $limit = max(1, min(500, $limit));

        $builder = (new DatabaseQueryBuilder($this->getConnection()))
            ->select(['id', 'userId', 'projectId', 'title', 'content', 'created_at', 'updated_at'])
            ->table(self::TABLENAME_NOTE)
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

    public function getById(int $id, int $userId): ?Note
    {
        if ($id <= 0 || $userId <= 0) {
            return null;
        }
        $rows = (new DatabaseQueryBuilder($this->getConnection()))
            ->select(['id', 'userId', 'projectId', 'title', 'content', 'created_at', 'updated_at'])
            ->table(self::TABLENAME_NOTE)
            ->where('id', '=', $id)
            ->where('userId', '=', $userId)
            ->limit(1)
            ->get();
        return $rows ? $this->mapRow($rows[0]) : null;
    }

    public function findByTitle(string $title, int $userId): ?Note
    {
        $title = trim($title);
        if ($title === '' || $userId <= 0) {
            return null;
        }
        $rows = (new DatabaseQueryBuilder($this->getConnection()))
            ->select(['id', 'userId', 'projectId', 'title', 'content', 'created_at', 'updated_at'])
            ->table(self::TABLENAME_NOTE)
            ->where('userId', '=', $userId)
            ->whereRaw('LOWER(title) = LOWER(:t_title)', [':t_title' => $title])
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
            ->table(self::TABLENAME_NOTE);
        $builder->execute();
        return (int)$builder->getLastInsertId();
    }

    public function setProjectId(int $id, int $userId, ?int $projectId): bool
    {
        return (new DatabaseQueryBuilder($this->getConnection()))
            ->update(['projectId' => $projectId])
            ->table(self::TABLENAME_NOTE)
            ->where('id', '=', $id)
            ->where('userId', '=', $userId)
            ->execute();
    }

    public function update(int $id, int $userId, string $title, string $content): bool
    {
        if ($id <= 0 || $userId <= 0) {
            return false;
        }
        return (new DatabaseQueryBuilder($this->getConnection()))
            ->update(['title' => $title, 'content' => $content])
            ->table(self::TABLENAME_NOTE)
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
            ->table(self::TABLENAME_NOTE)
            ->where('id', '=', $id)
            ->where('userId', '=', $userId)
            ->execute();
    }

    public function backlinks(int $noteId, int $userId, string $title): array
    {
        if ($noteId <= 0 || $userId <= 0 || trim($title) === '') {
            return [];
        }
        $rows = (new DatabaseQueryBuilder($this->getConnection()))
            ->select(['id', 'userId', 'projectId', 'title', 'content', 'created_at', 'updated_at'])
            ->table(self::TABLENAME_NOTE)
            ->where('userId', '=', $userId)
            ->where('id', '!=', $noteId)
            ->where('content', 'LIKE', '%[[' . $title . ']]%')
            ->orderBy('updated_at', 'DESC')
            ->limit(50)
            ->get();
        return array_map([$this, 'mapRow'], $rows);
    }

    private function mapRow(array $row): Note
    {
        return new Note(
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
