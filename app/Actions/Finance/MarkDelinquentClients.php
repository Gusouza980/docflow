<?php

namespace App\Actions\Finance;

use App\Actions\Notifications\NotifyTeamMembers;
use App\Models\Client;
use App\Models\InternalReminder;
use App\Models\Receivable;
use Illuminate\Support\Facades\DB;

class MarkDelinquentClients
{
    public function __construct(
        private NotifyTeamMembers $notifyTeamMembers,
    ) {}

    /**
     * @return list<int>
     */
    public function execute(?int $overdueDays = null): array
    {
        $overdueDays ??= (int) config('docflow.finance.delinquent_after_days', 30);
        $cutoff = now()->subDays($overdueDays)->toDateString();
        $marked = [];

        $clientIds = Receivable::query()
            ->whereIn('status', [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL])
            ->whereDate('due_at', '<=', $cutoff)
            ->distinct()
            ->pluck('client_id')
            ->filter()
            ->values();

        Client::query()
            ->whereIn('id', $clientIds)
            ->where('status', '!=', Client::STATUS_DELINQUENT)
            ->with('organization')
            ->each(function (Client $client) use (&$marked): void {
                DB::transaction(function () use ($client, &$marked): void {
                    $client->update(['status' => Client::STATUS_DELINQUENT]);

                    $this->notifyTeamMembers->execute(
                        organization: $client->organization,
                        remindable: $client,
                        type: InternalReminder::TYPE_CLIENT_DELINQUENT,
                        client: $client,
                        includeManagers: true,
                    );

                    $marked[] = $client->id;
                });
            });

        return $marked;
    }
}
