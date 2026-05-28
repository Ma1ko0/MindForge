<?php

declare(strict_types=1);

namespace App\TodoList;

use App\DatabaseQueryBuilder;
use Repository;

class TodoListRepository extends Repository
{
	public function getTodoListsByUserId(int $userId, ?int $projectId = null): array
	{
		if ($userId <= 0) {
			throw new \InvalidArgumentException("User ID must be positive integer");
		}

		$builder = (new DatabaseQueryBuilder($this->getConnection()))
			->select()
			->table(self::TABLENAME_TODOLIST)
			->where("userId", "=", $userId);
		if ($projectId !== null) {
			$builder->where("projectId", "=", $projectId);
		}
		$todoLists = $builder->get();

		$lists = [];
		foreach ($todoLists as $list) {
			$lists[] = $this->mapRowToTodoList($list);
		}
		return $lists;
	}

	public function createTodoList(string $name, int $userId, ?int $projectId = null)
	{
		if (empty($name)) {
			throw new \InvalidArgumentException("List name cannot be empty");
		}
		if ($userId <= 0) {
			throw new \InvalidArgumentException("User ID must be positive integer");
		}

		$todoListArray = [
			'name' => $name,
			'userId' => $userId,
			'projectId' => $projectId,
		];
		return (new DatabaseQueryBuilder($this->getConnection()))
			->insert($todoListArray)
			->table(self::TABLENAME_TODOLIST)
			->execute();
	}

	public function setProjectId(int $listId, int $userId, ?int $projectId): bool
	{
		return (new DatabaseQueryBuilder($this->getConnection()))
			->update(['projectId' => $projectId])
			->table(self::TABLENAME_TODOLIST)
			->where('id', '=', $listId)
			->where('userId', '=', $userId)
			->execute();
	}

	public function deleteTodoList(int $listId, int $userId): bool
	{
		if ($listId <= 0) {
			throw new \InvalidArgumentException("List ID must be positive integer");
		}
		if ($userId <= 0) {
			throw new \InvalidArgumentException("User ID must be positive integer");
		}

		$tablename = self::TABLENAME_TODOLIST;
		return (new DatabaseQueryBuilder($this->getConnection()))
			->delete()
			->table($tablename)
			->where("id", "=", $listId)
			->where("userId", "=", $userId)
			->execute();
	}

	public function getTodoListByName(string $name, int $userId): TodoList
	{
		if (empty($name)) {
			throw new \InvalidArgumentException("List name cannot be empty");
		}
		if ($userId <= 0) {
			throw new \InvalidArgumentException("User ID must be positive integer");
		}

		$tablename = self::TABLENAME_TODOLIST;
		$todoLists = (new DatabaseQueryBuilder($this->getConnection()))
			->select()
			->table($tablename)
			->where("name", "=", $name)
			->where("userId", "=", $userId)
			->get();

		if (empty($todoLists)) {
			throw new \Exception("Todo list not found");
		}

		return $this->mapRowToTodoList($todoLists[0]);
	}

	public function getTodoListsWithStatsByUserId(int $userId): array
	{
		if ($userId <= 0) {
			return [];
		}
		return (new DatabaseQueryBuilder($this->getConnection()))
			->select([
				'l.id', 'l.name', 'l.userId', 'l.projectId',
				'COUNT(i.id) AS total',
				'SUM(CASE WHEN i.isChecked = 1 THEN 1 ELSE 0 END) AS done',
				'p.name AS project_name',
				'p.color AS project_color',
				'p.icon AS project_icon',
			])
			->table(self::TABLENAME_TODOLIST . ' l')
			->leftJoin(self::TABLENAME_TODOITEM . ' i', 'i.listId', '=', 'l.id')
			->leftJoin('Projects p', 'l.projectId', '=', 'p.id')
			->where('l.userId', '=', $userId)
			->groupBy('l.id', 'l.name', 'l.userId', 'l.projectId', 'p.name', 'p.color', 'p.icon')
			->orderBy('l.name', 'ASC')
			->get();
	}

	public function getListStats(int $listId): array
	{
		$rows = (new DatabaseQueryBuilder($this->getConnection()))
			->select([
				'COUNT(*) AS total',
				'SUM(CASE WHEN isChecked = 1 THEN 1 ELSE 0 END) AS done',
			])
			->table(self::TABLENAME_TODOITEM)
			->where('listId', '=', $listId)
			->get();
		$row = $rows[0] ?? ['total' => 0, 'done' => 0];
		return ['total' => (int)$row['total'], 'done' => (int)($row['done'] ?? 0)];
	}

	public function countByUserId(int $userId): int
	{
		if ($userId <= 0) {
			return 0;
		}
		$rows = (new DatabaseQueryBuilder($this->getConnection()))
			->select(['COUNT(*) AS cnt'])
			->table(self::TABLENAME_TODOLIST)
			->where('userId', '=', $userId)
			->get();
		return (int)($rows[0]['cnt'] ?? 0);
	}

	public function userOwnsList(int $listId, int $userId): bool
	{
		if ($listId <= 0 || $userId <= 0) {
			return false;
		}
		$tablename = self::TABLENAME_TODOLIST;
		$rows = (new DatabaseQueryBuilder($this->getConnection()))
			->select(['id'])
			->table($tablename)
			->where('id', '=', $listId)
			->where('userId', '=', $userId)
			->get();
		return !empty($rows);
	}

	public function mapRowToTodoList(array $row): TodoList
	{
		return new TodoList(
			(int)$row["id"],
			(string)$row["name"],
			(int)$row["userId"],
			isset($row["projectId"]) ? (int)$row["projectId"] : null,
		);
	}
}