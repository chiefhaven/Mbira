<?php

namespace App\Services;

use App\System;
use App\Utils\BusinessUtil;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class RegistrationOtpDelivery
{
    public function __construct(private BusinessUtil $businessUtil)
    {
    }

    public function smsSettings(): array
    {
        $stored = System::getProperty('registration_sms_settings');

        return array_replace(
            $this->businessUtil->defaultSmsSettings(),
            $stored ? json_decode(Crypt::decryptString($stored), true, 512, JSON_THROW_ON_ERROR) : []
        );
    }

    public function channels(): array
    {
        $channels = [];
        if (System::getProperty('registration_otp_email_enabled') === '1') {
            $channels[] = 'email';
        }
        if (System::getProperty('registration_otp_sms_enabled') === '1') {
            $channels[] = 'sms';
        }

        return $channels;
    }

    public function settingsRules(): array
    {
        $rules = [
            'registration_otp_email_enabled' => 'required|boolean',
            'registration_otp_sms_enabled' => 'required|boolean',
            'sms_settings' => 'required|array',
            'sms_settings.sms_service' => ['required', Rule::in(['nexmo', 'twilio', 'other'])],
            'sms_settings.request_method' => ['required', Rule::in(['get', 'post'])],
            'sms_settings.data_parameter_type' => ['required', Rule::in(['form-data', 'json'])],
            'sms_settings.send_to_param_type' => ['nullable', Rule::in(['string', 'array'])],
            'sms_settings.url' => 'nullable|url:http,https|max:2000',
        ];
        foreach (['nexmo_key', 'nexmo_secret', 'nexmo_from', 'twilio_sid', 'twilio_token', 'twilio_from', 'send_to_param_name', 'msg_param_name'] as $field) {
            $rules['sms_settings.'.$field] = 'nullable|string|max:500';
        }
        for ($i = 1; $i <= 10; $i++) {
            $rules['sms_settings.param_'.$i] = 'nullable|string|max:500';
            $rules['sms_settings.param_val_'.$i] = 'nullable|string|max:2000';
        }
        for ($i = 1; $i <= 3; $i++) {
            $rules['sms_settings.header_'.$i] = 'nullable|string|max:500';
            $rules['sms_settings.header_val_'.$i] = 'nullable|string|max:2000';
        }

        return $rules;
    }

    public function validateConfiguration(array $settings, array $mailSettings = []): void
    {
        if (!empty($settings['registration_otp_email_enabled'])) {
            $this->assertEmailConfigured($mailSettings);
        }
        if (!empty($settings['registration_otp_sms_enabled'])) {
            $this->assertSmsConfigured($settings['sms_settings']);
        }
    }

    public function saveSettings(array $settings): void
    {
        System::addProperty('registration_sms_settings', Crypt::encryptString(json_encode($settings['sms_settings'], JSON_THROW_ON_ERROR)));
        System::addProperty('registration_otp_email_enabled', (string) $settings['registration_otp_email_enabled']);
        System::addProperty('registration_otp_sms_enabled', (string) $settings['registration_otp_sms_enabled']);
    }

    public function send(string $channel, string $contact, string $code): void
    {
        if (!in_array($channel, $this->channels(), true)) {
            throw new \RuntimeException('Registration OTP channel is disabled.');
        }
        $message = __('registration.message', ['app' => config('app.name'), 'code' => $code]);
        if ($channel === 'email') {
            $this->assertEmailConfigured();
            Mail::raw($message, function ($mail) use ($contact) {
                $mail->to($contact)->subject(__('registration.email_subject', ['app' => config('app.name')]));
            });

            return;
        }

        $settings = $this->smsSettings();
        $this->assertSmsConfigured($settings);
        $result = $this->businessUtil->sendSms([
            'sms_settings' => $settings,
            'mobile_number' => $contact,
            'sms_body' => $message,
            'require_delivery_confirmation' => true,
        ]);
        if ($result === false) {
            throw new \RuntimeException('Registration SMS provider is not configured.');
        }
        if (($settings['sms_service'] ?? 'other') === 'other') {
            $this->assertCustomSmsAccepted($result);
        }
    }

    private function assertCustomSmsAccepted($response): void
    {
        if (!$response instanceof \Psr\Http\Message\ResponseInterface ||
            $response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new \RuntimeException('Registration SMS gateway did not accept the request.');
        }

        $body = trim((string) $response->getBody());
        $contentType = strtolower($response->getHeaderLine('Content-Type'));
        if (str_contains($contentType, 'text/html') || str_starts_with($body, '<')) {
            throw new \RuntimeException('Registration SMS gateway returned an HTML page instead of an API response.');
        }
        if (str_contains($contentType, 'json') || str_starts_with($body, '{') || str_starts_with($body, '[')) {
            $result = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $status = is_array($result) ? ($result['status'] ?? null) : null;
            if ((is_string($status) && in_array(strtolower($status), ['error', 'failed', 'failure', 'fail'], true)) ||
                (is_array($result) && ($result['success'] ?? null) === false)) {
                throw new \RuntimeException('Registration SMS gateway rejected the message.');
            }
        }
    }

    private function assertEmailConfigured(array $mailSettings = []): void
    {
        // Log/array/fallback mailers must never report an undelivered OTP as sent.
        $mailer = $mailSettings['MAIL_MAILER'] ?? config('mail.default');
        $transport = config('mail.mailers.'.$mailer.'.transport', $mailer);
        if (!in_array($transport, ['smtp', 'sendmail', 'ses', 'mailgun', 'postmark'], true) ||
            !filter_var($mailSettings['MAIL_FROM_ADDRESS'] ?? config('mail.from.address'), FILTER_VALIDATE_EMAIL) ||
            ($transport === 'smtp' && !($mailSettings['MAIL_HOST'] ?? config('mail.mailers.'.$mailer.'.host')))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'registration_otp_email_enabled' => __('registration.unavailable'),
            ]);
        }
    }

    private function assertSmsConfigured(array $settings): void
    {
        $required = match ($settings['sms_service'] ?? 'other') {
            'nexmo' => ['nexmo_key', 'nexmo_secret', 'nexmo_from'],
            'twilio' => ['twilio_sid', 'twilio_token', 'twilio_from'],
            default => ['url', 'send_to_param_name', 'msg_param_name'],
        };
        foreach ($required as $field) {
            if (empty($settings[$field])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'sms_settings.'.$field => __('validation.required', ['attribute' => $field]),
                ]);
            }
        }
    }
}
