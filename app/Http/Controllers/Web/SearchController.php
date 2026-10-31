<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\OrganizationMember;
use App\Models\Task;
use App\Support\WebOrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SearchController extends Controller
{
    public function __invoke(Request $request, WebOrganizationContext $webOrganizationContext): Response
    {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);

        $term = trim($request->string('q')->toString());

        return Inertia::render('Search/Index', [
            'query' => $term,
            'clients' => $term === '' ? [] : $this->clients($membership, $term),
            'tasks' => $term === '' ? [] : $this->tasks($membership, $term),
        ]);
    }

    /**
     * @return list<array{id: int, title: string, href: string}>
     */
    private function clients(OrganizationMember $membership, string $term): array
    {
        return Client::query()
            ->whereBelongsTo($membership->organization)
            ->where(function (Builder $query) use ($term): void {
                $query->where('display_name', 'like', "%{$term}%")
                    ->orWhere('document_number', 'like', "%{$term}%");
            })
            ->when(! $membership->isAdmin() && ! $membership->isManager(), function (Builder $query) use ($membership): void {
                $query->where(function (Builder $query) use ($membership): void {
                    $query->where('access_policy', Client::ACCESS_ALL_MEMBERS)
                        ->orWhereHas('responsibles', fn (Builder $query) => $query->whereKey($membership->id))
                        ->orWhereHas('accessMembers', fn (Builder $query) => $query->whereKey($membership->id));
                });
            })
            ->orderBy('display_name')
            ->limit(20)
            ->get()
            ->map(fn (Client $client): array => [
                'id' => $client->id,
                'title' => $client->display_name,
                'href' => route('clients.show', $client, absolute: false),
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, title: string, href: string}>
     */
    private function tasks(OrganizationMember $membership, string $term): array
    {
        return Task::query()
            ->whereBelongsTo($membership->organization)
            ->where('title', 'like', "%{$term}%")
            ->when(! $membership->isAdmin() && ! $membership->isManager(), function (Builder $query) use ($membership): void {
                $query->where(function (Builder $query) use ($membership): void {
                    $query->whereNull('client_id')
                        ->orWhereHas('client', function (Builder $query) use ($membership): void {
                            $query->where('access_policy', Client::ACCESS_ALL_MEMBERS)
                                ->orWhereHas('responsibles', fn (Builder $query) => $query->whereKey($membership->id))
                                ->orWhereHas('accessMembers', fn (Builder $query) => $query->whereKey($membership->id));
                        });
                });
            })
            ->orderBy('title')
            ->limit(20)
            ->get()
            ->map(fn (Task $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'href' => route('tasks.show', $task, absolute: false),
            ])
            ->all();
    }
}
