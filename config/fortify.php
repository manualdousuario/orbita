<?php

use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Features;

return [

    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,
    'home' => '/home',
    'prefix' => '',
    'domain' => null,
    'middleware' => ['web', 'throttle:fortify'],

    'limiters' => [
        'login' => null,
        'two-factor' => null,
    ],

    'pipelines' => [
        'login' => [
            CanonicalizeUsername::class,
            AttemptToAuthenticate::class,
            PrepareAuthenticatedSession::class,
        ],
    ],

    'views' => true,

    'features' => [
        Features::registration(),
        Features::resetPasswords(),
    ],

];
