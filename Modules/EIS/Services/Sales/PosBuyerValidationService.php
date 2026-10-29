<?php

namespace Modules\EIS\Services\Sales;

use App\Contact;
use App\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Http\EisHealthService;
use Modules\EIS\Services\Utilities\EisValidationClient;
use Modules\EIS\Services\EisSubscriptionAccess;

class PosBuyerValidationService
{
    public function __construct(
        private EisValidationClient $client,
        protected EisHealthService $healthService
    ) {}

    /**
     * Validate buyer information for POS sales.
     *
     * Rules:
     * - EIS offline: Allow ordinary sales.
     * - EIS online: Validate buyer TIN and authorization requirements.
     * - Authorization required but missing: Block sale.
     * - Authorization not required: Allow sale.
     * - Relief sales: Require successful EIS validation.
     *
     * Returns only server-validated VAT5 certificate details.
     * Never accepts a client-provided validity flag.
     */
    public function validate(
        int $businessId,
        array $input,
        ?Transaction $existing = null
    ): ?array {

        /*
        |--------------------------------------------------------------------------
        | 1. Verify Transaction Ownership
        |--------------------------------------------------------------------------
        */

        if (
            $existing &&
            (int) $existing->business_id !== $businessId
        ) {
            throw ValidationException::withMessages([
                'contact_id' => 'Sale does not belong to this business.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Determine Relief Status
        |--------------------------------------------------------------------------
        */

        $relief = array_key_exists('is_relief', $input)
            ? filter_var(
                $input['is_relief'],
                FILTER_VALIDATE_BOOLEAN
            )
            : (bool) ($existing->is_relief ?? false);

        /*
        |--------------------------------------------------------------------------
        | 3. Load Buyer
        |--------------------------------------------------------------------------
        */

        $contactId = $input['contact_id']
            ?? $existing?->contact_id;

        $contact = Contact::where('business_id', $businessId)
            ->find($contactId);

        if (! $contact) {
            throw ValidationException::withMessages([
                'contact_id' => 'Select a buyer from this business.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Get Buyer TIN and Authorization Code
        |--------------------------------------------------------------------------
        */

        $tin = trim((string) $contact->tax_number);

        $code = trim((string) $contact->authorization_code);

        if (!app(EisSubscriptionAccess::class)->allows($businessId)) {
            if ($relief) {
                throw ValidationException::withMessages([
                    'is_relief' => 'The active package does not include EIS validation for relief sales.',
                ]);
            }

            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Load Active EIS Settings
        |--------------------------------------------------------------------------
        */

        $locationId = $input['location_id'] ?? $existing?->location_id;
        if (!$locationId && EisSetting::where('business_id', $businessId)->exists()) {
            throw ValidationException::withMessages(['location_id' => 'Select a sale location before EIS buyer validation.']);
        }
        $hasEis = EisSetting::where('business_id', $businessId)->where('location_id', $locationId)->exists();
        try {
            $settings = $hasEis
                ? \Modules\EIS\Services\Terminal\TerminalContext::forLocation($businessId, (int) $locationId, true)
                : null;
        } catch (\InvalidArgumentException | \Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw ValidationException::withMessages(['location_id' => $e->getMessage()]);
        }

        /*
         * Businesses without active EIS settings
         * can continue normal POS sales.
         */

        if (! $settings) {

            if ($relief) {
                throw ValidationException::withMessages([
                    'is_relief' =>
                        'Relief sales require active EIS settings.',
                ]);
            }

            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Prepare VAT5 Certificate Details
        |--------------------------------------------------------------------------
        */

        $certificate = null;

        if ($relief) {

            if ($tin === '') {
                throw ValidationException::withMessages([
                    'buyerTIN' =>
                        'A buyer TIN is required for a relief sale.',
                ]);
            }

            /*
             * Only reuse certificate details if
             * the buyer has not changed.
             */

            $stored = (
                $existing &&
                (int) $contact->id === (int) $existing->contact_id
            )
                ? ($existing->vat5_certificate_details ?? [])
                : [];

            $certificate = [
                'projectNumber' =>
                    $input['vat5_project_number']
                    ?? $stored['projectNumber']
                    ?? null,

                'certificateNumber' =>
                    $input['vat5_certificate_number']
                    ?? $stored['certificateNumber']
                    ?? null,

                'quantity' =>
                    $input['vat5_quantity']
                    ?? $stored['quantity']
                    ?? null,
            ];

            Validator::make($certificate, [
                'projectNumber' => 'required|string|max:255',
                'certificateNumber' => 'required|string|max:255',
                'quantity' => 'required|numeric|gt:0',
            ])->validate();

            $certificate['quantity'] = (float) $certificate['quantity'];
        }

        /*
        |--------------------------------------------------------------------------
        | 7. Determine Whether EIS Validation Is Required
        |--------------------------------------------------------------------------
        */

        $requiresApiValidation = (
            $tin !== '' ||
            $code !== '' ||
            $certificate !== null
        );

        /*
         * No buyer TIN, authorization code,
         * or VAT5 certificate.
         *
         * No external buyer validation required.
         */

        if (! $requiresApiValidation) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | 8. Check EIS Availability
        |--------------------------------------------------------------------------
        */

        $isOnline = $this->healthService->isOnline(
            $businessId,
            $settings->jwt_token
        );

        /*
        |--------------------------------------------------------------------------
        | 9. Handle EIS Offline
        |--------------------------------------------------------------------------
        |
        | Ordinary sales are allowed when EIS is unavailable.
        |
        | This includes:
        | - Buyer has TIN but no authorization code.
        | - Buyer has TIN and authorization code.
        | - Buyer has no TIN.
        |
        | Relief sales are blocked because certificate
        | validation cannot be completed.
        |
        */

        if (! $isOnline) {

            Log::warning(
                'EIS unavailable. Allowing ordinary POS sale.',
                [
                    'business_id' => $businessId,
                    'contact_id' => $contact->id,
                    'has_tin' => $tin !== '',
                    'has_authorization_code' => $code !== '',
                    'is_relief' => $relief,
                ]
            );

            /*
             * VAT5 relief requires online validation.
             */

            if ($relief) {
                throw ValidationException::withMessages([
                    'is_relief' =>
                        'EIS is currently unavailable. Relief sales require online certificate validation.',
                ]);
            }

            /*
             * Allow ordinary POS sale.
             */

            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | 10. Validate Buyer TIN
        |--------------------------------------------------------------------------
        */

        if ($tin !== '') {

            $requirement = $this->client
                ->checkTinAuthorizationRequirement(
                    $settings,
                    $tin
                );

            /*
             * Confirm TIN exists and that the response
             * contains a valid authorization requirement.
             */

            if (
                ($requirement['tinExists'] ?? null) !== true ||
                ! is_bool(
                    $requirement['requiresAuthorizationCode'] ?? null
                ) ||
                ($requirement['tin'] ?? null) !== $tin
            ) {
                throw ValidationException::withMessages([
                    'buyerTIN' =>
                        'EIS could not confirm the buyer TIN.',
                ]);
            }

            /*
             * Authorization code is only mandatory
             * when EIS explicitly requires it.
             */

            $authorizationRequired =
                $requirement['requiresAuthorizationCode'];

            if (
                $authorizationRequired === true &&
                $code === ''
            ) {
                throw ValidationException::withMessages([
                    'buyerAuthorizationCode' =>
                        'This buyer TIN requires an EIS authorization code. Edit the customer to add it.',
                ]);
            }

            /*
             * If authorization is not required,
             * continue even when the code is empty.
             */
        }

        /*
        |--------------------------------------------------------------------------
        | 11. Validate Authorization Code
        |--------------------------------------------------------------------------
        |
        | Validate only when the buyer has supplied a code.
        |
        */

        if ($code !== '') {

            $this->client->validateAuthorizationCode(
                $settings,
                $code
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 12. Validate VAT5 Certificate
        |--------------------------------------------------------------------------
        */

        if ($certificate !== null) {

            $this->client->validateVat5Certificate(
                $settings,
                $certificate
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 13. Return Validated Certificate
        |--------------------------------------------------------------------------
        */

        return $certificate;
    }

    /**
     * Ensure buyer details have not changed
     * during EIS validation.
     */
    public function assertSaleUnchanged(
        Transaction $validated,
        Transaction $locked
    ): void {

        if (
            (int) $validated->contact_id !==
                (int) $locked->contact_id ||

            (bool) $validated->is_relief !==
                (bool) $locked->is_relief ||

            $validated->vat5_certificate_details !==
                $locked->vat5_certificate_details
        ) {
            throw ValidationException::withMessages([
                'is_relief' =>
                    'The buyer or relief details changed during validation. Reload the sale and retry.',
            ]);
        }
    }
}