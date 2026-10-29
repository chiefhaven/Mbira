<?php

namespace Modules\Connector\Support;

use App\User;
use Illuminate\Contracts\Container\Container;
use Laravel\Passport\Bridge\RefreshTokenRepository;
use Laravel\Passport\Bridge\User as PassportUser;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Passport;
use Laravel\Passport\TokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface;

class DesktopTokenIssuer
{
    private $app;
    private $login;

    public function __construct(Container $app, DesktopPasswordLogin $login)
    {
        $this->app = $app;
        $this->login = $login;
    }

    public function issueToken(ServerRequestInterface $request, User $selected, string $access)
    {
        $body = $request->getParsedBody();
        $users = new class($this->login, $selected, $access, $body) implements UserRepositoryInterface
        {
            private $login;
            private $id;
            private $username;
            private $access;
            private $body;

            public function __construct(DesktopPasswordLogin $login, User $selected, string $access, array $body)
            {
                $this->login = $login;
                $this->id = (string) $selected->getKey();
                $this->username = $selected->username;
                $this->access = $access;
                $this->body = $body;
            }

            public function getUserEntityByUserCredentials($username, $password, $grantType, ClientEntityInterface $clientEntity)
            {
                if ($grantType !== 'password' || $username !== $this->username
                    || $password !== $this->body['password']
                    || (string) $clientEntity->getIdentifier() !== $this->body['client_id']) {
                    return null;
                }

                // Recheck client, tenant, unique password and permissions at the
                // grant boundary, but NEVER let a global username lookup choose
                // the token subject. Concurrent username reassignment fails closed.
                $user = $this->login->resolve($this->body['client_id'], $this->body['client_secret'], $password, $this->access);
                if (! $user || (string) $user->getKey() !== $this->id || $user->username !== $this->username) {
                    return null;
                }

                return new PassportUser($this->id);
            }
        };

        // Replacing a grant on Passport's singleton would leak this identity into
        // later requests (especially long-lived workers). Modify only a clone.
        $server = clone $this->app->make(AuthorizationServer::class);
        $grant = new PasswordGrant($users, $this->app->make(RefreshTokenRepository::class));
        $grant->setRefreshTokenTTL(Passport::refreshTokensExpireIn());
        $server->enableGrantType($grant, Passport::tokensExpireIn());

        return (new AccessTokenController($server, $this->app->make(TokenRepository::class)))->issueToken($request);
    }
}