<?php

return [

    'finance' => [
        'overdue_portal_reminder_cooldown_days' => (int) env('DOCFLOW_FINANCE_REMINDER_COOLDOWN_DAYS', 7),
        'delinquent_after_days' => (int) env('DOCFLOW_FINANCE_DELINQUENT_DAYS', 30),
    ],

];
