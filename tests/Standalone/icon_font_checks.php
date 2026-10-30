<?php

// No application kernel, network, or database required.
$root = dirname(__DIR__, 2);
$checks = 0;
$check = function (bool $valid, string $message) use (&$checks) {
    if (!$valid) {
        throw new RuntimeException($message);
    }
    ++$checks;
};
foreach (['vendor.css', 'icon-fonts.css'] as $stylesheet) {
    $css = file_get_contents($root.'/public/css/'.$stylesheet);
    preg_match_all('~url\(["\']?(\.\./fonts/[^)"\'?]+)(?:\?[^)"\']*)?["\']?\)~', $css, $matches);
    foreach (array_unique($matches[1]) as $reference) {
        $reference = explode('#', $reference, 2)[0];
        $path = $root.'/public/css/'.$reference;
        $check(is_file($path) && filesize($path) > 0, 'Font exists: '.$reference);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if (in_array($extension, ['woff', 'woff2'], true)) {
            $check(file_get_contents($path, false, null, 0, 4) === ($extension === 'woff' ? 'wOFF' : 'wOF2'), 'Valid font header: '.$reference);
        }
    }
}
$css = file_get_contents($root.'/public/css/icon-fonts.css');
foreach (['solid-900', 'regular-400', 'brands-400'] as $weight) {
    $check(str_contains($css, 'webfa-'.$weight.'.woff2'), 'Font weight is loaded: '.$weight);
}
$check(is_file($root.'/public/fonts/vendor/@fortawesome/fontawesome-free/LICENSE.txt'), 'Distribution license is included.');
foreach (['resources/views/layouts/partials/css.blade.php', 'resources/views/layouts/guest.blade.php'] as $view) {
    $check(str_contains(file_get_contents($root.'/'.$view), "@include('layouts.partials.icon-fonts')"), 'Shared layout loads font definitions.');
}
echo "PASS: {$checks} icon font checks.".PHP_EOL;
