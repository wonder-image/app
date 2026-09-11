<?php
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\Console\Commands\Build;

check('new installations include managed performance rules exactly once', function () {
    $template = Build::htaccessTemplate();
    return substr_count($template, '# WONDER PERFORMANCE START') === 1
        && str_contains($template, 'webp|avif')
        && str_contains($template, 'expr=%{QUERY_STRING}')
        && str_contains($template, 'max-age=86400, must-revalidate');
});

check('legacy custom rules are preserved and repeated updates are idempotent', function () {
    $custom = "# Hosting custom configuration\nRedirect 301 /old /new\n";
    $updated = Build::updateHtaccessPerformance($custom);
    return str_starts_with($updated, $custom)
        && Build::updateHtaccessPerformance($updated) === $updated;
});

check('existing managed block is replaced without changing surrounding content', function () {
    $old = "# before\n# WONDER PERFORMANCE START\nold rules\n# WONDER PERFORMANCE END\n# after\n";
    return Build::updateHtaccessPerformance($old)
        === "# before\n".Build::htaccessPerformanceBlock()."\n# after\n";
});

summary();
