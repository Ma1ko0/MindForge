<?php

declare(strict_types=1);

namespace App;

use App\TodoItem\TodoItemRepository;

class TodoItemController extends Controller
{
	public function getAllTodoItems() {
		$userId = "0";
		$items = [];
		try {
			$todoItemRepository = new TodoItemRepository($this->pdo);
			$items = $todoItemRepository->getTodoItemsByUserId($userId);
		} catch (\Exception $ex) {
			$this->response->error($ex->getMessage());
		}
		$this->response->success($items);
		$this->logger->logging("User Requested successfully his todoItems", DEBUG);
	}
}