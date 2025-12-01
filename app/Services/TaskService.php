<?php

namespace App\Services;

use App\Models\task;

class TaskService
{
    function getAll($id = null)
    {
        if (!$id) {
            return task::all();
        }

        return task::find($id);
    }

    function create($data)
    {
        $task = new task;
        $task->name = $data['name'];
        $task->description = $data['description'] ?? null;
        $task->save();

        return $task;
    }

    function update($id, $data)
    {
        $task = task::find($id);
        if (!$task) {
            return null;
        }

        $task->name = $data['name'];
        if (isset($data['description'])) {
            $task->description = $data['description'];
        }
        $task->save();

        return $task;
    }

    function delete($id)
    {
        $task = task::find($id);
        if (!$task) {
            return false;
        }

        return $task->delete();
    }
}

