<?php

namespace Toniel\LaravelKeycloakSocialite\Events;

use Throwable;

class KeycloakAuthenticationFailed
{
    /**
     * Create a new event instance.
     *
     * @param  string  $reason  Human-readable description of the failure.
     * @param  \Throwable|null  $exception  The caught exception, if any.
     * @param  string|null  $errorRedirect  Passed by reference — listeners may override where to redirect on failure.
     * @param  string|null  $errorMessage  Passed by reference — listeners may override the flash message.
     */
    public function __construct(
        public string $reason,
        public ?Throwable $exception = null,
        public ?string &$errorRedirect = null,
        public ?string &$errorMessage = null,
    ) {}
}
