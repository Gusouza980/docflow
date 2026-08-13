<?php

namespace App\Jobs\Automations;

use App\Automations\AutomationRunner;
use App\Models\Organization;
use App\Models\Plan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

class RunAutomationRule implements ShouldQueue
{
    use Queueable;

    public bool $afterCommit = true;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public int $organizationId,
        public string $trigger,
        public string $subjectType,
        public int $subjectId,
        public array $context = [],
        public ?string $dedupeSuffix = null,
    ) {}

    public function handle(AutomationRunner $runner): void
    {
        if (! Plan::query()->exists()) {
            return;
        }

        $organization = Organization::query()->find($this->organizationId);

        if ($organization === null) {
            return;
        }

        /** @var class-string<Model> $subjectType */
        $subjectType = $this->subjectType;
        $subject = $subjectType::query()->find($this->subjectId);

        if ($subject === null) {
            return;
        }

        $runner->dispatch(
            organization: $organization,
            trigger: $this->trigger,
            subject: $subject,
            context: $this->context,
            dedupeSuffix: $this->dedupeSuffix,
        );
    }

    public static function dispatchFor(Model $subject, string $trigger, array $context = [], ?string $dedupeSuffix = null): void
    {
        $organizationId = $subject->getAttribute('organization_id');

        if (! $organizationId) {
            return;
        }

        self::dispatch(
            organizationId: (int) $organizationId,
            trigger: $trigger,
            subjectType: $subject::class,
            subjectId: (int) $subject->getKey(),
            context: $context,
            dedupeSuffix: $dedupeSuffix,
        );
    }
}
