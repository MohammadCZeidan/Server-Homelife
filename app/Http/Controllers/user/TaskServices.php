<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\task;

class TaskController extends Controller
{
    function getAllTasks($id = null)
    {
        if (!$id) {
            $tasks = task::all();
            return $this->responseJSON($tasks);
        }

        $task = task::find($id);
        if (!$task) {
            return $this->responseJSON(null, "failure", 404);
        }
        return $this->responseJSON($task);
    }

    function addOrUpdateTask(Request $request, $id = "add")
    {
        if ($id == "add") {
            $task = new task;
        } else {
            $task = task::find($id);
            if (!$task) {
                return $this->responseJSON(null, "failure", 400);
            }
        }

        $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $task->name = $request->name;
        if ($request->has('description')) {
            $task->description = $request->description;
        }

        if ($task->save()) {
            return $this->responseJSON($task);
        }

        return $this->responseJSON(null, "failure", 400);
    }

    function deleteTask($id)
    {
        $task = task::find($id);
        if (!$task) {
            return $this->responseJSON(null, "failure", 404);
        }

        if ($task->delete()) {
            return $this->responseJSON(null, "success");
        }

        return $this->responseJSON(null, "failure", 400);
    }
}
