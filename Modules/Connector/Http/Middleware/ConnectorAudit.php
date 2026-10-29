<?php

namespace Modules\Connector\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Modules\Connector\Support\Audit\AuditState;

class ConnectorAudit
{
    private $config;

    public function __construct(Repository $config)
    {
        $this->config = $config;
    }

    public function handle(Request $request, Closure $next)
    {
        $path = trim($request->path(), '/');
        $tokenPath = $this->config->get('connector.audit_oauth_token_path');
        if ($tokenPath === null) {
            $tokenPath = trim($this->config->get('passport.path', 'oauth'), '/').'/token';
        }
        $tokenRequest = $path === trim($tokenPath, '/');
        if ($tokenRequest || $path === 'connector/api' || strpos($path, 'connector/api/') === 0) {
            $request->attributes->set(AuditState::ATTRIBUTE, new AuditState($tokenRequest));
        }

        // Final logging and response headers belong to RequestHandled, not a finally block:
        // the kernel has not rendered thrown exceptions while this middleware unwinds.
        return $next($request);
    }
}