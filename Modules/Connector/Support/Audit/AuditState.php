<?php

namespace Modules\Connector\Support\Audit;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AuditState
{
    const ATTRIBUTE = '_connector_audit_state';

    public $requestId;
    public $started;
    public $tokenRequest;
    public $actorId;
    public $emitted = false;
    private $failed = false;
    private $succeeded = false;

    public function __construct(bool $tokenRequest)
    {
        // Never trust the incoming X-Request-ID.
        $this->requestId = (string) Str::uuid();
        $this->started = hrtime(true);
        $this->tokenRequest = $tokenRequest;
    }

    public static function from(Request $request): ?self
    {
        $state = $request->attributes->get(self::ATTRIBUTE);

        return $state instanceof self ? $state : null;
    }

    // Only server code calls these markers; never inspect a response or request payload.
    public static function failure(Request $request): void
    {
        if ($state = self::from($request)) {
            $state->failed = true;
        }
    }

    public static function success(Request $request): void
    {
        if ($state = self::from($request)) {
            $state->succeeded = true;
        }
    }

    public function outcome(int $status): string
    {
        if ($status >= 400) {
            return 'failure';
        }
        if ($this->failed) {
            return $this->succeeded ? 'partial' : 'failure';
        }

        return 'success';
    }
}