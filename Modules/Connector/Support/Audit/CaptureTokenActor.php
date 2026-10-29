<?php

namespace Modules\Connector\Support\Audit;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Laravel\Passport\Events\AccessTokenCreated;

class CaptureTokenActor
{
    private $app;

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    public function handle(AccessTokenCreated $event): void
    {
        try {
            if (! $this->app->bound('request')) {
                return;
            }
            $request = $this->app->make('request');
            $state = $request instanceof Request ? AuditState::from($request) : null;
            if ($state && $state->tokenRequest && ! $state->emitted) {
                // Passport's trusted event only. No token ID, credentials, or user lookup.
                $state->actorId = AuditMetadata::id($event->userId);
            }
        } catch (\Throwable $ignored) {
            // Audit must never interfere with token issuance.
        }
    }
}