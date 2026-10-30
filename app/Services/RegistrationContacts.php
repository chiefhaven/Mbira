<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class RegistrationContacts
{
    public function phone(string $value, string $field = 'mobile'): string
    {
        $phones = PhoneNumberUtil::getInstance();
        try {
            $phone = $phones->parse(trim($value), 'MW');
            if ($phones->isValidNumberForRegion($phone, 'MW')) {
                return $phones->format($phone, PhoneNumberFormat::E164);
            }
        } catch (NumberParseException $e) {
            // Report malformed and foreign numbers through the same field error.
        }
        throw ValidationException::withMessages([$field => __('registration.malawi_phone_required')]);
    }

    public function assertAvailable(string $channel, string $contact, string $field = 'contact'): void
    {
        if ($channel === 'email') {
            $exists = DB::table('users')->whereRaw('LOWER(TRIM(email)) = ?', [mb_strtolower(trim($contact))])->exists();
        } else {
            $normalized = $this->phone($contact, $field);
            $national = substr($normalized, 4);
            $variants = [substr($normalized, 1), '0'.$national, $national, '00'.substr($normalized, 1)];
            $exists = false;
            foreach (['users' => ['contact_number', 'alt_number'], 'business_locations' => ['mobile', 'alternate_number']] as $table => $columns) {
                $query = DB::table($table)->where(function ($query) use ($columns, $variants) {
                    foreach ($columns as $column) {
                        $expression = $column;
                        foreach (['+', ' ', '-', '(', ')', '.'] as $character) {
                            $expression = "REPLACE($expression, '$character', '')";
                        }
                        $query->orWhereIn(DB::raw($expression), $variants);
                    }
                });
                if ($query->exists()) {
                    $exists = true;
                    break;
                }
            }
        }
        if ($exists) {
            throw ValidationException::withMessages([$field => __('registration.contact_registered')]);
        }
    }

    public function registration(Request $request, callable $next)
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'mobile' => 'required|string|max:50',
            'alternate_number' => 'nullable|string|max:50',
        ]);
        $contacts = [
            'email' => ['email', mb_strtolower(trim($request->input('email')))],
            'mobile' => ['sms', $this->phone($request->input('mobile'))],
        ];
        if ($request->filled('alternate_number')) {
            $contacts['alternate_number'] = ['sms', $this->phone($request->input('alternate_number'), 'alternate_number')];
        }
        $keys = array_unique(array_map(fn ($contact) => 'registration-contact:'.hash('sha256', $contact[0].':'.$contact[1]), $contacts));
        sort($keys);
        $proceed = function () use ($request, $contacts, $next) {
            foreach ($contacts as $field => [$channel, $contact]) {
                $this->assertAvailable($channel, $contact, $field);
                $request->merge([$field => $contact]);
            }

            return $next();
        };
        foreach (array_reverse($keys) as $key) {
            $callback = $proceed;
            $proceed = function () use ($key, $callback) {
                $result = Cache::lock($key, 120)->get($callback);
                if ($result === false) {
                    throw ValidationException::withMessages(['contact' => __('registration.busy')]);
                }

                return $result;
            };
        }

        return $proceed();
    }
}
