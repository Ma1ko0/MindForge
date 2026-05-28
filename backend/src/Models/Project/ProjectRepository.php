<?php

declare(strict_types=1);

namespace App\Project;

use App\DatabaseQueryBuilder;
use Repository;

class ProjectRepository extends Repository
{
    protected const TABLENAME_PROJECT = "Projects";

    public function listByUserId(int $userId, bool $includeArchived = false): array
    {
        if ($userId <= 0) {
            return [];
        }
        $builder = (new DatabaseQueryBuilder($this->getConnection()))
            ->select(['id', 'userId', 'name', 'description', 'color', 'icon', 'is_archived', 'created_at', 'updated_at'])
            ->table(self::TABLENAME_PROJECT)
            ->where('userId', '=', $userId);
        if (!$includeArchived) {
            $builder->where('is_archived', '=', 0);
        }
        $rows = $builder->orderBy('name', 'ASC')->get();
        return array_map([$this, 'mapRow'], $rows);
    }

    public function listByUserIdWithStats(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        return (new DatabaseQueryBuilder($this->getConnection()))
            ->select([
                'p.id', 'p.userId', 'p.name', 'p.description', 'p.color', 'p.icon', 'p.is_archived', 'p.created_at', 'p.updated_at',
                '(SELECT COUNT(*) FROM TodoLists WHERE projectId = p.id) AS list_count',
                '(SELECT COUNT(*) FROM Notes WHERE projectId = p.id) AS note_count',
                '(SELECT COUNT(*) FROM TodoItems i INNER JOIN TodoLists l ON i.listId = l.id WHERE l.projectId = p.id AND i.isChecked = 0) AS open_count',
            ])
            ->table(self::TABLENAME_PROJECT . ' p')
            ->where('p.userId', '=', $userId)
            ->where('p.is_archived', '=', 0)
            ->orderBy('p.name', 'ASC')
            ->get();
    }

    public function getById(int $id, int $userId): ?Project
    {
        if ($id <= 0 || $userId <= 0) {
            return null;
        }
        $rows = (new DatabaseQueryBuilder($this->getConnection()))
            ->select(['id', 'userId', 'name', 'description', 'color', 'icon', 'is_archived', 'created_at', 'updated_at'])
            ->table(self::TABLENAME_PROJECT)
            ->where('id', '=', $id)
            ->where('userId', '=', $userId)
            ->limit(1)
            ->get();
        return $rows ? $this->mapRow($rows[0]) : null;
    }

    public function create(int $userId, string $name, string $description, string $color, string $icon): int
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException("User ID must be positive");
        }
        $builder = (new DatabaseQueryBuilder($this->getConnection()))
            ->insert([
                'userId' => $userId,
                'name' => $name,
                'description' => $description,
                'color' => $color,
                'icon' => $icon,
            ])
            ->table(self::TABLENAME_PROJECT);
        $builder->execute();
        return (int)$builder->getLastInsertId();
    }

    public function update(int $id, int $userId, string $name, string $description, string $color, string $icon): bool
    {
        return (new DatabaseQueryBuilder($this->getConnection()))
            ->update([
                'name' => $name,
                'description' => $description,
                'color' => $color,
                'icon' => $icon,
            ])
            ->table(self::TABLENAME_PROJECT)
            ->where('id', '=', $id)
            ->where('userId', '=', $userId)
            ->execute();
    }

    public function setArchived(int $id, int $userId, bool $archived): bool
    {
        return (new DatabaseQueryBuilder($this->getConnection()))
            ->update(['is_archived' => $archived ? 1 : 0])
            ->table(self::TABLENAME_PROJECT)
            ->where('id', '=', $id)
            ->where('userId', '=', $userId)
            ->execute();
    }

    public function delete(int $id, int $userId): bool
    {
        return (new DatabaseQueryBuilder($this->getConnection()))
            ->delete()
            ->table(self::TABLENAME_PROJECT)
            ->where('id', '=', $id)
            ->where('userId', '=', $userId)
            ->execute();
    }

    private function mapRow(array $row): Project
    {
        return new Project(
            (int)$row['id'],
            (int)$row['userId'],
            (string)$row['name'],
            (string)($row['description'] ?? ''),
            (string)($row['color'] ?? 'blue'),
            (string)($row['icon'] ?? 'folder'),
            (int)($row['is_archived'] ?? 0) === 1,
            $row['created_at'] ?? null,
            $row['updated_at'] ?? null,
        );
    }
}
