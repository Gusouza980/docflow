<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreTaskChecklistItemRequest;
use App\Http\Requests\Web\UpdateTaskChecklistItemRequest;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class TaskChecklistItemController extends Controller
{
    public function store(StoreTaskChecklistItemRequest $request, Task $task, WebOrganizationContext $webOrganizationContext): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($task->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);

        $task->checklistItems()->create([
            ...$request->validated(),
            'organization_id' => $task->organization_id,
        ]);

        return redirect()->route('tasks.show', $task)->with('status', 'Item adicionado.');
    }

    public function update(UpdateTaskChecklistItemRequest $request, TaskChecklistItem $item, WebOrganizationContext $webOrganizationContext): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($item->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);

        $data = $request->validated();
        $item->update([
            ...$data,
            'completed_at' => array_key_exists('is_completed', $data) && $data['is_completed'] ? now() : ($data['is_completed'] ?? null ? $item->completed_at : null),
        ]);

        return redirect()->route('tasks.show', $item->task)->with('status', 'Checklist atualizado.');
    }

    public function destroy(TaskChecklistItem $item, WebOrganizationContext $webOrganizationContext): RedirectResponse
    {
        $request = request();
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($item->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);

        $task = $item->task;
        $item->delete();

        return redirect()->route('tasks.show', $task)->with('status', 'Item removido.');
    }
}
