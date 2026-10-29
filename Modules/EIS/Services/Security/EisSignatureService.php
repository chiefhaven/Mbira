<?php
namespace Modules\EIS\Services\Security;

class EisSignatureService
{
    public function make(array $payload, string $secretKey, int $timestamp): string
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha256', $body . $timestamp, $secretKey);
    }
}