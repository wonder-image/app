<?php
/** php tests/Security/SvgSanitizerTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';
require __DIR__.'/../../app/function/string/general.php';
require __DIR__.'/../../app/function/file/upload.php';

use Wonder\App\Security\SvgSanitizer;

$ns = 'xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"';
$clean = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<svg $ns viewBox=\"0 0 10 10\">\n  <!-- logo -->\n  <style>.a{fill:#c00}</style>\n  <rect class=\"a\" width=\"10\" height=\"10\"/>\n  <a href=\"https://example.com/\"><circle cx=\"5\" cy=\"5\" r=\"2\"/></a>\n  <image xlink:href=\"data:image/png;base64,iVBORw0KGgo=\" width=\"1\" height=\"1\"/>\n</svg>\n";

$sanitized = static function (string $body) use ($ns): ?string {
    return SvgSanitizer::sanitize("<svg $ns>$body</svg>");
};

$safe = static function (?string $svg): bool {
    if ($svg === null) {
        return false;
    }
    $lower = strtolower(preg_replace('/\s+/', '', $svg));
    foreach (['<script', 'onload', 'onclick', 'onbegin', 'foreignobject', 'javascript:', 'data:text/html', 'data:image/svg', 'expression('] as $needle) {
        if (str_contains($lower, $needle)) {
            echo "    resta: $needle\n";
            return false;
        }
    }
    return true;
};

check('SVG pulito restituito identico, byte per byte', function () use ($clean) {
    return SvgSanitizer::sanitize($clean) === $clean;
});

check('<script> rimosso, il resto resta', function () use ($sanitized, $safe) {
    $out = $sanitized('<script>alert(1)</script><script type="text/ecmascript"><![CDATA[alert(2)]]></script><rect width="1"/>');
    return $safe($out) && str_contains((string) $out, '<rect width="1"/>');
});

check('attributi on* rimossi (onload sulla radice, onclick, onbegin)', function () use ($ns, $sanitized, $safe) {
    $root = SvgSanitizer::sanitize("<svg $ns onload=\"alert(1)\" ONMOUSEOVER=\"x()\"><rect onclick=\"alert(2)\" width=\"1\"/></svg>");
    $anim = $sanitized('<rect width="1"><animate onbegin="alert(3)" attributeName="x" to="2"/></rect>');
    return $safe($root) && $safe($anim) && str_contains((string) $root, '<rect width="1"/>');
});

check('<foreignObject> rimosso con il contenuto HTML', function () use ($sanitized, $safe) {
    $out = $sanitized('<foreignObject width="10" height="10"><body xmlns="http://www.w3.org/1999/xhtml"><iframe src="x"/></body></foreignObject><circle r="1"/>');
    return $safe($out) && !str_contains(strtolower((string) $out), 'iframe') && str_contains((string) $out, '<circle r="1"/>');
});

check('href/xlink:href javascript: e data: non raster rimossi', function () use ($sanitized, $safe) {
    $out = $sanitized(
        '<a href="javascript:alert(1)"><text>a</text></a>'
        .'<a xlink:href=" JaVa&#x0A;Script:alert(2)"><text>b</text></a>'
        .'<a href="data:text/html;base64,PHNjcmlwdD4="><text>c</text></a>'
        .'<image xlink:href="data:image/svg+xml;base64,PHN2Zz4=" width="1"/>'
        .'<set attributeName="href" to="javascript:alert(3)"/>'
        .'<image xlink:href="data:image/png;base64,iVBORw0KGgo=" width="1"/>'
    );
    return $safe($out) && str_contains((string) $out, 'data:image/png;base64');
});

check('style con url(javascript o expression( rimosso', function () use ($sanitized, $safe) {
    $out = $sanitized('<style>rect{background:url(javascript:alert(1))}</style><rect style="width:expression(alert(2))" fill="red"/>');
    return $safe($out) && str_contains((string) $out, 'fill="red"') && !str_contains((string) $out, '<style');
});

check('processing instruction rimossa', function () use ($ns) {
    $out = SvgSanitizer::sanitize("<?xml version=\"1.0\"?>\n<?xml-stylesheet href=\"javascript:alert(1)\"?>\n<svg $ns/>");
    return $out !== null && !str_contains($out, 'xml-stylesheet');
});

check('DOCTYPE/entità, XML non valido e radice non svg rifiutati', function () use ($ns) {
    $xxe = "<?xml version=\"1.0\"?>\n<!DOCTYPE svg [<!ENTITY x SYSTEM \"file:///etc/passwd\">]>\n<svg $ns><text>&x;</text></svg>";
    return SvgSanitizer::sanitize($xxe) === null
        && SvgSanitizer::sanitize("<svg $ns><rect></svg>") === null
        && SvgSanitizer::sanitize('<html><body>x</body></html>') === null
        && SvgSanitizer::sanitize('') === null
        && SvgSanitizer::sanitize('non è xml') === null;
});

check('idempotente: ripulire due volte non cambia nulla', function () use ($sanitized) {
    $once = $sanitized('<script>x</script><rect onload="y" width="1"/>');
    return $once !== null && SvgSanitizer::sanitize($once) === $once;
});

check('uploadFiles: SVG non valido rifiutato con 931, svg senza whitelist 921', function () {
    global $ALERT;
    $dir = sys_get_temp_dir().'/wi-svg-'.bin2hex(random_bytes(4));
    mkdir($dir);
    $tmp = $dir.'/upload.tmp';
    file_put_contents($tmp, "<!DOCTYPE svg [<!ENTITY x \"y\">]><svg xmlns=\"http://www.w3.org/2000/svg\"/>");
    $files = ['name' => ['logo.svg'], 'type' => ['image/svg+xml'], 'tmp_name' => [$tmp], 'error' => [0], 'size' => [filesize($tmp)]];

    $ALERT = null;
    uploadFiles($files, ['extensions' => ['svg'], 'max_size' => 1], $dir.'/dest', []);
    $invalid = $ALERT;

    $ALERT = null;
    uploadFiles($files, ['max_size' => 1], $dir.'/dest', []);
    $denied = $ALERT;

    exec('rm -rf '.escapeshellarg($dir));
    return $invalid === 931 && $denied === 921;
});

summary();
