<?php

require dirname(__DIR__).'/vendor/autoload.php';

use Wonder\App\ResourceSchema\FormField;

$checks = 0;
$check = static function (bool $c, string $m) use (&$checks): void {
    if (!$c) { throw new RuntimeException($m); }
    $checks++;
};

// --- Task 1: HasQuickCreate concern -----------------------------------------

// A tiny fake target resource with a slug.
$fakeResource = new class {
    public static function slug(): string { return 'category'; }
};
$fakeClass = get_class($fakeResource);

$input = FormField::key('category_id')->select(['1' => 'A'])->quickCreate($fakeClass, ['name'], 'name');
$config = ($input->get()['context']['quick_create'] ?? null);

$check(is_array($config), 'quick_create config stored');
$check($config['slug'] === 'category', 'slug resolved from target');
$check($config['fields'] === ['name'], 'subset fields stored');
$check($config['label'] === 'name', 'label field stored');
$check($config['resource'] === $fakeClass, 'target class stored');

echo "OK: {$checks} checks passed\n";
