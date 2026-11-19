<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Central Domain
    |--------------------------------------------------------------------------
    |
    | The root domain of the platform. It serves the landing page, tenant
    | registration and the central login. Each tenant lives on a subdomain
    | of it: "{slug}.{domain}".
    |
    */

    'domain' => env('APP_DOMAIN', 'scheduling-saas.localhost'),

    /*
    |--------------------------------------------------------------------------
    | Reserved Slugs
    |--------------------------------------------------------------------------
    |
    | Subdomains that can never be claimed by a tenant.
    |
    */

    'reserved_slugs' => [
        'admin', 'api', 'app', 'assets', 'billing', 'blog', 'cdn', 'dashboard',
        'docs', 'ftp', 'help', 'login', 'mail', 'register', 'root', 'scheduling-saas',
        'smtp', 'static', 'status', 'support', 'www',
    ],

    /*
    |--------------------------------------------------------------------------
    | Lifetimes
    |--------------------------------------------------------------------------
    */

    'trial_days' => 14,

    'invitation_expires_days' => 7,

    // How long the one-time link that carries a freshly registered owner from
    // the central domain to the tenant subdomain stays valid.
    'login_handoff_seconds' => 60,

];
