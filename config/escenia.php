<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default plan
    |--------------------------------------------------------------------------
    | Plan key assigned to a tenant on registration. Entitlements are resolved
    | from the plan's features/limits, never from `if ($plan === 'pro')`.
    */
    'default_plan' => env('ESCENIA_DEFAULT_PLAN', 'free'),

    /*
    |--------------------------------------------------------------------------
    | Default currency (ISO-4217)
    |--------------------------------------------------------------------------
    | Used by the Money value object where a currency is otherwise unspecified.
    */
    'default_currency' => env('ESCENIA_DEFAULT_CURRENCY', 'USD'),
];
