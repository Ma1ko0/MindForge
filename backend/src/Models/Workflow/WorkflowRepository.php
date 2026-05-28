<?php

declare(strict_types=1);

namespace App\Workflow;

use App\DatabaseQueryBuilder;
use Repository;

class WorkflowRepository extends Repository
{
    protected const TABLENAME_BLOCKS = "WorkflowBlocks";
    protected const TABLENAME_TEMPLATES = "WorkflowTemplates";
    protected const TABLENAME_TEMPLATE_BLOCKS = "WorkflowTemplateBlocks";

    // ---------------- Blocks ----------------

    public function getBlocksForDay(int $userId, string $date): array
    {
        if ($userId <= 0) {
            return [];
        }
        return (new DatabaseQueryBuilder($this->getConnection()))
            ->select([
                'b.id', 'b.userId', 'b.blockDate', 'b.startTime', 'b.endTime',
                'b.title', 'b.description', 'b.color', 'b.isDone',
                'b.linkedTodoItemId', 'b.linkedNoteId',
                'ti.content AS linkedTodoContent',
                'ti.isChecked AS linkedTodoChecked',
                'ti.listId AS linkedTodoListId',
                'n.title AS linkedNoteTitle',
            ])
            ->table(self::TABLENAME_BLOCKS . ' b')
            ->leftJoin('TodoItems ti', 'b.linkedTodoItemId', '=', 'ti.id')
            ->leftJoin('Notes n', 'b.linkedNoteId', '=', 'n.id')
            ->where('b.userId', '=', $userId)
            ->where('b.blockDate', '=', $date)
            ->orderBy('b.startTime', 'ASC')
            ->orderBy('b.id', 'ASC')
            ->get();
    }

    public function getBlockById(int $blockId, int $userId): ?array
    {
        $rows = (new DatabaseQueryBuilder($this->getConnection()))
            ->select(['*'])
            ->table(self::TABLENAME_BLOCKS)
            ->where('id', '=', $blockId)
            ->where('userId', '=', $userId)
            ->limit(1)
            ->get();
        return $rows[0] ?? null;
    }

    public function createBlock(
        int $userId,
        string $date,
        string $startTime,
        string $endTime,
        string $title,
        string $description,
        string $color,
        ?int $linkedTodoItemId,
        ?int $linkedNoteId
    ): int {
        $builder = (new DatabaseQueryBuilder($this->getConnection()))
            ->insert([
                'userId' => $userId,
                'blockDate' => $date,
                'startTime' => $startTime,
                'endTime' => $endTime,
                'title' => $title,
                'description' => $description,
                'color' => $color,
                'linkedTodoItemId' => $linkedTodoItemId,
                'linkedNoteId' => $linkedNoteId,
            ])
            ->table(self::TABLENAME_BLOCKS);
        $builder->execute();
        return (int)$builder->getLastInsertId();
    }

    public function updateBlock(
        int $blockId,
        int $userId,
        string $startTime,
        string $endTime,
        string $title,
        string $description,
        string $color,
        ?int $linkedTodoItemId,
        ?int $linkedNoteId
    ): bool {
        return (new DatabaseQueryBuilder($this->getConnection()))
            ->update([
                'startTime' => $startTime,
                'endTime' => $endTime,
                'title' => $title,
                'description' => $description,
                'color' => $color,
                'linkedTodoItemId' => $linkedTodoItemId,
                'linkedNoteId' => $linkedNoteId,
            ])
            ->table(self::TABLENAME_BLOCKS)
            ->where('id', '=', $blockId)
            ->where('userId', '=', $userId)
            ->execute();
    }

    public function setBlockDone(int $blockId, int $userId, bool $done): bool
    {
        return (new DatabaseQueryBuilder($this->getConnection()))
            ->update(['isDone' => $done ? 1 : 0])
            ->table(self::TABLENAME_BLOCKS)
            ->where('id', '=', $blockId)
            ->where('userId', '=', $userId)
            ->execute();
    }

    public function deleteBlock(int $blockId, int $userId): bool
    {
        return (new DatabaseQueryBuilder($this->getConnection()))
            ->delete()
            ->table(self::TABLENAME_BLOCKS)
            ->where('id', '=', $blockId)
            ->where('userId', '=', $userId)
            ->execute();
    }

    public function countBlocksForDay(int $userId, string $date): array
    {
        $rows = (new DatabaseQueryBuilder($this->getConnection()))
            ->select([
                'COUNT(*) AS total',
                'SUM(CASE WHEN isDone = 1 THEN 1 ELSE 0 END) AS done',
            ])
            ->table(self::TABLENAME_BLOCKS)
            ->where('userId', '=', $userId)
            ->where('blockDate', '=', $date)
            ->get();
        $row = $rows[0] ?? ['total' => 0, 'done' => 0];
        return ['total' => (int)$row['total'], 'done' => (int)($row['done'] ?? 0)];
    }

    // ---------------- Templates ----------------

    public function listTemplates(int $userId): array
    {
        return (new DatabaseQueryBuilder($this->getConnection()))
            ->select(['t.id', 't.userId', 't.name', 't.description', 't.created_at', 't.updated_at', 'COUNT(b.id) AS blockCount'])
            ->table(self::TABLENAME_TEMPLATES . ' t')
            ->leftJoin(self::TABLENAME_TEMPLATE_BLOCKS . ' b', 'b.templateId', '=', 't.id')
            ->where('t.userId', '=', $userId)
            ->groupBy('t.id', 't.userId', 't.name', 't.description', 't.created_at', 't.updated_at')
            ->orderBy('t.name', 'ASC')
            ->get();
    }

    public function getTemplate(int $templateId, int $userId): ?array
    {
        $rows = (new DatabaseQueryBuilder($this->getConnection()))
            ->select(['*'])
            ->table(self::TABLENAME_TEMPLATES)
            ->where('id', '=', $templateId)
            ->where('userId', '=', $userId)
            ->limit(1)
            ->get();
        return $rows[0] ?? null;
    }

    public function getTemplateBlocks(int $templateId): array
    {
        return (new DatabaseQueryBuilder($this->getConnection()))
            ->select(['*'])
            ->table(self::TABLENAME_TEMPLATE_BLOCKS)
            ->where('templateId', '=', $templateId)
            ->orderBy('startTime', 'ASC')
            ->orderBy('sortOrder', 'ASC')
            ->get();
    }

    public function createTemplate(int $userId, string $name, string $description): int
    {
        $builder = (new DatabaseQueryBuilder($this->getConnection()))
            ->insert(['userId' => $userId, 'name' => $name, 'description' => $description])
            ->table(self::TABLENAME_TEMPLATES);
        $builder->execute();
        return (int)$builder->getLastInsertId();
    }

    public function updateTemplate(int $templateId, int $userId, string $name, string $description): bool
    {
        return (new DatabaseQueryBuilder($this->getConnection()))
            ->update(['name' => $name, 'description' => $description])
            ->table(self::TABLENAME_TEMPLATES)
            ->where('id', '=', $templateId)
            ->where('userId', '=', $userId)
            ->execute();
    }

    public function deleteTemplate(int $templateId, int $userId): bool
    {
        return (new DatabaseQueryBuilder($this->getConnection()))
            ->delete()
            ->table(self::TABLENAME_TEMPLATES)
            ->where('id', '=', $templateId)
            ->where('userId', '=', $userId)
            ->execute();
    }

    public function addTemplateBlock(int $templateId, string $startTime, string $endTime, string $title, string $description, string $color): int
    {
        $builder = (new DatabaseQueryBuilder($this->getConnection()))
            ->insert([
                'templateId' => $templateId,
                'startTime' => $startTime,
                'endTime' => $endTime,
                'title' => $title,
                'description' => $description,
                'color' => $color,
            ])
            ->table(self::TABLENAME_TEMPLATE_BLOCKS);
        $builder->execute();
        return (int)$builder->getLastInsertId();
    }

    public function deleteTemplateBlock(int $blockId, int $templateId): bool
    {
        return (new DatabaseQueryBuilder($this->getConnection()))
            ->delete()
            ->table(self::TABLENAME_TEMPLATE_BLOCKS)
            ->where('id', '=', $blockId)
            ->where('templateId', '=', $templateId)
            ->execute();
    }

    public function applyTemplateToDate(int $templateId, int $userId, string $date): int
    {
        $blocks = $this->getTemplateBlocks($templateId);
        $count = 0;
        foreach ($blocks as $b) {
            $this->createBlock(
                $userId,
                $date,
                (string)$b['startTime'],
                (string)$b['endTime'],
                (string)$b['title'],
                (string)($b['description'] ?? ''),
                (string)($b['color'] ?? 'blue'),
                null,
                null,
            );
            $count++;
        }
        return $count;
    }
}
