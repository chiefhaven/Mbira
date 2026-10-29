<?php

namespace Modules\Connector\Support\Audit;

use Monolog\Formatter\JsonFormatter;

class SanitizeAuditLog
{
    public function __invoke($logger): void
    {
        $sanitize = function (array $record): array {
            $record['message'] = 'connector_request_completed';
            $record['context'] = AuditMetadata::sanitize($record['context']);
            $record['extra'] = [];

            return $record;
        };

        $monolog = $logger->getLogger();
        foreach ($monolog->getProcessors() as $ignored) {
            $monolog->popProcessor();
        }
        $monolog->pushProcessor($sanitize);
        foreach ($monolog->getHandlers() as $handler) {
            // Handler processing occurs AFTER logger processors. Sanitize here as well,
            // so even a later ambient logger processor cannot leak context or extra.
            foreach ($handler->getProcessors() as $ignored) {
                $handler->popProcessor();
            }
            $handler->pushProcessor($sanitize);
            $handler->setFormatter(new JsonFormatter());
        }
    }
}