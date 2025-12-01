<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\TaskService;

class TaskController extends Controller
{
    private $taskService;

    function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    function getAllTasks($id = null)
    {
        $result = $this->taskService->getAll($id);
        
        if ($id && !$result) {
            return $this->responseJSON(null, "failure", 404);
        }
        
        return $this->responseJSON($result);
    }

    function addOrUpdateTask(Request $request, $id = "add")
    {
        $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
        ]);

        if ($id == "add") {
            $task = $this->taskService->create($request->all());
        } else {
            $task = $this->taskService->update($id, $request->all());
            if (!$task) {
                return $this->responseJSON(null, "failure", 400);
            }
        }

        return $this->responseJSON($task);
    }

    function deleteTask($id)
    {
        $deleted = $this->taskService->delete($id);
        
        if (!$deleted) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON(null, "success");
    }
}
