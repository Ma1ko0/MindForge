<?php

declare(strict_types=1);

namespace App\TodoItem;

use App\DatabaseQueryBuilder;
use Repository;

class TodoItemRepository extends Repository
{
	//TODO: make maybe traits? to check for valid user id as it is needed very often
	public function getTodoItemsByListId(int $listId): array
	{
		if ($listId <= 0) {
			throw new \InvalidArgumentException("List ID must be positive integer");
		}

		$tablename = self::TABLENAME_TODOITEM;
		$todoItems = (new DatabaseQueryBuilder($this->getConnection()))
			->select()
			->table($tablename)
			->where("listId", "=", $listId)
			->get();

		$items = [];
		foreach ($todoItems as $item) {
			$items[] = $this->mapRowToTodoItem($item);
		}
		return $items;
	}

	public function createTodoItem(string $content, bool $isChecked, int $listId, ?string $dueDate = null): int
	{
		if ($listId <= 0) {
			throw new \InvalidArgumentException("List ID must be positive integer");
		}

		$tablename = self::TABLENAME_TODOITEM;
		$todoItemArray = [
			'content' => $content,
			'isChecked' => intval($isChecked),
			'listId' => $listId
		];
		if ($dueDate !== null && $dueDate !== '') {
			$todoItemArray['due_date'] = $dueDate;
		}

		$builder = (new DatabaseQueryBuilder($this->getConnection()))
			->insert($todoItemArray)
			->table($tablename);

		$builder->execute();
		return (int)$builder->getLastInsertId();
	}

	public function setDueDate(int $itemId, ?string $dueDate): bool
	{
		if ($itemId <= 0) {
			throw new \InvalidArgumentException("Item ID must be positive integer");
		}
		return (new DatabaseQueryBuilder($this->getConnection()))
			->update(['due_date' => ($dueDate !== null && $dueDate !== '') ? $dueDate : null])
			->table(self::TABLENAME_TODOITEM)
			->where('id', '=', $itemId)
			->execute();
	}

	public function getItemsDueOnDate(int $userId, string $date): array
	{
		if ($userId <= 0) {
			return [];
		}
		return (new DatabaseQueryBuilder($this->getConnection()))
			->select(['i.id', 'i.content', 'i.isChecked', 'i.listId', 'i.due_date', 'l.name AS listName'])
			->table(self::TABLENAME_TODOITEM . ' i')
			->join(self::TABLENAME_TODOLIST . ' l', 'i.listId', '=', 'l.id')
			->where('l.userId', '=', $userId)
			->whereRaw('DATE(i.due_date) = :due_date', [':due_date' => $date])
			->orderBy('i.due_date', 'ASC')
			->get();
	}

	public function getItemsForMonthByUserId(int $userId, int $year, int $month): array
	{
		if ($userId <= 0) {
			return [];
		}
		$start = sprintf('%04d-%02d-01 00:00:00', $year, $month);
		$end   = date('Y-m-t 23:59:59', strtotime($start));
		return (new DatabaseQueryBuilder($this->getConnection()))
			->select(['i.id', 'i.content', 'i.isChecked', 'i.listId', 'i.due_date', 'l.name AS listName'])
			->table(self::TABLENAME_TODOITEM . ' i')
			->join(self::TABLENAME_TODOLIST . ' l', 'i.listId', '=', 'l.id')
			->where('l.userId', '=', $userId)
			->whereBetween('i.due_date', [$start, $end])
			->orderBy('i.due_date', 'ASC')
			->get();
	}

	public function countOpenByUserId(int $userId): int
	{
		if ($userId <= 0) {
			return 0;
		}
		$rows = (new DatabaseQueryBuilder($this->getConnection()))
			->select(['COUNT(*) AS cnt'])
			->table(self::TABLENAME_TODOITEM . ' i')
			->join(self::TABLENAME_TODOLIST . ' l', 'i.listId', '=', 'l.id')
			->where('l.userId', '=', $userId)
			->where('i.isChecked', '=', 0)
			->get();
		return (int)($rows[0]['cnt'] ?? 0);
	}

	public function countCompletedTodayByUserId(int $userId): int
	{
		if ($userId <= 0) {
			return 0;
		}
		$rows = (new DatabaseQueryBuilder($this->getConnection()))
			->select(['COUNT(*) AS cnt'])
			->table(self::TABLENAME_TODOITEM . ' i')
			->join(self::TABLENAME_TODOLIST . ' l', 'i.listId', '=', 'l.id')
			->where('l.userId', '=', $userId)
			->where('i.isChecked', '=', 1)
			->whereRaw('DATE(i.updated_at) = CURDATE()')
			->get();
		return (int)($rows[0]['cnt'] ?? 0);
	}

	public function getRecentByUserId(int $userId, int $limit = 5): array
	{
		if ($userId <= 0) {
			return [];
		}
		$limit = max(1, min(50, $limit));
		return (new DatabaseQueryBuilder($this->getConnection()))
			->select(['i.id', 'i.content', 'i.isChecked', 'i.listId', 'i.created_at', 'l.name AS listName'])
			->table(self::TABLENAME_TODOITEM . ' i')
			->join(self::TABLENAME_TODOLIST . ' l', 'i.listId', '=', 'l.id')
			->where('l.userId', '=', $userId)
			->orderBy('i.created_at', 'DESC')
			->limit($limit)
			->get();
	}

	public function getTodoItemById(int $itemId): ?TodoItem
	{
		if ($itemId <= 0) {
			throw new \InvalidArgumentException("Item ID must be positive integer");
		}
		$tablename = self::TABLENAME_TODOITEM;
		$rows = (new DatabaseQueryBuilder($this->getConnection()))
			->select()
			->table($tablename)
			->where("id", "=", $itemId)
			->get();
		if (empty($rows)) {
			return null;
		}
		return $this->mapRowToTodoItem($rows[0]);
	}

	public function setIsChecked(int $itemId, bool $isChecked): bool
	{
		if ($itemId <= 0) {
			throw new \InvalidArgumentException("Item ID must be positive integer");
		}
		$tablename = self::TABLENAME_TODOITEM;
		return (new DatabaseQueryBuilder($this->getConnection()))
			->update(['isChecked' => intval($isChecked)])
			->table($tablename)
			->where("id", "=", $itemId)
			->execute();
	}

	public function deleteTodoItem(int $itemId): bool
	{
		if ($itemId <= 0) {
			throw new \InvalidArgumentException("Item ID must be positive integer");
		}

		$tablename = self::TABLENAME_TODOITEM;
		return (new DatabaseQueryBuilder($this->getConnection()))
			->delete()
			->table($tablename)
			->where("id", "=", $itemId)
			->execute();
	}

	public function mapRowToTodoItem(array $row): TodoItem
	{
		return new TodoItem(
			$row["id"],
			$row["content"],
			(bool)$row["isChecked"],
			(int)$row["listId"],
			$row["due_date"] ?? null,
		);
	}
}
