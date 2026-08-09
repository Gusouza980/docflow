<?php

use App\Models\Plan;
use App\Support\Billing\NormalizesPlanFeatures;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $normalizer = app(NormalizesPlanFeatures::class);

        Plan::query()->each(function (Plan $plan) use ($normalizer): void {
            $plan->forceFill([
                'features' => $normalizer->backfillMissing($plan->features, $plan->slug),
            ])->save();
        });
    }

    public function down(): void
    {
        //
    }
};
