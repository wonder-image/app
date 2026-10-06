<?php
/** php tests/Themes/ChoiceTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Themes\Concerns\MergesClasses;

$merge = new class {
    use MergesClasses;

    public function run(array $base, array $attributes): array
    {
        return $this->mergeClasses($base, $attributes);
    }
};

check('le classi date si aggiungono a quelle base, senza vuoti né doppioni', fn () =>
    $merge->run(['wi-choice'], ['class' => ['mt-2', 'wi-choice', '']]) === ['wi-choice', 'mt-2']
    && $merge->run(['card'], ['class' => ' a  b ']) === ['card', 'a', 'b']
    && $merge->run(['wi-steps'], []) === ['wi-steps']
);

summary();
