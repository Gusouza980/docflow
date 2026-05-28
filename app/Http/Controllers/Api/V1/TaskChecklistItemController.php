<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class TaskChecklistItemController extends Controller
{
    public function store(Request $request, Task $task, RecordAuditLog $auditLog): JsonResponse
    {
        $this->authorizeTask($task);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'is_required' => ['nullable', 'boolean'],
        ]);

        $item = $task->checklistItems()->create([
            ...$data,
            'organization_id' => $task->organization_id,
        ]);
        $auditLog->execute('task_checklist_item.created', $request->user(), $task->organization, $task, request: $request);

        return response()->json(['data' => $item], Response::HTTP_CREATED);
    }

    public function update(Request $request, TaskChecklistItem $item, RecordAuditLog $auditLog): JsonResponse
    {
        $this->authorizeTask($item->task);
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'is_required' => ['sometimes', 'boolean'],
            'is_completed' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('is_completed', $data)) {
            $data['completed_at'] = $data['is_completed'] ? now() : null;
        }

        $item->update($data);
        $auditLog->execute('task_checklist_item.updated', $request->user(), $item->organization, $item->task, request: $request);

        return response()->json(['data' => $item]);
    }

    public function destroy(TaskChecklistItem $item, Request $request, RecordAuditLog $auditLog): JsonResponse
    {
        $this->authorizeTask($item->task);
        $item->delete();
        $auditLog->execute('task_checklist_item.deleted', $request->user(), $item->organization, $item->task, request: $request);

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }

    private function authorizeTask(Task $task): void
    {
        abort_if($task->organization_id !== app(OrganizationContext::class)->id(), Response::HTTP_NOT_FOUND);
        Gate::authorize('update', $task);
    }
}
