<?php

// In-memory cache, sessions and SQLite; fake delivery only.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Http\Middleware\EnsureRegistrationContactVerified;
use App\Services\RegistrationOtp;
use App\Services\RegistrationOtpDelivery;
use App\System;
use Carbon\Carbon;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\RateLimiter;
use Illuminate\Config\Repository;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Application;
use Illuminate\Hashing\HashManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

$app = new Application(dirname(__DIR__, 2));
$config = new Repository([
    'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]],
    'hashing' => ['driver' => 'bcrypt', 'bcrypt' => ['rounds' => 4]],
    'constants' => ['allow_registration' => true],
    'mail' => ['default' => 'smtp', 'mailers' => ['smtp' => ['transport' => 'smtp', 'host' => 'smtp.example.test']], 'from' => ['address' => 'sender@example.test']],
]);
$app->instance('config', $config);
$app->instance('cache', new CacheManager($app));
$app->bind('cache.store', fn () => $app['cache']->store());
$app->instance(RateLimiter::class, new RateLimiter($app['cache']->store()));
$app->instance('hash', new HashManager($app));
$app->instance('encrypter', new Encrypter(random_bytes(32), 'AES-256-CBC'));
$loader = new ArrayLoader();
$loader->addMessages('en', 'registration', require dirname(__DIR__, 2).'/lang/en/registration.php');
$translator = new Translator($loader, 'en');
$app->instance('translator', $translator);
$app->instance('validator', new Factory($translator, $app));
Request::macro('validate', function (array $rules) {
    return app('validator')->make($this->all(), $rules)->validate();
});
$logs = [];
$logger = Mockery::mock(Psr\Log\LoggerInterface::class);
$logger->shouldReceive('error')->andReturnUsing(function ($message, $context) use (&$logs) {
    $logs[] = [$message, $context];
});
$app->instance('log', $logger);
$capsule = new Capsule($app);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$app->instance('db', $capsule->getDatabaseManager());
$capsule->getConnection()->getSchemaBuilder()->create('system', function (Blueprint $table) {
    $table->increments('id');
    $table->string('key')->unique();
    $table->text('value')->nullable();
});
$capsule->schema()->create('users', function (Blueprint $table) {
    $table->increments('id');
    $table->string('email')->nullable();
    $table->string('contact_number')->nullable();
    $table->string('alt_number')->nullable();
    $table->softDeletes();
});
$capsule->schema()->create('business_locations', function (Blueprint $table) {
    $table->increments('id');
    $table->string('mobile')->nullable();
    $table->string('alternate_number')->nullable();
});
Facade::setFacadeApplication($app);
Facade::clearResolvedInstances();
$routes = new RouteCollection();
foreach (['business.getRegister' => 'business/register', 'business.verifyContact' => 'business/register/verify'] as $name => $uri) {
    $routes->add((new Route('GET', $uri, []))->name($name));
}
$request = Request::create('http://localhost/business/register');
$url = new UrlGenerator($routes, $request);
$app->instance('url', $url);
$redirector = new Redirector($url);
$app->instance('redirect', $redirector);
$delivery = new class(new App\Utils\BusinessUtil()) extends RegistrationOtpDelivery {
    public array $sent = [];
    public bool $fail = false;
    public array $enabled = ['email', 'sms'];

    public function channels(): array
    {
        return $this->enabled;
    }

    public function send(string $channel, string $contact, string $code): void
    {
        if ($this->fail) {
            throw new RuntimeException('DO NOT LOG: secret-code-recipient');
        }
        $this->sent[] = compact('channel', 'contact', 'code');
    }
};
$otp = new RegistrationOtp($delivery);
$checks = 0;
$check = function (bool $condition, string $message) use (&$checks) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
};
$reject = function (callable $call, string $field, ?string $message = null) use ($check) {
    try {
        $call();
        throw new RuntimeException('Expected validation failure: '.$field);
    } catch (ValidationException $e) {
        $check(isset($e->errors()[$field]), 'Expected error field '.$field);
        if ($message !== null) {
            $check($e->errors()[$field][0] === __($message), 'Expected error message '.$message);
        }
    }
};
$session = function () {
    $store = new Store('otp-check', new ArraySessionHandler(120));
    $store->start();

    return $store;
};
$code = fn () => $delivery->sent[array_key_last($delivery->sent)]['code'];
$reset = function () use ($delivery) {
    Cache::flush();
    Carbon::setTestNow('2026-10-30 10:00:00');
    $delivery->fail = false;
    $delivery->enabled = ['email', 'sms'];
};
$reset();
$check($otp->normalizeContact(' Person@Example.test ') === ['channel' => 'email', 'contact' => 'person@example.test'], 'Normalize email.');
$check($otp->normalizeContact('0991 234 567') === ['channel' => 'sms', 'contact' => '+265991234567'], 'Normalize Malawi local number.');
$check($otp->normalizeContact('+265 991 234 567')['contact'] === '+265991234567', 'Normalize international number.');
$reject(fn () => $otp->normalizeContact('not-a-contact'), 'contact');
$reject(fn () => $otp->normalizeContact('+260971234567'), 'contact');
$s = $session();
$check($otp->verifiedContact($s) === null, 'No verification without OTP.');
$otp->send($s, 'person@example.test', '127.0.0.1');
$sentCode = $code();
$check((bool) preg_match('/^[0-9]{6}$/', $sentCode), 'Generate six-digit code.');
$state = $otp->state($s);
$check(Hash::check($sentCode, $state['hash']) && $state['hash'] !== $sentCode, 'Only a hash is cached.');
$check(!str_contains(json_encode($s->all()), $sentCode), 'Code is not stored in the session.');
$check($state['expires_at'] - $state['sent_at'] === 300, 'Exactly five-minute expiry.');
$reject(fn () => $otp->send($s, 'other@example.test', '127.0.0.1'), 'contact');
$reject(fn () => $otp->send($session(), 'person@example.test', '127.0.0.2'), 'contact', 'registration.rate_limit');
$wrong = $sentCode === '100000' ? '100001' : '100000';
$reject(fn () => $otp->verify($s, $wrong, '127.0.0.1'), 'otp', 'registration.invalid_code');
$oldSessionId = $s->getId();
$otp->verify($s, $sentCode, '127.0.0.1');
$check($s->getId() !== $oldSessionId, 'Regenerate session after verification.');
$check($otp->verifiedContact($s) === ['channel' => 'email', 'contact' => 'person@example.test'], 'Verified identity is bound.');
$check($otp->verifiedContact($session()) === null, 'A separate browser session cannot use the proof.');
$replayedSession = $session();
$replayedSession->put('registration_otp_token', $s->get('registration_otp_token'));
$check(!isset($otp->state($s)['hash']), 'Remove hash after verification.');
$reject(fn () => $otp->verify($s, $sentCode, '127.0.0.1'), 'otp');
$otp->consume($s);
$check($otp->verifiedContact($s) === null && !$s->has('registration_otp_token'), 'Successful registration consumes verification.');
$check($otp->verifiedContact($replayedSession) === null, 'Consumed proof cannot be replayed from an old session.');

$reset();
$s = $session();
$otp->send($s, 'phone@example.test', '127.0.0.1');
$sentCode = $code();
$wrong = $sentCode === '100000' ? '100001' : '100000';
for ($i = 0; $i < 5; $i++) {
    $reject(fn () => $otp->verify($s, $wrong, '127.0.0.1'), 'otp');
}
$reject(fn () => $otp->verify($s, $sentCode, '127.0.0.1'), 'otp', 'registration.attempts');
Carbon::setTestNow(now()->addSeconds(60));
$otp->send($s, 'phone@example.test', '127.0.0.1');
$check($otp->state($s)['attempts'] === 0, 'Resend resets challenge attempts after cooldown.');
$check(Hash::check($code(), $otp->state($s)['hash']), 'Resend replaces the challenge.');
Carbon::setTestNow(now()->addSeconds(300));
$reject(fn () => $otp->verify($s, $code(), '127.0.0.1'), 'otp', 'registration.expired');

$reset();
$s = $session();
$otp->send($s, '0991234567', '127.0.0.1');
$otp->verify($s, $code(), '127.0.0.1');
$check($otp->verifiedContact($s)['channel'] === 'sms', 'SMS verification works.');
Carbon::setTestNow(now()->addSeconds(60));
$otp->send($s, 'new@example.test', '127.0.0.1');
$check($otp->verifiedContact($s) === null, 'Changing contact invalidates the previous proof.');
$otp->verify($s, $code(), '127.0.0.1');
Carbon::setTestNow(now()->addSeconds(1800));
$check($otp->verifiedContact($s) === null, 'Verification expires after exactly 30 minutes.');

$reset();
$s = $session();
$delivery->enabled = ['email'];
$reject(fn () => $otp->send($s, '0991234567', '127.0.0.1'), 'contact', 'registration.unavailable');
$delivery->fail = true;
$reject(fn () => $otp->send($s, 'fail@example.test', '127.0.0.1'), 'contact', 'registration.delivery_failed');
$check($otp->state($s) === null && $otp->verifiedContact($s) === null, 'Failed delivery creates no challenge or proof.');
$check(count($logs) === 1 && !str_contains(json_encode($logs), 'secret-code-recipient'), 'Delivery errors logged without secrets.');

$reset();
$s = $session();
for ($i = 0; $i < 5; $i++) {
    $otp->send($s, 'limited@example.test', '127.0.0.1');
    Carbon::setTestNow(now()->addSeconds(60));
}
$reject(fn () => $otp->send($session(), 'limited@example.test', '127.0.0.2'), 'contact', 'registration.rate_limit');
$reset();
for ($i = 0; $i < 10; $i++) {
    $otp->send($session(), 'ip'.$i.'@example.test', '127.0.0.1');
}
$reject(fn () => $otp->send($session(), 'ip11@example.test', '127.0.0.1'), 'contact', 'registration.rate_limit');
$reset();
for ($i = 0; $i < 30; $i++) {
    $reject(fn () => $otp->verify($session(), '123456', '127.0.0.1'), 'otp');
}
$reject(fn () => $otp->verify($session(), '123456', '127.0.0.1'), 'contact', 'registration.rate_limit');

$reset();
$s = $session();
$otp->send($s, 'gate@example.test', '127.0.0.1');
$gate = new EnsureRegistrationContactVerified($otp);
$passed = false;
$next = function ($request) use (&$passed) {
    $passed = true;

    return new Response('wizard');
};
$prepareRequest = function (string $method, array $input = []) use ($s, $app, $redirector) {
    $request = Request::create('http://localhost/business/register', $method, $input);
    $request->setLaravelSession($s);
    $app->instance('request', $request);
    $redirector->setSession($s);

    return $request;
};
$response = $gate->handle($prepareRequest('GET', ['package' => '5']), $next);
$check(!$passed && $response->isRedirect('http://localhost/business/register/verify'), 'GET requires verification.');
$check($s->get('registration_package') === '5', 'Preserve selected package.');
$response = $gate->handle($prepareRequest('POST'), $next);
$check(!$passed && $response->isRedirect(), 'Direct POST cannot bypass verification.');
$otp->verify($s, $code(), '127.0.0.1');
$request = $prepareRequest('GET');
$response = $gate->handle($request, $next);
$check($passed && $request->attributes->get('verified_registration_contact')['contact'] === 'gate@example.test', 'Verified GET proceeds with contact.');
$passed = false;
$response = $gate->handle($prepareRequest('POST', ['email' => 'changed@example.test', 'password' => 'private', 'confirm_password' => 'private']), $next);
$check(!$passed && $response->isRedirect(), 'Cannot replace verified email on POST.');
$check(!isset($s->getOldInput()['password'], $s->getOldInput()['confirm_password']), 'Passwords are not flashed on mismatch.');
$gate->handle($prepareRequest('POST', ['email' => 'gate@example.test', 'mobile' => '0991234567']), $next);
$check($passed, 'Matching verified contact proceeds.');
$passed = false;
$config->set('constants.allow_registration', false);
$gate->handle($prepareRequest('POST', ['email' => 'gate@example.test']), $next);
$check(!$passed, 'Disabled registration remains disabled.');
$controller = new App\Http\Controllers\RegistrationVerificationController();
$check($controller->show($prepareRequest('GET'), $otp, $delivery)->isRedirect('http://localhost'), 'Verification page respects disabled registration.');
$sentCount = count($delivery->sent);
$check($controller->send($prepareRequest('POST'), $otp)->isRedirect('http://localhost'), 'Send endpoint respects disabled registration.');
$check(count($delivery->sent) === $sentCount, 'No delivery while registration is disabled.');
$check($controller->verify($prepareRequest('POST'), $otp)->isRedirect('http://localhost'), 'Verify endpoint respects disabled registration.');
$config->set('constants.allow_registration', true);
$lock = Cache::lock('registration-otp-lock:'.$s->get('registration_otp_token'), 120);
$lock->get();
$reject(fn () => $otp->verify($s, '123456', '127.0.0.1'), 'contact', 'registration.busy');
$lock->release();

$smsUtil = Mockery::mock(App\Utils\BusinessUtil::class)->makePartial();
$realDelivery = new RegistrationOtpDelivery($smsUtil);
$check($realDelivery->channels() === [], 'Channels disabled until configured.');
$settings = [
    'registration_otp_email_enabled' => '1',
    'registration_otp_sms_enabled' => '1',
    'sms_settings' => ['sms_service' => 'twilio', 'twilio_sid' => 'test-sid', 'twilio_token' => 'test-secret', 'twilio_from' => '+265991234567'],
];
$realDelivery->validateConfiguration($settings);
$realDelivery->saveSettings($settings);
$check($realDelivery->channels() === ['email', 'sms'], 'Configured channels enabled.');
$check($realDelivery->smsSettings()['twilio_token'] === 'test-secret', 'Encrypted SMS settings round-trip.');
$check(!str_contains(System::getProperty('registration_sms_settings'), 'test-secret'), 'SMS credentials encrypted at rest.');
$settings['sms_settings']['twilio_token'] = '';
$reject(fn () => $realDelivery->validateConfiguration($settings), 'sms_settings.twilio_token');
$config->set('mail.mailers.smtp.transport', 'log');
$reject(fn () => $realDelivery->validateConfiguration(['registration_otp_email_enabled' => 1]), 'registration_otp_email_enabled');
$config->set('mail.mailers.smtp.transport', 'array');
$reject(fn () => $realDelivery->validateConfiguration(['registration_otp_email_enabled' => 1]), 'registration_otp_email_enabled');
$config->set('mail.mailers.smtp.transport', 'smtp');
$realDelivery->validateConfiguration(['registration_otp_email_enabled' => 1], [
    'MAIL_MAILER' => 'smtp', 'MAIL_HOST' => 'new-smtp.example.test', 'MAIL_FROM_ADDRESS' => 'new-sender@example.test',
]);
$check(true, 'Validate newly submitted SMTP settings in the same save.');
$mail = Mockery::mock();
$mail->shouldReceive('raw')->once()->withArgs(function ($body, $callback) use ($check) {
    $check(str_contains($body, '654321'), 'Email contains the generated OTP.');
    $message = Mockery::mock();
    $message->shouldReceive('to')->once()->with('mail@example.test')->andReturnSelf();
    $message->shouldReceive('subject')->once()->andReturnSelf();
    $callback($message);

    return true;
});
Illuminate\Support\Facades\Mail::swap($mail);
$realDelivery->send('email', 'mail@example.test', '654321');
$check(true, 'Email delivery uses configured mailer and verified target.');
System::addProperty('registration_otp_email_enabled', '0');
try {
    $realDelivery->send('email', 'mail@example.test', '654321');
    throw new RuntimeException('Disabled channel sent a code.');
} catch (RuntimeException $e) {
    $check($e->getMessage() === 'Registration OTP channel is disabled.', 'Delivery rejects disabled channels.');
}
    $smsUtil->shouldReceive('sendSms')->once()->withArgs(function ($data) use ($check) {
        $check($data['mobile_number'] === '+265991234567', 'SMS goes to the normalized Malawi number.');
        $check(str_contains($data['sms_body'], '654321'), 'SMS contains the generated OTP.');
        $check($data['sms_settings']['twilio_token'] === 'test-secret', 'SMS uses Superadmin encrypted credentials.');
        $check($data['require_delivery_confirmation'] === true, 'OTP SMS requires provider acceptance.');

        return true;
    })->andReturnNull();
    $realDelivery->send('sms', '+265991234567', '654321');
    $smsUtil->shouldReceive('sendSms')->once()->andReturn(false);
    try {
        $realDelivery->send('sms', '+265991234567', '654321');
        throw new RuntimeException('Silent SMS failure was accepted.');
    } catch (RuntimeException $e) {
        $check($e->getMessage() === 'Registration SMS provider is not configured.', 'False SMS result is explicitly rejected.');
    }
$customSettings = $settings;
$customSettings['sms_settings'] = [
    'sms_service' => 'other',
    'url' => 'https://gateway.example.test/api/http/sms/send',
    'send_to_param_name' => 'recipient',
    'msg_param_name' => 'message',
];
$realDelivery->saveSettings($customSettings);
$responses = [
    new GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], '{"status":"error","message":"The recipient field is required."}'),
    new GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], '{"success":false}'),
    new GuzzleHttp\Psr7\Response(302, ['Location' => '/login']),
    new GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'text/html'], '<html>Login</html>'),
    new GuzzleHttp\Psr7\Response(200, [], '{"status":"failed"}'),
    new GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], '{"broken"'),
];
foreach ($responses as $response) {
    $smsUtil->shouldReceive('sendSms')->once()->andReturn($response);
    try {
        $realDelivery->send('sms', '+265991234567', '654321');
        throw new LogicException('Rejected custom gateway response was accepted.');
    } catch (RuntimeException | JsonException $e) {
        $check(true, 'Custom gateway failure does not report success.');
    }
}
$smsUtil->shouldReceive('sendSms')->once()->andReturn(
    new GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], '{"status":"success","data":{"status":"queued"}}')
);
$realDelivery->send('sms', '+265991234567', '654321');
$check(true, 'Gateway acceptance response succeeds.');
Cache::flush();
$smsUtil->shouldReceive('sendSms')->once()->andReturn($responses[0]);
$failedSession = $session();
$reject(fn () => (new RegistrationOtp($realDelivery))->send($failedSession, '+265991234567', '127.0.0.1'), 'contact', 'registration.delivery_failed');
$check((new RegistrationOtp($realDelivery))->state($failedSession) === null, 'Rejected gateway request creates no sent-code challenge.');
$check(!str_contains(json_encode($logs), 'recipient field'), 'Gateway response bodies are not logged.');
$customSettings['sms_settings'] += [
    'request_method' => 'post',
    'data_parameter_type' => 'json',
    'send_to_param_type' => 'string',
    'header_1' => 'Accept',
    'header_val_1' => 'application/json',
    'header_2' => 'Content-Type',
    'header_val_2' => 'application/json',
    'param_1' => 'api_token',
    'param_val_1' => 'fake-test-token',
    'param_2' => 'sender_id',
    'param_val_2' => 'Mbira',
    'param_3' => 'type',
    'param_val_3' => 'plain',
];
$realDelivery->saveSettings($customSettings);
$client = Mockery::mock('overload:GuzzleHttp\Client');
$client->shouldReceive('__construct')->once()->with(['timeout' => 20, 'connect_timeout' => 5]);
$client->shouldReceive('post')->once()->withArgs(function ($endpoint, $options) use ($check) {
    $check($endpoint === 'https://gateway.example.test/api/http/sms/send', 'Configured endpoint is used.');
    $check($options['json'] === [
        'recipient' => '+265991234567',
        'message' => __('registration.message', ['app' => config('app.name'), 'code' => '654321']),
        'api_token' => 'fake-test-token',
        'sender_id' => 'Mbira',
        'type' => 'plain',
    ], 'MbiraNotify JSON request uses documented recipient/message/token/sender/type fields.');
    $check($options['headers'] === ['Accept' => 'application/json', 'Content-Type' => 'application/json'], 'JSON headers are sent.');
    $check($options['allow_redirects'] === false, 'OTP does not follow gateway login redirects.');

    return true;
})->andReturn(new GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], '{"status":"success"}'));
(new RegistrationOtpDelivery(new App\Utils\BusinessUtil()))->send('sms', '+265991234567', '654321');
$reset();
$contacts = new App\Services\RegistrationContacts();
$capsule->table('users')->insert(['email' => ' Registered@Example.test ', 'contact_number' => '(0991) 234-567', 'deleted_at' => '2026-01-01']);
$capsule->table('business_locations')->insert(['alternate_number' => '+265 882 123 456']);
$beforeDuplicateSends = count($delivery->sent);
$reject(fn () => $otp->send($session(), 'registered@example.test', '127.0.0.1'), 'contact', 'registration.contact_registered');
$reject(fn () => $otp->send($session(), '+265991234567', '127.0.0.1'), 'contact', 'registration.contact_registered');
$reject(fn () => $otp->send($session(), '0882 123 456', '127.0.0.1'), 'contact', 'registration.contact_registered');
$check(count($delivery->sent) === $beforeDuplicateSends, 'Duplicate contacts rejected before delivery.');
foreach (['+265991234567', '0991 234 567', '991234567', '00265991234567'] as $storedPhone) {
    $capsule->table('users')->update(['contact_number' => $storedPhone]);
    $reject(fn () => $contacts->assertAvailable('sms', '0991234567'), 'contact', 'registration.contact_registered');
}
$reject(fn () => $contacts->phone('+260971234567'), 'mobile', 'registration.malawi_phone_required');
$reject(fn () => $contacts->phone('not-a-phone'), 'mobile', 'registration.malawi_phone_required');
$check($contacts->phone('0882123456') === '+265882123456', 'Normalize valid Malawi phone.');
$late = $session();
$otp->send($late, 'late@example.test', '127.0.0.1');
$lateCode = $code();
$capsule->table('users')->insert(['email' => 'late@example.test']);
$reject(fn () => $otp->verify($late, $lateCode, '127.0.0.1'), 'contact', 'registration.contact_registered');
$reject(fn () => $contacts->registration(Request::create('/', 'POST', ['email' => 'new@example.test']), fn () => true), 'mobile');
$reject(fn () => $contacts->registration(Request::create('/', 'POST', [
    'email' => 'new@example.test', 'mobile' => '+260971234567',
]), fn () => true), 'mobile');
$reject(fn () => $contacts->registration(Request::create('/', 'POST', [
    'email' => 'registered@example.test', 'mobile' => '0992123456',
]), fn () => true), 'email');
$reject(fn () => $contacts->registration(Request::create('/', 'POST', [
    'email' => 'new@example.test', 'mobile' => '0992123456', 'alternate_number' => '0882123456',
]), fn () => true), 'alternate_number');
$freshRequest = Request::create('/', 'POST', ['email' => 'NEW@example.test', 'mobile' => '0992123456']);
$contacts->registration($freshRequest, function () use ($check, $freshRequest, $contacts) {
    $check($freshRequest->input('mobile') === '+265992123456' && $freshRequest->input('email') === 'new@example.test', 'Final registration normalizes both contacts.');
    $blocked = false;
    try {
        $contacts->registration(Request::create('/', 'POST', [
            'email' => 'new@example.test', 'mobile' => '0993123456',
        ]), fn () => true);
    } catch (ValidationException $e) {
        $blocked = isset($e->errors()['contact']);
    }
    $check($blocked, 'Concurrent registrations cannot reuse a locked email.');

    return true;
});

Carbon::setTestNow();
Mockery::close();
echo "PASS: {$checks} registration OTP checks (no real delivery or live data).".PHP_EOL;
