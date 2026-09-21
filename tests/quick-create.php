<?php

require dirname(__DIR__).'/vendor/autoload.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\Backend\Support\QuickCreateAuthorizer;
use Wonder\Backend\Support\QuickCreateController;

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

// --- Task 2: QuickCreateAuthorizer ------------------------------------------

$permResource = new class {
    public static function slug(): string { return 'category'; }
    public static function permissionSchema(): object {
        return new class {
            public function get(string $k): array {
                return $k === 'backend' ? ['create' => ['admin', 'editor']] : [];
            }
        };
    }
};
$permClass = get_class($permResource);

$check(QuickCreateAuthorizer::createAuthority($permClass) === ['admin', 'editor'], 'reads backend.create authority');
$check(QuickCreateAuthorizer::userCanCreate($permClass, ['editor']) === true, 'intersecting authority allowed');
$check(QuickCreateAuthorizer::userCanCreate($permClass, ['viewer']) === false, 'non-intersecting denied');

// --- Task 3: QuickCreateController::whitelist (pure, DB-free) ----------------

$kept = QuickCreateController::whitelist(['name', 'slug'], ['name' => 'Scarpe', 'slug' => 'scarpe', 'evil' => 'x', 'id' => '9']);
$check($kept === ['name' => 'Scarpe', 'slug' => 'scarpe'], 'whitelist keeps only declared subset keys');

echo "OK: {$checks} checks passed\n";
