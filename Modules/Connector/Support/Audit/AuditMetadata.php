<?php

namespace Modules\Connector\Support\Audit;

final class AuditMetadata
{
    public static function id($value): ?string
    {
        if ((is_int($value) || is_string($value)) && preg_match('/^[1-9][0-9]{0,19}$/D', (string) $value)) {
            return (string) $value;
        }

        return null;
    }

    public static function action($value): ?string
    {
        // Only a static controller class/method, never URI, route parameters or route name.
        if (is_string($value) && strlen($value) <= 240 && preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*@[A-Za-z_][A-Za-z0-9_]*$/D', $value)) {
            return $value;
        }

        return null;
    }

    public static function sanitize(array $context): array
    {
        $safe = [];
        $uuid = $context['request_id'] ?? null;
        if (is_string($uuid) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $uuid)) {
            $safe['request_id'] = $uuid;
        }
        foreach (['actor_id', 'business_id'] as $key) {
            $safe[$key] = self::id($context[$key] ?? null);
        }
        $safe['action'] = self::action($context['action'] ?? null);
        $status = $context['http_status'] ?? null;
        if (is_int($status) && $status >= 100 && $status <= 599) {
            $safe['http_status'] = $status;
        }
        if (in_array($context['outcome'] ?? null, ['success', 'failure', 'partial'], true)) {
            $safe['outcome'] = $context['outcome'];
        }
        $duration = $context['duration_ms'] ?? null;
        if ((is_int($duration) || is_float($duration)) && is_finite((float) $duration) && $duration >= 0) {
            $safe['duration_ms'] = round($duration, 3);
        }

        return $safe;
    }
}