<?php
/** php tests/Auth/AuthFormSpacingTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\Auth\Frontend\AuthProfile;

// Spazi dei form di auth da `custom/config/auth.php`.
$root = sys_get_temp_dir().'/wi-auth-spacing-'.bin2hex(random_bytes(4));
mkdir($root.'/custom/config', 0777, true);
$profile = new AuthProfile();

$GLOBALS['ROOT'] = $root.'/senza-config';
check('senza config → gap e margin top 6, anche da cellulare', fn () =>
    $profile->formGap('login') === 6 && $profile->formMarginTop('login') === 6
    && $profile->formGap('login', 'phone') === 6 && $profile->formMarginTop('login', 'phone') === 6
);

file_put_contents($root.'/custom/config/auth.php', '<?php return [
    "form" => ["gap" => ["desktop" => 5, "phone" => 3], "margin_top" => 20],
    "login" => ["gap" => 4],
    "signup-request" => ["margin_top" => ["desktop" => 8, "phone" => 2]],
];');
$GLOBALS['ROOT'] = $root;
check('form → gap desktop e cellulare', fn () => $profile->formGap('password-recovery') === 5 && $profile->formGap('password-recovery', 'phone') === 3);
check('margin top oltre 12 → 12, anche da cellulare', fn () => $profile->formMarginTop('password-recovery') === 12 && $profile->formMarginTop('password-recovery', 'phone') === 12);
check('login con un numero → stesso gap da cellulare', fn () => $profile->formGap('login') === 4 && $profile->formGap('login', 'phone') === 4);
check('login senza margin top → quello di form', fn () => $profile->formMarginTop('login') === 12);
check('signup-request → margin top desktop e cellulare', fn () => $profile->formMarginTop('signup-request') === 8 && $profile->formMarginTop('signup-request', 'phone') === 2);

exec('rm -rf '.escapeshellarg($root));
summary();
