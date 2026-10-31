<?php

namespace App\Actions\Finance;

use App\Models\Client;
use App\Models\Receivable;

class RestoreClientStanding
{
    public function execute(?Client $client): void
    {
        if ($client === null || $client->status !== Client::STATUS_DELINQUENT) {
            return;
        }

        $overdueDays = (int) config('docflow.finance.delinquent_after_days', 30);
        $cutoff = now()->subDays($overdueDays)->toDateString();

        $stillDelinquent = $client->receivables()
            ->whereIn('status', [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL])
            ->whereDate('due_at', '<=', $cutoff)
            ->exists();

        if ($stillDelinquent) {
            return;
        }

        $client->update(['status' => Client::STATUS_ACTIVE]);
    }
}
