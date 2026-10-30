<?php

// No application kernel, network, or database required.
$root = dirname(__DIR__, 2);
$css = file_get_contents($root.'/public/css/vendor.css');
$checks = 0;
$check = function (bool $valid, string $message) use (&$checks) {
    if (!$valid) {
        throw new RuntimeException($message);
    }
    ++$checks;
};
foreach (['blue.png' => [240, 24], 'blue@2x.png' => [480, 48]] as $filename => $dimensions) {
    $relative = '../images/vendor/icheck/skins/square/'.$filename;
    $check(str_contains($css, $relative), 'Vendor CSS references '.$filename);
    $path = $root.'/public/images/vendor/icheck/skins/square/'.$filename;
    $check(is_file($path), 'Sprite exists: '.$filename);
    $image = getimagesize($path);
    $check($image !== false && $image[2] === IMAGETYPE_PNG, 'Valid PNG sprite: '.$filename);
    $check([$image[0], $image[1]] === $dimensions, 'Correct sprite dimensions: '.$filename);
}
echo "PASS: {$checks} checkbox/radio asset checks.".PHP_EOL;
