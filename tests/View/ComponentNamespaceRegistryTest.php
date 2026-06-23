<?php // tests/View/ComponentNamespaceRegistryTest.php
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\View\ComponentNamespaceRegistry;

ComponentNamespaceRegistry::reset();

check('register + has', function () {
    ComponentNamespaceRegistry::register('rsvp', '/abs/rsvp/view/components/');
    return ComponentNamespaceRegistry::has('rsvp') === true
        && ComponentNamespaceRegistry::has('nope') === false;
});

check('base strips trailing slash', function () {
    return ComponentNamespaceRegistry::base('rsvp') === '/abs/rsvp/view/components';
});

check('base null when missing', function () {
    return ComponentNamespaceRegistry::base('nope') === null;
});

check('reset clears map', function () {
    ComponentNamespaceRegistry::reset();
    return ComponentNamespaceRegistry::all() === [];
});

summary();
