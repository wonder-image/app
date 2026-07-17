<?php
/** php tests/App/Support/SyncTableSorterTest.php */
declare(strict_types=1);

require __DIR__.'/../../../vendor/autoload.php';

use Wonder\App\Models\Config\SocietyAddress;
use Wonder\App\Models\Config\SocietyTimetable;
use Wonder\App\Models\Css\CssDefault;
use Wonder\App\Models\Css\CssFont;
use Wonder\App\Model;
use Wonder\App\Support\SyncTableSorter;
use Wonder\Sql\TableSchema as Column;

final class SyncCycleA extends Model
{
    public static string $table = 'cycle_a';

    public static function tableSchema(): array
    {
        return [Column::key('cycle_b_id')->int()->foreign('cycle_b')];
    }

    public static function dataSchema(): array
    {
        return [];
    }
}

final class SyncCycleB extends Model
{
    public static string $table = 'cycle_b';

    public static function tableSchema(): array
    {
        return [Column::key('cycle_a_id')->int()->foreign('cycle_a')];
    }

    public static function dataSchema(): array
    {
        return [];
    }
}

$fail = 0;

function same(string $label, array $actual, array $expected): void
{
    global $fail;

    if ($actual === $expected) {
        echo "ok: {$label}\n";
        return;
    }

    $fail++;
    echo "FAIL: {$label}\n";
    echo '  expected: '.json_encode($expected)."\n";
    echo '  actual:   '.json_encode($actual)."\n";
}

same(
    'css_font precede css_default',
    SyncTableSorter::sort(
        ['css_default', 'css_font'],
        [
            'css_default' => CssDefault::class,
            'css_font' => CssFont::class,
        ]
    ),
    ['css_font', 'css_default']
);

same(
    'piu dipendenze sono ordinate stabilmente',
    SyncTableSorter::sort(
        ['css_default', 'css_font', 'society_timetable', 'society_address'],
        [
            'css_default' => CssDefault::class,
            'css_font' => CssFont::class,
            'society_timetable' => SocietyTimetable::class,
            'society_address' => SocietyAddress::class,
        ]
    ),
    ['css_font', 'society_address', 'css_default', 'society_timetable']
);

same(
    'dipendenze fuori dal sync non modificano l ordine',
    SyncTableSorter::sort(
        ['css_default'],
        ['css_default' => CssDefault::class]
    ),
    ['css_default']
);

same(
    'un ciclo conserva l ordine configurato',
    SyncTableSorter::sort(
        ['cycle_b', 'cycle_a'],
        [
            'cycle_a' => SyncCycleA::class,
            'cycle_b' => SyncCycleB::class,
        ]
    ),
    ['cycle_b', 'cycle_a']
);

echo $fail === 0 ? "\nTutti i test passati\n" : "\n{$fail} test falliti\n";
exit($fail === 0 ? 0 : 1);
