<?php

declare(strict_types=1);

namespace App\TodoItem;

use App\DatabaseQueryBuilder;
use App\Response;
use Repository;

class TodoItemRepository extends Repository
{
	//TODO: make maybe traits? to check for valid user id as it is needed very often
	public function getTodoItemsByUserId(string $userid): array
	{
		if (empty($userId)) {
			throw new \InvalidArgumentException("User ID cannot be empty");
		}

		if (!is_string($userId)) {
			throw new \InvalidArgumentException("User ID must be a string");
		}

		if (strlen($userId) > 255) {
			throw new \LengthException("User ID is too long");
		}

		if (!preg_match("/^[a-zA-Z0-9]+$/", $userId)) {
			throw new \InvalidArgumentException("User ID contains invalid characters");
		}
		$tablenameTodoItemToUser = self::TABLENAME_TODOITEMTOUSER;
		$allTodoItemIds = (new DatabaseQueryBuilder($this->getConnection()))
			->select()
			->table($tablenameTodoItemToUser)
			->where("id", "=", $userId)
			->get();
		if (sizeof($allTodoItemIds) == 0) {
			return [];
		}
		$tablenameTodoItems = self::TABLENAME_TODOITEM;
		
		$todoItems = (new DatabaseQueryBuilder($this->getConnection()))
			->select()
			->table($tablenameTodoItems)
			->where("id", "IN", $allTodoItemIds)
			->get();
		
		$items = [];
		foreach ($todoItems as $item) {
			$items[] = $this->mapRowToTodoItem($item);
		}
		return $items;
	}

	public function mapRowToTodoItem(array $row): TodoItem
	{
		return new TodoItem($row["content"], $row["isChecked"]);
	}
}
