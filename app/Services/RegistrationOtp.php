<?php

namespace App\Services;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class RegistrationOtp
{
    public function __construct(private RegistrationOtpDelivery $delivery)
    {
    }

    public function normalizeContact(string $contact): array
    {
        $contact = trim($contact);
        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            return ['channel' => 'email', 'contact' => mb_strtolower($contact)];
        }
        $phones = PhoneNumberUtil::getInstance();
        try {
            $number = $phones->parse($contact, 'MW');
            if ($phones->isValidNumberForRegion($number, 'MW')) {
                return ['channel' => 'sms', 'contact' => $phones->format($number, PhoneNumberFormat::E164)];
            }
        } catch (NumberParseException $e) {
            // Both email and phone validation failed.
        }
        throw ValidationException::withMessages(['contact' => __('registration.invalid_contact')]);
    }

    public function state(Session $session): ?array
    {
        $token = $session->get('registration_otp_token');

        return $token ? Cache::get('registration-otp:'.$token) : null;
    }

    public function verifiedContact(Session $session): ?array
    {
        $state = $this->state($session);
        if (!$state || empty($state['verified_until']) || $state['verified_until'] <= now()->timestamp) {
            return null;
        }

        return ['channel' => $state['channel'], 'contact' => $state['contact']];
    }

    public function locked(Session $session, callable $callback)
    {
        if (!$session->has('registration_otp_token')) {
            $session->put('registration_otp_token', Str::random(64));
        }
        $result = Cache::lock('registration-otp-lock:'.$session->get('registration_otp_token'), 120)->get($callback);
        if ($result === false) {
            throw ValidationException::withMessages(['contact' => __('registration.busy')]);
        }

        return $result;
    }

    public function send(Session $session, string $contact, string $ip): void
    {
        $normalized = $this->normalizeContact($contact);
        if (!in_array($normalized['channel'], $this->delivery->channels(), true)) {
            throw ValidationException::withMessages(['contact' => __('registration.unavailable')]);
        }
        app(RegistrationContacts::class)->assertAvailable($normalized['channel'], $normalized['contact']);
        $this->locked($session, function () use ($session, $normalized, $ip) {
            $state = $this->state($session);
            if ($state && $state['sent_at'] + 60 > now()->timestamp) {
                throw ValidationException::withMessages(['contact' => __('registration.resend_wait', [
                    'seconds' => $state['sent_at'] + 60 - now()->timestamp,
                ])]);
            }
            $target = 'registration-send-target:'.hash('sha256', $normalized['contact']);
            $result = Cache::lock($target.':lock', 60)->get(function () use ($session, $normalized, $ip, $target) {
                if (Cache::get($target.':next-send-at', 0) > now()->timestamp) {
                    throw ValidationException::withMessages(['contact' => __('registration.rate_limit')]);
                }
                $this->limit($target, 5, 3600);
                $this->limit('registration-send-ip:'.hash('sha256', $ip), 10, 3600);
                Cache::put($target.':next-send-at', now()->addSeconds(60)->timestamp, 60);
                $code = (string) random_int(100000, 999999);
                Cache::forget('registration-otp:'.$session->get('registration_otp_token'));
                try {
                    $this->delivery->send($normalized['channel'], $normalized['contact'], $code);
                } catch (\Exception $e) {
                    // Provider exceptions can contain credentials, recipients, and the OTP.
                    Log::error('Registration OTP delivery failed.', [
                        'channel' => $normalized['channel'], 'exception_type' => get_class($e),
                    ]);
                    throw ValidationException::withMessages(['contact' => __('registration.delivery_failed')]);
                }
                Cache::put('registration-otp:'.$session->get('registration_otp_token'), $normalized + [
                    'hash' => Hash::make($code),
                    'sent_at' => now()->timestamp,
                    'expires_at' => now()->addMinutes(5)->timestamp,
                    'attempts' => 0,
                    'verified_until' => null,
                ], 300);

                return true;
            });
            if ($result === false) {
                throw ValidationException::withMessages(['contact' => __('registration.busy')]);
            }

            return true;
        });
    }

    public function verify(Session $session, string $code, string $ip): void
    {
        $this->locked($session, function () use ($session, $code, $ip) {
            $this->limit('registration-verify-ip:'.hash('sha256', $ip), 30, 600);
            $state = $this->state($session);
            if (!$state || $state['expires_at'] <= now()->timestamp || !empty($state['verified_until'])) {
                throw ValidationException::withMessages(['otp' => __('registration.expired')]);
            }
            app(RegistrationContacts::class)->assertAvailable($state['channel'], $state['contact']);
            if ($state['attempts'] >= 5) {
                throw ValidationException::withMessages(['otp' => __('registration.attempts')]);
            }
            ++$state['attempts'];
            $valid = Hash::check($code, $state['hash']);
            if ($valid) {
                $state['verified_until'] = now()->addMinutes(30)->timestamp;
                unset($state['hash']);
            }
            Cache::put('registration-otp:'.$session->get('registration_otp_token'), $state,
                $valid ? 1800 : max(1, $state['expires_at'] - now()->timestamp));
            if (!$valid) {
                throw ValidationException::withMessages(['otp' => __($state['attempts'] >= 5 ? 'registration.attempts' : 'registration.invalid_code')]);
            }
            $session->regenerate(true);

            return true;
        });
    }

    public function consume(Session $session): void
    {
        Cache::forget('registration-otp:'.$session->get('registration_otp_token'));
        $session->forget('registration_otp_token');
    }

    private function limit(string $key, int $attempts, int $seconds): void
    {
        if (RateLimiter::tooManyAttempts($key, $attempts)) {
            throw ValidationException::withMessages(['contact' => __('registration.rate_limit')]);
        }
        RateLimiter::hit($key, $seconds);
    }
}
