<?php
/** php tests/OAuth/PkceTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\OAuth\Pkce;

// RFC 7636, appendice B
$verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
$challenge = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';

check('vettore RFC 7636 B', fn () => Pkce::challenge($verifier) === $challenge && Pkce::verify($verifier, $challenge));
check('verifier diverso rifiutato', fn () => !Pkce::verify(str_repeat('a', 43), $challenge));
check('verifier troppo corto', fn () => !Pkce::validVerifier(str_repeat('a', 42)));
check('verifier troppo lungo', fn () => !Pkce::validVerifier(str_repeat('a', 129)));
check('verifier ai limiti ammesso', fn () => Pkce::validVerifier(str_repeat('a', 43)) && Pkce::validVerifier(str_repeat('a', 128)));
check('carattere non ammesso', fn () => !Pkce::validVerifier(str_repeat('a', 42) . '+'));
check('verify con verifier non valido è falso', fn () => !Pkce::verify('corto', $challenge));
check('challenge: 43 caratteri base64url', fn () => Pkce::validChallenge($challenge) && !Pkce::validChallenge('abc') && !Pkce::validChallenge(str_repeat('+', 43)));

summary();
