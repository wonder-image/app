<?php // tests/Console/ModulePublishTest.php
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\Console\Commands\ModulePublish;

final class ModulePublishProbe extends ModulePublish
{
    public function call(string $method, ...$args)
    {
        return $this->$method(...$args);
    }
}

$probe = new ModulePublishProbe();

check('components mapped under slug', function () use ($probe) {
    return $probe->call('destinationFor', 'components/countdown.php', 'rsvp', '/r')
        === '/r/custom/view/components/rsvp/countdown.php';
});

check('pages keep nested area', function () use ($probe) {
    return $probe->call('destinationFor', 'pages/frontend/home.php', 'rsvp', '/r')
        === '/r/custom/view/pages/rsvp/frontend/home.php';
});

check('single top-level file', function () use ($probe) {
    return $probe->call('destinationFor', 'config.php', 'rsvp', '/r')
        === '/r/custom/view/rsvp/config.php';
});

summary();
