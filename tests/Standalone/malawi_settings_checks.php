<?php

// Isolated SQLite data only; no application kernel or live records.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Utils\BusinessUtil;
use Illuminate\Config\Repository;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\DatabasePresenceVerifier;
use Illuminate\Validation\Factory;

$app = new Application(dirname(__DIR__, 2));
$app->instance('config', new Repository());
$capsule = new Capsule($app);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$app->instance('db', $capsule->getDatabaseManager());
Facade::setFacadeApplication($app);
Facade::clearResolvedInstances();
$connection = $capsule->getConnection();
$connection->getPdo()->sqliteCreateFunction('concat', fn (...$parts) => implode('', $parts));
$connection->getSchemaBuilder()->create('currencies', function (Blueprint $table) {
    $table->integer('id')->primary();
    $table->string('country');
    $table->string('currency');
    $table->string('code');
});
$connection->table('currencies')->insert([
    ['id' => 17, 'country' => 'Malawi', 'currency' => 'Kwacha', 'code' => 'MWK'],
    ['id' => 18, 'country' => 'United States', 'currency' => 'Dollar', 'code' => 'USD'],
    ['id' => 19, 'country' => 'Malawi', 'currency' => 'Legacy Kwacha', 'code' => 'MMK'],
]);
$factory = new Factory(new Translator(new ArrayLoader(), 'en'), $app);
$factory->setPresenceVerifier(new DatabasePresenceVerifier($capsule->getDatabaseManager()));
$util = new BusinessUtil();
$checks = 0;
$check = function (bool $condition, string $message) use (&$checks) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
};

$currencies = $util->allCurrencies();
$check($currencies->keys()->all() === [17], 'Only MWK is selectable, with no hardcoded currency ID.');
$check($util->allTimeZones() === ['Africa/Blantyre' => 'Africa/Blantyre'], 'Only the Malawi time zone is selectable.');
$rules = BusinessUtil::malawiSettingsRules();
$valid = ['country' => 'Malawi', 'currency_id' => 17, 'time_zone' => 'Africa/Blantyre'];
$check($factory->make($valid, $rules)->passes(), 'Malawi settings are accepted.');
foreach ([
    ['country' => 'Zambia'],
    ['country' => ''],
    ['currency_id' => 18],
    ['currency_id' => 19],
    ['currency_id' => 999],
    ['time_zone' => 'UTC'],
    ['time_zone' => 'Africa/Lusaka'],
] as $invalid) {
    $field = array_key_first($invalid);
    $validator = $factory->make(array_replace($valid, $invalid), $rules);
    $check($validator->fails() && $validator->errors()->has($field), 'Reject invalid '.$field.': '.reset($invalid));
}
$check($factory->make([], $rules)->fails(), 'Missing required settings are rejected.');
$check($connection->table('currencies')->count() === 3, 'Foreign and legacy currency records are not deleted.');

echo "PASS: {$checks} Malawi settings checks (SQLite memory only).".PHP_EOL;
