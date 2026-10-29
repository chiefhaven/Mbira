<?php

namespace Modules\Connector\Support\Audit;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Events\RequestHandled;

class RecordConnectorAudit
{
    private $app;

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    public function handle(RequestHandled $event): void
    {
        $state = AuditState::from($event->request);
        if (! $state || $state->emitted) {
            return;
        }
        // Mark before writing, including on sink failure; duplicate events cannot retry.
        $state->emitted = true;
        try {
            $event->response->headers->set('X-Request-ID', $state->requestId);
            $actorId = $state->tokenRequest ? $state->actorId : null;
            $businessId = null;
            if (! $state->tokenRequest) {
                try {
                    $guard = $this->app->make('auth')->guard('api');
                    // GuardHelpers::hasUser reads cached state without authenticating.
                    // user() is called ONLY when already cached (no bearer revalidation).
                    if (method_exists($guard, 'hasUser') && $guard->hasUser()) {
                        $user = $guard->user();
                        if ($user instanceof Model) {
                            // Raw attributes avoid accessors, relations and business queries.
                            $attributes = $user->getAttributes();
                            $actorId = AuditMetadata::id($attributes['id'] ?? null);
                            $businessId = AuditMetadata::id($attributes['business_id'] ?? null);
                        }
                    }
                } catch (\Throwable $ignored) {
                    // Unknown identity stays null, without falling back to web/session/input.
                }
            }
            $route = $event->request->route();
            $status = $event->response->getStatusCode();
            $metadata = AuditMetadata::sanitize([
                'request_id' => $state->requestId,
                'actor_id' => $actorId,
                'business_id' => $businessId,
                'action' => $route ? $route->getActionName() : null,
                'http_status' => $status,
                'outcome' => $state->outcome($status),
                'duration_ms' => max(0, (hrtime(true) - $state->started) / 1000000),
            ]);
            $this->app->make('log')->channel('connector_audit')->info('connector_request_completed', $metadata);
        } catch (\Throwable $ignored) {
            $this->warnSinkFailure();
        }
    }

    protected function warnSinkFailure(): void
    {
        try {
            // Bypass Laravel/Monolog shared context and processors entirely.
            error_log('Connector audit sink unavailable.');
        } catch (\Throwable $ignored) {
            // Even a failed fallback must not fail a request.
        }
    }
}