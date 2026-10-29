<?php

namespace Modules\Connector\Support;

use App\Business;
use App\User;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Hashing\Hasher;
use Laravel\Passport\Bridge\ClientRepository as BridgeClients;
use Laravel\Passport\ClientRepository;

class DesktopPasswordLogin
{
    public const MAX_CANDIDATES = 1000;

    private $clients;
    private $bridge;
    private $hasher;
    private $config;

    public function __construct(ClientRepository $clients, BridgeClients $bridge, Hasher $hasher, Repository $config)
    {
        $this->clients = $clients;
        $this->bridge = $bridge;
        $this->hasher = $hasher;
        $this->config = $config;
    }

    public function resolve(string $clientId, string $clientSecret, string $password, string $access): ?User
    {
        $client = $this->clients->findActive($clientId);
        // Public, ownerless, revoked and non-password clients can never select a tenant.
        if (! $client || $client->revoked || ! $client->password_client || ! $client->confidential()
            || ! $client->user_id || ! $this->bridge->validateClient($clientId, $clientSecret, 'password')) {
            return null;
        }

        // Passport must authenticate App\User, not a contact or another provider/model.
        $provider = $client->provider ?: $this->config->get('auth.guards.api.provider');
        if (! is_string($provider) || $this->config->get('auth.providers.'.$provider.'.model') !== User::class
            || ! in_array($access, ['admin', 'cashier'], true)) {
            return null;
        }

        $owner = User::query()->where('user_type', 'user')->where('status', 'active')
            ->where('allow_login', 1)->find($client->user_id);
        if (! $owner || ! $owner->business_id
            || ! Business::query()->where('is_active', 1)->whereKey($owner->business_id)->exists()) {
            return null;
        }

        // User's SoftDeletes scope applies to both owner and candidates. Never search
        // another business, and never filter by role before detecting ambiguity.
        $candidates = User::query()->where('business_id', $owner->business_id)
            ->where('user_type', 'user')->where('status', 'active')->where('allow_login', 1)
            ->orderBy('id')->limit(self::MAX_CANDIDATES + 1)->get();
        if ($candidates->count() > self::MAX_CANDIDATES) {
            return null;
        }

        $match = null;
        foreach ($candidates as $candidate) {
            if ($this->hasher->check($password, $candidate->getAuthPassword())) {
                if ($match !== null) {
                    return null;
                }
                $match = $candidate;
            }
        }
        if (! $match || ! is_string($match->username) || $match->username === '') {
            return null;
        }

        // App\User::findForPassport is an existing global username lookup. Require
        // snapshot uniqueness and the same identity. DesktopTokenIssuer separately
        // pins the token subject by ID to close the later username-handoff race.
        // This is a username check AFTER tenant-scoped password matching, not a
        // global password search; it does not change traditional OAuth behavior.
        if (User::query()->where('username', $match->username)->limit(2)->get()->count() !== 1) {
            return null;
        }
        $passportUser = (new User)->findForPassport($match->username);
        if (! $passportUser || (string) $passportUser->getKey() !== (string) $match->getKey()) {
            return null;
        }

        $authorized = $access === 'admin'
            ? ($match->hasRole('Admin#'.$owner->business_id) || $match->can('superadmin'))
            : $match->can('sell.create');

        return $authorized ? $match : null;
    }
}