# Sedi della società in "Dati aziendali" — Piano di implementazione (3 di 3)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** trasformare "Dati aziendali" nella pagina delle sedi della società, con eredità dalla sede predefinita, orari e chiusure sul modello di Google, `infoSociety()` per sede e migrazione dei siti esistenti; poi verificare i piani 1, 2 e 3 su `boilerplates/new-site` con database.

**Architecture:** la logica sta in classi pure testabili senza database (`OpeningHours`, `SocietyLocationResolver`, `SocietyLocationDefaults`, `LegacySocietyMapper`, `OpeningHoursInput`, `SocietyLocations::assemble()`); i Model descrivono tre tabelle nuove; `SocietyLocations` legge una volta per richiesta e alimenta `infoSociety()`. Il backend usa due Resource sullo stesso Model: "Dati aziendali" (CRUD delle sedi, `admin`) e "Orari e chiusure" (pagina di modifica propria, `admin` e `administrator`). Due piccoli miglioramenti generici del backend servono alla scheda: azioni e sottotitolo nell'header dei form, suggerimenti (`placeholder`) calcolati dalla Resource.

**Tech Stack:** PHP 8.2, `wonder-image/app`, mysqli, test con `tests/harness.php`.

**Spec:** `docs/superpowers/specs/2026-09-16-prerequisiti-moduli-gestionale-design.md` (parte E, sezioni Compatibilità e Validazione). Piani precedenti: `2026-09-17-ambiente-e-sincronizzazione.md`, `2026-09-17-transazioni-guida-classi-fiscali.md`.

## Global Constraints

- Repo `wonder-image/app`, ramo `feature/prerequisiti-moduli-gestionale`. Non toccare `vendor-static/xml-sitemaps/data/generator.conf`.
- I test sono in `.gitignore` (`/tests/*`): si aggiungono con `git add -f`.
- Tabelle: `society_locations` (`SyncSchema::multiRow()->keepIds()`, non `localOnly()`), `society_location_hours` e `society_location_special_hours` (non sincronizzate).
- Giorni `Mon`…`Sun`; orari `HH:MM`; `24:00` = mezzanotte a fine giornata; chiusura vuota = sempre aperto.
- `hours_type`: `regular` o un tipo secondario di Google in minuscolo; gli orari speciali valgono solo per `regular`.
- Eredità dalla predefinita: contatti (`email`, `pec`, `tel`, `cel`) e link campo per campo; dati aziendali e legali, indirizzo (con Place ID), sede legale per gruppo intero; orari e orari speciali insieme, solo se la sede non ha orari propri.
- `infoSociety(int|string|null $location = null)`: senza argomento la predefinita; id o slug inesistenti → predefinita; stessi campi di oggi più `location`, `google_place_id`, `hours`, `specialHours`, `businessStatus`.
- Una sola sede predefinita; la prima è predefinita; la predefinita non si elimina.
- "Dati aziendali": stesso percorso `app/config/corporate-data`, permessi `admin`. "Orari e chiusure": `admin` e `administrator`, anche in produzione.
- Vecchie tabelle `society*` restano, senza `syncSchema()` e senza scritture.
- Nessuna chiamata alle API di Google.
- Commit in inglese chiusi da `Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>`.
- Verifica su `boilerplates/new-site`: nessun commit in quel repo, nessuna password letta o scritta, stato dei file ripristinato alla fine.

## Mappa dei file

| File | Azione | Responsabilità |
|---|---|---|
| `class/App/Support/OpeningHours.php` | nuovo | fasce di una data, aperto/chiuso, timetable legacy, orari speciali futuri |
| `class/App/Support/SocietyLocationResolver.php` | nuovo | eredità dalla predefinita, valori ereditati, orari effettivi |
| `class/App/Support/GoogleMapsLink.php` | nuovo | link a Google Maps dal Place ID |
| `class/App/Support/SocietyLocationDefaults.php` | nuovo | regole della sede predefinita |
| `class/App/Models/Config/SocietyLocation.php` | nuovo | tabella `society_locations` |
| `class/App/Models/Config/SocietyLocationHour.php` | nuovo | tabella `society_location_hours` |
| `class/App/Models/Config/SocietyLocationSpecialHour.php` | nuovo | tabella `society_location_special_hours` |
| `class/App/Models/Config/Society*.php` (5) | modifica | niente più `syncSchema()` |
| `app/build/row/society.php` | modifica | crea solo `logos` |
| `class/App/Support/LegacySocietyMapper.php` | nuovo | vecchie tabelle → sede e orari |
| `class/App/Support/SocietyLocationsMigration.php` | nuovo | migrazione una tantum e lettura dei vecchi dati |
| `class/App/UpdateRunner.php` | modifica | passo `society_locations` |
| `class/App/Support/SocietyLocations.php` | nuovo | lettura per richiesta, `default/find/all/hoursFor/isOpen` |
| `app/function/info.php` | modifica | `infoSociety($location)`, `infoSocietyLocations()` |
| `app/service/lang.php` | modifica | `$SOCIETY` anche con le sole tabelle nuove |
| `class/App/ResourceSchema/Input.php` | modifica | `placeholder()` |
| `class/App/Resource.php` | modifica | `formPlaceholders()` |
| `class/Backend/Support/ResourcePagePresenter.php` | modifica | form con `SUBTITLE`, `ACTIONS`, suggerimenti |
| `app/view/layout/backend/partials/header-actions.php` | nuovo | pulsanti dell'header (da `show.php`) |
| `app/view/layout/backend/show.php`, `form.php` | modifica | usano il partial; il form mostra sottotitolo, azioni, messaggio d'errore |
| `class/App/Resources/Config/CorporateDataResource.php` | riscritto | Resource delle sedi |
| `app/config/routes/route.backend.php` | modifica | via le route legacy di `corporate-data` |
| `app/http/backend/config/corporate-data.php`, `app/view/pages/backend/config/corporate-data.php`, `class/App/PageSchema/CorporateDataPageSchema.php` | eliminati | pagina legacy |
| `class/App/Support/OpeningHoursInput.php` | nuovo | normalizzazione e validazione dei repeater |
| `class/App/Resources/Config/OpeningHoursResource.php` | nuovo | "Orari e chiusure" |
| `class/Backend/Support/OpeningHoursPageController.php` | nuovo | modifica e salvataggio degli orari |
| `app/http/backend/config/opening-hours.php` | nuovo | handler |
| `tests/App/Support/OpeningHoursTest.php`, `SocietyLocationResolverTest.php`, `LegacySocietyMapperTest.php`, `SocietyLocationsTest.php`, `OpeningHoursInputTest.php`, `tests/App/SocietyLocationModelsTest.php`, `tests/App/ResourceSchema/InputPlaceholderTest.php`, `tests/Backend/Support/HeaderActionsPartialTest.php` | nuovi | test senza database |
| `docs/app/concetti/dati-aziendali.md`, `docs/app/SUMMARY.md`, `docs/app/piattaforma/multi-ambiente.md`, `docs/app/piattaforma/installazione-e-deploy.md`, `docs/app/concetti/risorse/custom-page-schema.md`, `docs/app/concetti/risorse/resource.md` | nuovo/modifica | documentazione |

Esecuzione dei test di un file: `php tests/<percorso>.php` (esce con `N test, 0 falliti`). Suite completa: `for f in $(find tests -name '*Test.php' | sort); do php "$f" > /dev/null || echo "FAIL $f"; done`.

---
### Task 1: Calcolo degli orari (`OpeningHours`)

**Files:**
- Create: `class/App/Support/OpeningHours.php`
- Test: `tests/App/Support/OpeningHoursTest.php`

**Interfaces:**
- Consumes: nulla.
- Produces:
  - `OpeningHours::REGULAR = 'regular'`, `OpeningHours::DAYS` (`Mon`…`Sun`), `OpeningHours::SECONDARY_TYPES` (13 tipi);
  - `periodsFor(array $hours, array $specialHours, DateTimeInterface $date, string $type = 'regular'): list<array{open: string, close: string, overnight: bool}>` (vuota = chiuso);
  - `isOpenAt(array $hours, array $specialHours, DateTimeInterface $at, string $type = 'regular'): bool`;
  - `timetable(array $hours, string $type = 'regular'): array<string, list<array{from: string, to: string}>>` (formato di `prettyTimeTable()`);
  - `upcomingSpecial(array $specialHours, DateTimeInterface $from): list<array>` (date `Y-m-d`, `closed` booleano);
  - `time(mixed $value): string` (`HH:MM` o `''`), `date(mixed $value): string` (`Y-m-d` o `''`), `nextDay(string $day): string`.

- [ ] **Step 1: Scrivere il test**

`tests/App/Support/OpeningHoursTest.php` (date di riferimento: 14/09/2026 lunedì, 18 venerdì, 19 sabato, 20/12/2026 domenica, 17/08/2026 e 31/08/2026 lunedì):

```php
<?php
/** php tests/App/Support/OpeningHoursTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Support\OpeningHours;

$d = static fn (string $value): DateTimeImmutable => new DateTimeImmutable($value, new DateTimeZone('Europe/Rome'));
$h = static fn (string $openDay, string $open, string $close, string $closeDay = '', string $type = 'regular'): array => [
    'hours_type' => $type,
    'open_day' => $openDay,
    'open_time' => $open,
    'close_day' => $closeDay !== '' ? $closeDay : $openDay,
    'close_time' => $close,
];

$week = [
    $h('Mon', '15:00', '19:00'),
    $h('Mon', '09:00', '13:00'),
    $h('Fri', '22:00', '02:00', 'Sat'),
    $h('Sat', '18:00', '24:00'),
    $h('Mon', '19:00', '22:00', '', 'delivery'),
];
$ferie = [['start_date' => '2026-08-10', 'end_date' => '2026-08-25 00:00:00', 'closed' => 'true', 'open_time' => '', 'close_time' => '']];

check('più fasce nello stesso giorno, ordinate', fn () =>
    OpeningHours::periodsFor($week, [], $d('2026-09-14')) === [
        ['open' => '09:00', 'close' => '13:00', 'overnight' => false],
        ['open' => '15:00', 'close' => '19:00', 'overnight' => false],
    ]
);

check('aperto e chiuso tra le fasce', fn () =>
    OpeningHours::isOpenAt($week, [], $d('2026-09-14 10:00'))
    && !OpeningHours::isOpenAt($week, [], $d('2026-09-14 14:00'))
    && OpeningHours::isOpenAt($week, [], $d('2026-09-14 18:59'))
    && !OpeningHours::isOpenAt($week, [], $d('2026-09-14 19:00'))
);

check('chiusura il giorno dopo', fn () =>
    OpeningHours::isOpenAt($week, [], $d('2026-09-18 23:00'))
    && OpeningHours::isOpenAt($week, [], $d('2026-09-19 01:30'))
    && !OpeningHours::isOpenAt($week, [], $d('2026-09-19 02:00'))
);

check('24:00 chiude a fine giornata', fn () =>
    OpeningHours::isOpenAt($week, [], $d('2026-09-19 23:59'))
    && !OpeningHours::isOpenAt($week, [], $d('2026-09-19 17:59'))
);

check('chiusura vuota = sempre aperto', function () use ($h, $d) {
    $always = [$h('Sun', '00:00', '')];
    $timetable = OpeningHours::timetable($always);

    return OpeningHours::isOpenAt($always, [], $d('2026-09-16 03:00'))
        && count($timetable) === 7
        && $timetable['Wed'] === [['from' => '00:00', 'to' => '24:00']];
});

check('chiusura su un intervallo di date', fn () =>
    OpeningHours::periodsFor($week, $ferie, $d('2026-08-17')) === []
    && !OpeningHours::isOpenAt($week, $ferie, $d('2026-08-17 10:00'))
    && count(OpeningHours::periodsFor($week, $ferie, $d('2026-08-31'))) === 2
);

check('apertura straordinaria in un giorno chiuso', function () use ($week, $d) {
    $special = [['start_date' => '2026-12-20', 'end_date' => '', 'closed' => 'false', 'open_time' => '10:00', 'close_time' => '18:00']];

    return OpeningHours::periodsFor($week, $special, $d('2026-12-20')) === [['open' => '10:00', 'close' => '18:00', 'overnight' => false]]
        && OpeningHours::isOpenAt($week, $special, $d('2026-12-20 11:00'))
        && OpeningHours::periodsFor($week, [], $d('2026-12-20')) === [];
});

check('la chiusura prevale sull\'apertura dello stesso giorno', fn () =>
    OpeningHours::periodsFor($week, [
        ['start_date' => '2026-09-14', 'end_date' => '', 'closed' => 'false', 'open_time' => '10:00', 'close_time' => '12:00'],
        ['start_date' => '2026-09-14', 'end_date' => '', 'closed' => 'true', 'open_time' => '', 'close_time' => ''],
    ], $d('2026-09-14')) === []
);

check('apertura straordinaria oltre la mezzanotte', function () use ($d) {
    $special = [['start_date' => '2026-12-31', 'end_date' => '2027-01-01', 'closed' => 'false', 'open_time' => '20:00', 'close_time' => '03:00']];

    return OpeningHours::isOpenAt([], $special, $d('2026-12-31 23:00'))
        && OpeningHours::isOpenAt([], $special, $d('2027-01-01 02:00'))
        && !OpeningHours::isOpenAt([], $special, $d('2027-01-01 03:00'));
});

check('orari secondari separati dai regolari', fn () =>
    OpeningHours::periodsFor($week, [], $d('2026-09-14'), 'delivery') === [['open' => '19:00', 'close' => '22:00', 'overnight' => false]]
    && !OpeningHours::isOpenAt($week, [], $d('2026-09-14 20:00'))
    && OpeningHours::isOpenAt($week, [], $d('2026-09-14 20:00'), 'delivery')
);

check('le chiusure non toccano gli orari secondari', fn () =>
    OpeningHours::isOpenAt($week, $ferie, $d('2026-08-17 20:00'), 'delivery')
);

check('timetable nel formato di prettyTimeTable', fn () =>
    OpeningHours::timetable($week) === [
        'Mon' => [['from' => '09:00', 'to' => '13:00'], ['from' => '15:00', 'to' => '19:00']],
        'Fri' => [['from' => '22:00', 'to' => '02:00']],
        'Sat' => [['from' => '18:00', 'to' => '24:00']],
    ]
);

check('orari speciali da oggi in avanti, ordinati', function () use ($d) {
    $rows = OpeningHours::upcomingSpecial([
        ['start_date' => '2026-12-24', 'end_date' => '', 'closed' => 'true'],
        ['start_date' => '2026-09-01', 'end_date' => '2026-09-10', 'closed' => 'true'],
        ['start_date' => '2026-09-10', 'end_date' => '2026-09-20 00:00:00', 'closed' => 'true'],
        ['start_date' => '2026-12-20 00:00:00', 'end_date' => null, 'closed' => 'false', 'open_time' => '10:00'],
    ], $d('2026-09-17'));

    return array_column($rows, 'start_date') === ['2026-09-10', '2026-12-20', '2026-12-24']
        && $rows[0]['end_date'] === '2026-09-20'
        && $rows[1]['closed'] === false
        && $rows[2]['closed'] === true;
});

check('orari, date e giorno dopo normalizzati', fn () =>
    OpeningHours::time('9:00') === '09:00'
    && OpeningHours::time('12:00:00') === '12:00'
    && OpeningHours::time('24:00') === '24:00'
    && OpeningHours::time('24:30') === ''
    && OpeningHours::time('abc') === ''
    && OpeningHours::date('2026-09-17 10:00:00') === '2026-09-17'
    && OpeningHours::date('0000-00-00') === ''
    && OpeningHours::nextDay('Sun') === 'Mon'
    && OpeningHours::nextDay('Wed') === 'Thu'
);

summary();
```

- [ ] **Step 2: Eseguire il test e verificare che fallisca**

Run: `php tests/App/Support/OpeningHoursTest.php`
Expected: errore fatale `Class "Wonder\App\Support\OpeningHours" not found`.

- [ ] **Step 3: Implementare `OpeningHours`**

`class/App/Support/OpeningHours.php`:

```php
<?php

namespace Wonder\App\Support;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Orari di una sede sul modello di Google (`regularHours`, `specialHours`).
 *
 * Classe pura: lavora su righe già lette dal database.
 * - Orari: `hours_type`, `open_day`, `open_time`, `close_day`, `close_time`.
 * - Orari speciali: `start_date`, `end_date`, `closed`, `open_time`, `close_time`.
 *
 * `24:00` è la mezzanotte a fine giornata; una riga senza chiusura indica
 * "sempre aperto"; gli orari speciali valgono solo per il tipo `regular`.
 */
final class OpeningHours
{
    public const REGULAR = 'regular';

    public const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    /** Tipi di orari secondari di Google, in minuscolo. */
    public const SECONDARY_TYPES = [
        'drive_through',
        'happy_hour',
        'delivery',
        'takeout',
        'kitchen',
        'breakfast',
        'lunch',
        'dinner',
        'brunch',
        'pickup',
        'access',
        'senior_hours',
        'online_service_hours',
    ];

    /**
     * Fasce effettive di una data; lista vuota = chiuso.
     *
     * @return list<array{open: string, close: string, overnight: bool}>
     */
    public static function periodsFor(
        array $hours,
        array $specialHours,
        DateTimeInterface $date,
        string $type = self::REGULAR
    ): array {
        if ($type === self::REGULAR) {
            $special = self::specialPeriodsFor($specialHours, $date->format('Y-m-d'));

            if ($special !== null) {
                return $special;
            }
        }

        $rows = self::rowsOfType($hours, $type);

        if (self::alwaysOpen($rows)) {
            return [['open' => '00:00', 'close' => '24:00', 'overnight' => false]];
        }

        $weekday = $date->format('D');
        $periods = [];

        foreach ($rows as $row) {
            if ((string) ($row['open_day'] ?? '') !== $weekday) {
                continue;
            }

            $open = self::time($row['open_time'] ?? '');
            $close = self::time($row['close_time'] ?? '');
            $closeDay = trim((string) ($row['close_day'] ?? ''));

            if ($open === '' || $close === '') {
                continue;
            }

            $periods[] = [
                'open' => $open,
                'close' => $close,
                'overnight' => $closeDay !== '' && $closeDay !== $weekday,
            ];
        }

        return self::sortPeriods($periods);
    }

    public static function isOpenAt(
        array $hours,
        array $specialHours,
        DateTimeInterface $at,
        string $type = self::REGULAR
    ): bool {
        $time = $at->format('H:i');

        foreach (self::periodsFor($hours, $specialHours, $at, $type) as $period) {
            $afterOpen = $time >= $period['open'];

            if ($period['overnight'] ? $afterOpen : ($afterOpen && $time < $period['close'])) {
                return true;
            }
        }

        $yesterday = DateTimeImmutable::createFromInterface($at)->sub(new DateInterval('P1D'));

        foreach (self::periodsFor($hours, $specialHours, $yesterday, $type) as $period) {
            if ($period['overnight'] && $time < $period['close']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Orari per giorno nel formato di `prettyTimeTable()`.
     *
     * @return array<string, list<array{from: string, to: string}>>
     */
    public static function timetable(array $hours, string $type = self::REGULAR): array
    {
        $rows = self::rowsOfType($hours, $type);
        $timetable = [];

        foreach (self::DAYS as $day) {
            if (self::alwaysOpen($rows)) {
                $timetable[$day] = [['from' => '00:00', 'to' => '24:00']];
                continue;
            }

            $ranges = [];

            foreach ($rows as $row) {
                $open = self::time($row['open_time'] ?? '');
                $close = self::time($row['close_time'] ?? '');

                if ((string) ($row['open_day'] ?? '') === $day && $open !== '' && $close !== '') {
                    $ranges[] = ['from' => $open, 'to' => $close];
                }
            }

            if ($ranges !== []) {
                usort($ranges, static fn (array $a, array $b): int => strcmp($a['from'], $b['from']));
                $timetable[$day] = $ranges;
            }
        }

        return $timetable;
    }

    /**
     * Orari speciali che finiscono da `$from` in avanti, ordinati per data.
     *
     * @return list<array<string, mixed>>
     */
    public static function upcomingSpecial(array $specialHours, DateTimeInterface $from): array
    {
        $today = $from->format('Y-m-d');
        $rows = [];

        foreach ($specialHours as $row) {
            if (!is_array($row)) {
                continue;
            }

            $start = self::date($row['start_date'] ?? '');
            $end = self::date($row['end_date'] ?? '');

            if ($start === '' || ($end !== '' ? $end : $start) < $today) {
                continue;
            }

            $row['start_date'] = $start;
            $row['end_date'] = $end;
            $row['closed'] = self::isClosed($row);
            $rows[] = $row;
        }

        usort($rows, static fn (array $a, array $b): int => [$a['start_date'], (string) ($a['open_time'] ?? '')]
            <=> [$b['start_date'], (string) ($b['open_time'] ?? '')]);

        return $rows;
    }

    /** `HH:MM` da `H:i`, `H:i:s` o `24:00`; stringa vuota se non valido. */
    public static function time(mixed $value): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim((string) $value), $matches) !== 1) {
            return '';
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];

        if ($minutes > 59 || $hours > 24 || ($hours === 24 && $minutes !== 0)) {
            return '';
        }

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /** `Y-m-d` da `Y-m-d` o `Y-m-d H:i:s`; stringa vuota se non valido. */
    public static function date(mixed $value): string
    {
        $value = substr(trim((string) $value), 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && $value !== '0000-00-00' ? $value : '';
    }

    public static function nextDay(string $day): string
    {
        $index = array_search($day, self::DAYS, true);

        return $index === false ? '' : self::DAYS[($index + 1) % 7];
    }

    /** @return list<array{open: string, close: string, overnight: bool}>|null `null` se nessun orario speciale vale per la data */
    private static function specialPeriodsFor(array $specialHours, string $day): ?array
    {
        $periods = [];
        $found = false;

        foreach ($specialHours as $row) {
            if (!is_array($row)) {
                continue;
            }

            $start = self::date($row['start_date'] ?? '');
            $end = self::date($row['end_date'] ?? '');

            if ($start === '') {
                continue;
            }

            if (self::isClosed($row)) {
                if ($day >= $start && $day <= ($end !== '' ? $end : $start)) {
                    return [];
                }

                continue;
            }

            $open = self::time($row['open_time'] ?? '');
            $close = self::time($row['close_time'] ?? '');

            if ($start !== $day || $open === '' || $close === '') {
                continue;
            }

            $found = true;
            $periods[] = [
                'open' => $open,
                'close' => $close,
                'overnight' => $close !== '24:00' && $close <= $open,
            ];
        }

        return $found ? self::sortPeriods($periods) : null;
    }

    private static function isClosed(array $row): bool
    {
        return in_array(strtolower(trim((string) ($row['closed'] ?? 'true'))), ['true', '1'], true);
    }

    private static function rowsOfType(array $hours, string $type): array
    {
        return array_values(array_filter(
            $hours,
            static fn (mixed $row): bool => is_array($row)
                && ((trim((string) ($row['hours_type'] ?? '')) ?: self::REGULAR) === $type)
        ));
    }

    private static function alwaysOpen(array $rows): bool
    {
        foreach ($rows as $row) {
            if (self::time($row['open_time'] ?? '') !== '' && trim((string) ($row['close_time'] ?? '')) === '') {
                return true;
            }
        }

        return false;
    }

    private static function sortPeriods(array $periods): array
    {
        usort($periods, static fn (array $a, array $b): int => strcmp($a['open'], $b['open']));

        return $periods;
    }
}
```

- [ ] **Step 4: Eseguire il test e verificare che passi**

Run: `php -l class/App/Support/OpeningHours.php && php tests/App/Support/OpeningHoursTest.php`
Expected: `14 test, 0 falliti`.

- [ ] **Step 5: Commit**

```bash
git add class/App/Support/OpeningHours.php
git add -f tests/App/Support/OpeningHoursTest.php
git commit -m "Add Google-style opening hours calculator

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 2: Eredità dalla predefinita, link a Maps, regole della predefinita

**Files:**
- Create: `class/App/Support/SocietyLocationResolver.php`, `class/App/Support/GoogleMapsLink.php`, `class/App/Support/SocietyLocationDefaults.php`
- Test: `tests/App/Support/SocietyLocationResolverTest.php`

**Interfaces:**
- Consumes: nulla.
- Produces:
  - `SocietyLocationResolver::CONTACT_FIELDS`, `LINK_FIELDS` (`site`, `instagram`, `facebook`, `tiktok`, `linkedin`, `whatsapp`, `youtube`), `GROUPS` (`legal`, `address`, `legal_address`);
  - `SocietyLocationResolver::resolve(array $location, ?array $default): array` (aggiunge `inherited_fields`);
  - `SocietyLocationResolver::inheritedValues(array $location, ?array $default): array<string, string>`;
  - `SocietyLocationResolver::hours(array $ownHours, array $ownSpecialHours, array $defaultHours, array $defaultSpecialHours, bool $isDefault): array{hours: array, special_hours: array, inherited: bool}`;
  - `SocietyLocationResolver::isEmpty(mixed $value): bool`;
  - `GoogleMapsLink::forPlace(string $placeId, string $query = ''): string`;
  - `SocietyLocationDefaults::flagOnSave(mixed $requested, bool $otherDefaultExists): string` (`'true'`/`'false'`), `SocietyLocationDefaults::canDelete(array $row): bool`.

- [ ] **Step 1: Scrivere il test**

`tests/App/Support/SocietyLocationResolverTest.php`:

```php
<?php
/** php tests/App/Support/SocietyLocationResolverTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Support\GoogleMapsLink;
use Wonder\App\Support\SocietyLocationDefaults;
use Wonder\App\Support\SocietyLocationResolver;

$default = [
    'id' => 1, 'is_default' => 'true',
    'email' => 'info@esempio.it', 'pec' => 'pec@esempio.it', 'tel' => '02 123', 'cel' => '',
    'site' => 'https://esempio.it', 'instagram' => 'https://instagram.com/esempio',
    'name' => 'Esempio', 'legal_name' => 'Esempio srl', 'pi' => '01234567890', 'cf' => '01234567890',
    'sdi' => 'ABC1234', 'rea' => 'MI-1', 'share_capital' => '10000.00',
    'country' => 'IT', 'province' => 'MI', 'city' => 'Milano', 'cap' => '20100', 'street' => 'Via Roma',
    'number' => '1', 'more' => '', 'gmaps' => '', 'google_place_id' => 'ChIJ-milano', 'google_synced_at' => null,
    'legal_country' => 'IT', 'legal_province' => 'MI', 'legal_city' => 'Milano', 'legal_cap' => '20100',
    'legal_street' => 'Via Legale', 'legal_number' => '2', 'legal_more' => '', 'legal_gmaps' => '',
];
$branch = [
    'id' => 2, 'is_default' => 'false',
    'email' => '', 'pec' => null, 'tel' => '030 999', 'cel' => '',
    'site' => '', 'instagram' => 'https://instagram.com/negozio',
    'name' => '', 'legal_name' => '', 'pi' => '', 'cf' => '', 'sdi' => '', 'rea' => '', 'share_capital' => null,
    'country' => 'IT', 'province' => 'BS', 'city' => 'Brescia', 'cap' => '25100', 'street' => '',
    'number' => '', 'more' => '', 'gmaps' => '', 'google_place_id' => '', 'google_synced_at' => null,
    'legal_country' => 'IT', 'legal_province' => '', 'legal_city' => '', 'legal_cap' => '',
    'legal_street' => '', 'legal_number' => '', 'legal_more' => '', 'legal_gmaps' => '',
];

check('contatti e link campo per campo', function () use ($default, $branch) {
    $r = SocietyLocationResolver::resolve($branch, $default);

    return $r['tel'] === '030 999' && $r['email'] === 'info@esempio.it' && $r['pec'] === 'pec@esempio.it'
        && $r['cel'] === '' && $r['instagram'] === 'https://instagram.com/negozio' && $r['site'] === 'https://esempio.it';
});

check('dati legali e sede legale vuoti: ereditati per intero', function () use ($default, $branch) {
    $r = SocietyLocationResolver::resolve($branch, $default);

    return $r['legal_name'] === 'Esempio srl' && $r['share_capital'] === '10000.00'
        && $r['legal_street'] === 'Via Legale' && $r['legal_city'] === 'Milano';
});

check('indirizzo compilato in parte: nessun campo ereditato', function () use ($default, $branch) {
    $r = SocietyLocationResolver::resolve($branch, $default);

    return $r['city'] === 'Brescia' && $r['street'] === '' && $r['google_place_id'] === ''
        && !in_array('street', $r['inherited_fields'], true);
});

check('il solo paese non rende compilato un gruppo', function () use ($default, $branch) {
    $branch['city'] = '';
    $branch['province'] = '';
    $branch['cap'] = '';
    $r = SocietyLocationResolver::resolve($branch, $default);

    return $r['street'] === 'Via Roma' && $r['google_place_id'] === 'ChIJ-milano';
});

check('dati legali compilati in parte restano della sede', function () use ($default, $branch) {
    $branch['legal_name'] = 'Negozio Brescia srl';
    $r = SocietyLocationResolver::resolve($branch, $default);

    return $r['legal_name'] === 'Negozio Brescia srl' && $r['pi'] === '' && $r['name'] === '';
});

check('la predefinita non eredita da sé stessa', fn () =>
    SocietyLocationResolver::resolve($default, $default)['inherited_fields'] === []
    && SocietyLocationResolver::resolve($branch, null)['email'] === ''
);

check('valori ereditati per i suggerimenti del form', function () use ($default, $branch) {
    $values = SocietyLocationResolver::inheritedValues($branch, $default);

    return ($values['email'] ?? null) === 'info@esempio.it' && ($values['legal_name'] ?? null) === 'Esempio srl'
        && !array_key_exists('tel', $values) && !array_key_exists('street', $values) && !array_key_exists('cel', $values);
});

check('nuova sede: suggerimenti da tutti i gruppi della predefinita', function () use ($default) {
    $values = SocietyLocationResolver::inheritedValues([], $default);

    return ($values['street'] ?? null) === 'Via Roma' && ($values['pi'] ?? null) === '01234567890'
        && ($values['site'] ?? null) === 'https://esempio.it';
});

check('orari propri o della predefinita, con le chiusure', function () {
    $own = [['open_day' => 'Mon']];
    $ownSpecial = [['start_date' => '2026-08-10']];
    $def = [['open_day' => 'Tue']];
    $defSpecial = [['start_date' => '2026-12-25']];

    return SocietyLocationResolver::hours($own, [], $def, $defSpecial, false) === ['hours' => $own, 'special_hours' => [], 'inherited' => false]
        && SocietyLocationResolver::hours([], $ownSpecial, $def, $defSpecial, false) === ['hours' => $def, 'special_hours' => $defSpecial, 'inherited' => true]
        && SocietyLocationResolver::hours([], $ownSpecial, $def, $defSpecial, true) === ['hours' => [], 'special_hours' => $ownSpecial, 'inherited' => false];
});

check('link a Google Maps dal Place ID', fn () =>
    GoogleMapsLink::forPlace('ChIJ-milano', 'Via Roma 1, Milano') === 'https://www.google.com/maps/search/?api=1&query=Via%20Roma%201%2C%20Milano&query_place_id=ChIJ-milano'
    && GoogleMapsLink::forPlace('ChIJ-x') === 'https://www.google.com/maps/search/?api=1&query=ChIJ-x&query_place_id=ChIJ-x'
    && GoogleMapsLink::forPlace('  ') === ''
);

check('una sola predefinita, mai eliminabile', fn () =>
    SocietyLocationDefaults::flagOnSave('false', false) === 'true'
    && SocietyLocationDefaults::flagOnSave('true', true) === 'true'
    && SocietyLocationDefaults::flagOnSave('false', true) === 'false'
    && SocietyLocationDefaults::flagOnSave(null, true) === 'false'
    && !SocietyLocationDefaults::canDelete(['is_default' => 'true'])
    && SocietyLocationDefaults::canDelete(['is_default' => 'false'])
);

summary();
```

- [ ] **Step 2: Eseguire il test e verificare che fallisca**

Run: `php tests/App/Support/SocietyLocationResolverTest.php`
Expected: errore fatale `Class "Wonder\App\Support\SocietyLocationResolver" not found`.

- [ ] **Step 3: Implementare le tre classi**

`class/App/Support/SocietyLocationResolver.php`:

```php
<?php

namespace Wonder\App\Support;

/**
 * Completa una sede con i dati della sede predefinita.
 *
 * - Contatti e link: campo per campo.
 * - Dati aziendali e legali, indirizzo (con Place ID), sede legale: per gruppo
 *   intero, solo se il gruppo della sede è tutto vuoto, così non si mescolano
 *   dati di sedi diverse.
 * - Orari: quelli della predefinita, con i suoi orari speciali, solo se la sede
 *   non ha orari propri.
 */
final class SocietyLocationResolver
{
    public const CONTACT_FIELDS = ['email', 'pec', 'tel', 'cel'];

    public const LINK_FIELDS = ['site', 'instagram', 'facebook', 'tiktok', 'linkedin', 'whatsapp', 'youtube'];

    public const GROUPS = [
        'legal' => ['name', 'legal_name', 'pi', 'cf', 'sdi', 'rea', 'share_capital'],
        'address' => ['country', 'province', 'city', 'cap', 'street', 'number', 'more', 'gmaps', 'google_place_id', 'google_synced_at'],
        'legal_address' => ['legal_country', 'legal_province', 'legal_city', 'legal_cap', 'legal_street', 'legal_number', 'legal_more', 'legal_gmaps'],
    ];

    /** Campi che da soli non rendono compilato un gruppo: valori di default del form e date tecniche. */
    private const NOT_SIGNIFICANT = ['country', 'legal_country', 'google_synced_at'];

    public static function resolve(array $location, ?array $default): array
    {
        $location['inherited_fields'] = [];

        if ($default === null || self::isSameLocation($location, $default)) {
            return $location;
        }

        $inherited = [];

        foreach (array_merge(self::CONTACT_FIELDS, self::LINK_FIELDS) as $field) {
            if (self::isEmpty($location[$field] ?? null) && !self::isEmpty($default[$field] ?? null)) {
                $location[$field] = $default[$field];
                $inherited[] = $field;
            }
        }

        foreach (self::GROUPS as $fields) {
            if (!self::isGroupEmpty($location, $fields) || self::isGroupEmpty($default, $fields)) {
                continue;
            }

            foreach ($fields as $field) {
                $location[$field] = $default[$field] ?? null;
                $inherited[] = $field;
            }
        }

        $location['inherited_fields'] = $inherited;

        return $location;
    }

    /**
     * Valori presi dalla predefinita e non vuoti, da mostrare come suggerimento.
     *
     * @return array<string, string>
     */
    public static function inheritedValues(array $location, ?array $default): array
    {
        $resolved = self::resolve($location, $default);
        $values = [];

        foreach ($resolved['inherited_fields'] as $field) {
            if (!self::isEmpty($resolved[$field] ?? null)) {
                $values[$field] = (string) $resolved[$field];
            }
        }

        return $values;
    }

    /**
     * @return array{hours: array, special_hours: array, inherited: bool}
     */
    public static function hours(
        array $ownHours,
        array $ownSpecialHours,
        array $defaultHours,
        array $defaultSpecialHours,
        bool $isDefault
    ): array {
        if ($isDefault || $ownHours !== []) {
            return ['hours' => $ownHours, 'special_hours' => $ownSpecialHours, 'inherited' => false];
        }

        return [
            'hours' => $defaultHours,
            'special_hours' => $defaultSpecialHours,
            'inherited' => $defaultHours !== [] || $defaultSpecialHours !== [],
        ];
    }

    public static function isEmpty(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }

    private static function isGroupEmpty(array $row, array $fields): bool
    {
        foreach ($fields as $field) {
            if (!in_array($field, self::NOT_SIGNIFICANT, true) && !self::isEmpty($row[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private static function isSameLocation(array $location, array $default): bool
    {
        $id = trim((string) ($location['id'] ?? ''));

        return $id !== '' && $id === trim((string) ($default['id'] ?? ''));
    }
}
```

`class/App/Support/GoogleMapsLink.php`:

```php
<?php

namespace Wonder\App\Support;

/**
 * Link a Google Maps dal Place ID, senza chiave API (Maps URLs).
 */
final class GoogleMapsLink
{
    public static function forPlace(string $placeId, string $query = ''): string
    {
        $placeId = trim($placeId);

        if ($placeId === '') {
            return '';
        }

        $query = trim($query);

        return 'https://www.google.com/maps/search/?'.http_build_query([
            'api' => 1,
            'query' => $query !== '' ? $query : $placeId,
            'query_place_id' => $placeId,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
```

`class/App/Support/SocietyLocationDefaults.php`:

```php
<?php

namespace Wonder\App\Support;

/**
 * Regole della sede predefinita: sempre una sola, la prima sede lo diventa,
 * non si può eliminare.
 */
final class SocietyLocationDefaults
{
    /** Valore di `is_default` da salvare: senza un'altra predefinita la sede lo diventa. */
    public static function flagOnSave(mixed $requested, bool $otherDefaultExists): string
    {
        return !$otherDefaultExists || (string) $requested === 'true' ? 'true' : 'false';
    }

    public static function canDelete(array $row): bool
    {
        return ($row['is_default'] ?? '') !== 'true';
    }
}
```

- [ ] **Step 4: Eseguire il test e verificare che passi**

Run: `for f in class/App/Support/SocietyLocationResolver.php class/App/Support/GoogleMapsLink.php class/App/Support/SocietyLocationDefaults.php; do php -l $f; done && php tests/App/Support/SocietyLocationResolverTest.php`
Expected: `11 test, 0 falliti`.

- [ ] **Step 5: Commit**

```bash
git add class/App/Support/SocietyLocationResolver.php class/App/Support/GoogleMapsLink.php class/App/Support/SocietyLocationDefaults.php
git add -f tests/App/Support/SocietyLocationResolverTest.php
git commit -m "Add society location inheritance rules and Maps link

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 3: Model delle sedi, degli orari e degli orari speciali

**Files:**
- Create: `class/App/Models/Config/SocietyLocation.php`, `class/App/Models/Config/SocietyLocationHour.php`, `class/App/Models/Config/SocietyLocationSpecialHour.php`
- Modify: `class/App/Models/Config/Society.php`, `SocietyAddress.php`, `SocietyLegalAddress.php`, `SocietySocial.php`, `SocietyTimetable.php` (via `syncSchema()` e l'import di `SyncSchema`), `app/build/row/society.php`
- Test: `tests/App/SocietyLocationModelsTest.php`

**Interfaces:**
- Consumes: `OpeningHours::DAYS`, `OpeningHours::REGULAR` (Task 1).
- Produces:
  - `SocietyLocation::$table = 'society_locations'`, `SocietyLocation::BUSINESS_STATUSES`, `SocietyLocation::address(): AddressExtension`, `SocietyLocation::legalAddress(): AddressExtension`;
  - `SocietyLocationHour::$table = 'society_location_hours'`;
  - `SocietyLocationSpecialHour::$table = 'society_location_special_hours'`, `SocietyLocationSpecialHour::SOURCES = ['manual', 'google']`.

- [ ] **Step 1: Scrivere il test**

`tests/App/SocietyLocationModelsTest.php`:

```php
<?php
/** php tests/App/SocietyLocationModelsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Models\Config\Society;
use Wonder\App\Models\Config\SocietyAddress;
use Wonder\App\Models\Config\SocietyLegalAddress;
use Wonder\App\Models\Config\SocietyLocation;
use Wonder\App\Models\Config\SocietyLocationHour;
use Wonder\App\Models\Config\SocietyLocationSpecialHour;
use Wonder\App\Models\Config\SocietySocial;
use Wonder\App\Models\Config\SocietyTimetable;

check('sedi sincronizzate con id stabili, modificabili ovunque', function () {
    $schema = SocietyLocation::syncSchema();

    return SocietyLocation::$table === 'society_locations'
        && $schema !== null && $schema->keepIds && !$schema->localOnly && !$schema->singleton;
});

check('colonne della sede per gruppo', function () {
    $columns = array_keys(SocietyLocation::getColumns());
    $expected = [
        'slug', 'label', 'is_default', 'visible', 'position', 'business_status', 'opening_date',
        'country', 'province', 'city', 'cap', 'street', 'number', 'more', 'gmaps', 'google_place_id', 'google_synced_at',
        'email', 'pec', 'tel', 'cel',
        'name', 'legal_name', 'pi', 'cf', 'sdi', 'rea', 'share_capital',
        'legal_country', 'legal_province', 'legal_city', 'legal_cap', 'legal_street', 'legal_number', 'legal_more', 'legal_gmaps',
        'site', 'instagram', 'facebook', 'tiktok', 'linkedin', 'whatsapp', 'youtube',
    ];
    $missing = array_values(array_diff($expected, $columns));

    if ($missing !== []) {
        echo '    mancano: '.implode(', ', $missing)."\n";
    }

    return $missing === [];
});

check('orari e orari speciali non sincronizzati, legati alla sede', function () {
    $hours = SocietyLocationHour::getColumns();
    $special = SocietyLocationSpecialHour::getColumns();

    return SocietyLocationHour::syncSchema() === null
        && SocietyLocationSpecialHour::syncSchema() === null
        && ($hours['society_location_id']['foreign_table'] ?? null) === 'society_locations'
        && ($special['society_location_id']['foreign_table'] ?? null) === 'society_locations'
        && array_diff(['hours_type', 'open_day', 'open_time', 'close_day', 'close_time', 'position'], array_keys($hours)) === []
        && array_diff(['start_date', 'end_date', 'closed', 'open_time', 'close_time', 'note', 'source'], array_keys($special)) === [];
});

check('vecchie tabelle non più sincronizzate', fn () =>
    Society::syncSchema() === null
    && SocietyAddress::syncSchema() === null
    && SocietyLegalAddress::syncSchema() === null
    && SocietySocial::syncSchema() === null
    && SocietyTimetable::syncSchema() === null
);

summary();
```

- [ ] **Step 2: Eseguire il test e verificare che fallisca**

Run: `php tests/App/SocietyLocationModelsTest.php`
Expected: errore fatale `Class "Wonder\App\Models\Config\SocietyLocation" not found`.

- [ ] **Step 3: Creare i Model**

`class/App/Models/Config/SocietyLocation.php`:

```php
<?php

namespace Wonder\App\Models\Config;

use Wonder\App\Model;
use Wonder\App\Schema\Extensions\AddressExtension;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Sede della società: dati, indirizzo con Google Place ID, contatti, dati
 * aziendali e legali, sede legale e link. Una sola sede è predefinita; le
 * altre prendono dalla predefinita ciò che manca
 * (`Wonder\App\Support\SocietyLocationResolver`).
 */
final class SocietyLocation extends Model
{
    public const BUSINESS_STATUSES = ['operational', 'closed_temporarily', 'closed_permanently', 'future_opening'];

    public static string $table = 'society_locations';
    public static string $folder = 'app/config/corporate-data';
    public static string $icon = 'bi bi-buildings';

    public static function syncSchema(): ?SyncSchema
    {
        return SyncSchema::multiRow()->keepIds();
    }

    public static function address(): AddressExtension
    {
        return AddressExtension::simple(linkKey: 'gmaps');
    }

    public static function legalAddress(): AddressExtension
    {
        return AddressExtension::simple(prefix: 'legal', linkKey: 'gmaps');
    }

    public static function tableSchema(): array
    {
        return [
            Column::key('slug')->length(100)->unique(),
            Column::key('label'),
            Column::key('is_default')->enum(['true', 'false'])->default('false'),
            Column::key('visible')->enum(['true', 'false'])->default('true'),
            Column::key('position')->int(),
            Column::key('business_status')->enum(self::BUSINESS_STATUSES)->default('operational'),
            ...static::sqlColumnsFromDataSchema(['opening_date']),
            ...static::address()->tableSchema(),
            Column::key('google_place_id'),
            ...static::sqlColumnsFromDataSchema([
                'google_synced_at',
                'email',
                'pec',
                'tel',
                'cel',
                'name',
                'legal_name',
                'pi',
                'cf',
                'sdi',
                'rea',
                'share_capital',
            ]),
            ...static::legalAddress()->tableSchema(),
            ...static::sqlColumnsFromDataSchema([
                'site',
                'instagram',
                'facebook',
                'tiktok',
                'linkedin',
                'whatsapp',
                'youtube',
            ]),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('slug')->text()->slug(),
            Field::key('label')->text()->required(),
            Field::key('is_default')->text()->sanitize(false),
            Field::key('visible')->text()->sanitize(false),
            Field::key('business_status')->text()->sanitize(false),
            Field::key('opening_date')->date(),
            ...static::address()->dataSchema(),
            Field::key('google_place_id')->text()->sanitize(false),
            Field::key('google_synced_at')->date(),
            Field::key('email')->email(),
            Field::key('pec')->text(),
            Field::key('tel')->text(),
            Field::key('cel')->text(),
            Field::key('name')->text(),
            Field::key('legal_name')->text(),
            Field::key('pi')->text(),
            Field::key('cf')->tin(),
            Field::key('sdi')->text()->upper(),
            Field::key('rea')->text(),
            Field::key('share_capital')->number()->decimals(2),
            ...static::legalAddress()->dataSchema(),
            Field::key('site')->text(),
            Field::key('instagram')->text(),
            Field::key('facebook')->text(),
            Field::key('tiktok')->text(),
            Field::key('linkedin')->text(),
            Field::key('whatsapp')->text(),
            Field::key('youtube')->text(),
        ];
    }
}
```

`class/App/Models/Config/SocietyLocationHour.php`:

```php
<?php

namespace Wonder\App\Models\Config;

use Wonder\App\Model;
use Wonder\App\Support\OpeningHours;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Orari regolari e secondari di una sede, come `regularHours.periods` di
 * Google: più fasce nello stesso giorno sono più righe. Dati di produzione,
 * non sincronizzati.
 */
final class SocietyLocationHour extends Model
{
    public static string $table = 'society_location_hours';
    public static string $folder = 'app/config/opening-hours';
    public static string $icon = 'bi bi-clock';

    public static function tableSchema(): array
    {
        return [
            Column::key('society_location_id')->int()->null(false)->foreign('society_locations'),
            Column::key('hours_type')->length(30)->default(OpeningHours::REGULAR),
            Column::key('open_day')->enum(OpeningHours::DAYS)->null(false),
            Column::key('open_time')->length(5)->null(false),
            Column::key('close_day')->enum(OpeningHours::DAYS),
            Column::key('close_time')->length(5),
            Column::key('position')->int(),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('society_location_id')->number(),
            Field::key('hours_type')->text()->sanitize(false),
            Field::key('open_day')->text()->sanitize(false),
            Field::key('open_time')->text()->sanitize(false),
            Field::key('close_day')->text()->sanitize(false),
            Field::key('close_time')->text()->sanitize(false),
        ];
    }
}
```

`class/App/Models/Config/SocietyLocationSpecialHour.php`:

```php
<?php

namespace Wonder\App\Models\Config;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Orari speciali e chiusure di una sede, come `specialHours` di Google.
 * `closed = true` vale anche su un intervallo di date; un'apertura
 * straordinaria dura al massimo fino al giorno dopo. Dati di produzione,
 * non sincronizzati.
 */
final class SocietyLocationSpecialHour extends Model
{
    public const SOURCES = ['manual', 'google'];

    public static string $table = 'society_location_special_hours';
    public static string $folder = 'app/config/opening-hours';
    public static string $icon = 'bi bi-calendar-x';

    public static function tableSchema(): array
    {
        return [
            Column::key('society_location_id')->int()->null(false)->foreign('society_locations'),
            ...static::sqlColumnsFromDataSchema(['start_date', 'end_date']),
            Column::key('closed')->enum(['true', 'false'])->default('true'),
            Column::key('open_time')->length(5),
            Column::key('close_time')->length(5),
            Column::key('note'),
            Column::key('source')->enum(self::SOURCES)->default('manual'),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('society_location_id')->number(),
            Field::key('start_date')->date(),
            Field::key('end_date')->date(),
            Field::key('closed')->text()->sanitize(false),
            Field::key('open_time')->text()->sanitize(false),
            Field::key('close_time')->text()->sanitize(false),
            Field::key('note')->text(),
            Field::key('source')->text()->sanitize(false),
        ];
    }
}
```

- [ ] **Step 4: Togliere il sync dalle vecchie tabelle e le righe vuote del seed**

In `Society.php`, `SocietyAddress.php`, `SocietyLegalAddress.php`, `SocietySocial.php` e `SocietyTimetable.php` elimina il metodo:

```php
    public static function syncSchema(): ?SyncSchema
    {
        return SyncSchema::singleton();
    }
```

(in `SocietyTimetable.php` il corpo è `return SyncSchema::multiRow();`) e la riga `use Wonder\App\Support\SyncSchema;`. Sopra la dichiarazione della classe aggiungi il docblock:

```php
/**
 * Tabella dei vecchi dati aziendali: non più scritta né sincronizzata.
 * Letta solo dalla migrazione verso `society_locations`; verrà rimossa.
 */
```

Sostituisci il contenuto di `app/build/row/society.php` con:

```php
<?php

    if (!sqlSelect('logos', ['id' => 1], 1)->exists) {

        sqlInsert('logos', ['id' => 1]);

    }
```

- [ ] **Step 5: Eseguire il test e verificare che passi**

Run: `for f in class/App/Models/Config/Society*.php app/build/row/society.php; do php -l $f; done && php tests/App/SocietyLocationModelsTest.php`
Expected: nessun errore di sintassi, `4 test, 0 falliti`.

- [ ] **Step 6: Commit**

```bash
git add class/App/Models/Config app/build/row/society.php
git add -f tests/App/SocietyLocationModelsTest.php
git commit -m "Add society location, hours and special hours models

Legacy society tables stop being synced.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 4: Migrazione dei vecchi dati aziendali in `forge update`

**Files:**
- Create: `class/App/Support/LegacySocietyMapper.php`, `class/App/Support/SocietyLocationsMigration.php`
- Modify: `class/App/UpdateRunner.php`
- Test: `tests/App/Support/LegacySocietyMapperTest.php`

**Interfaces:**
- Consumes: `OpeningHours::time()`, `OpeningHours::nextDay()`, `OpeningHours::DAYS`, `OpeningHours::REGULAR` (Task 1); `SocietyLocation`, `SocietyLocationHour` (Task 3); `Wonder\Sql\Transaction::run()` (piano 2).
- Produces:
  - `LegacySocietyMapper::location(array $society, array $address, array $legalAddress, array $social, array $columns): array`;
  - `LegacySocietyMapper::hours(array $timetableRows, ?string $timetableJson): list<array>` (righe con `hours_type`, `open_day`, `open_time`, `close_day`, `close_time`, `position`);
  - `SocietyLocationsMigration::runIfNeeded(): bool`, `SocietyLocationsMigration::legacyLocation(): array`, `SocietyLocationsMigration::legacyHours(): array`;
  - `UpdateRunner` → `stats.society_locations` (bool).

- [ ] **Step 1: Scrivere il test**

`tests/App/Support/LegacySocietyMapperTest.php`:

```php
<?php
/** php tests/App/Support/LegacySocietyMapperTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Support\LegacySocietyMapper;

check('sede predefinita dalle vecchie tabelle, solo colonne esistenti', fn () =>
    LegacySocietyMapper::location(
        ['id' => 1, 'name' => 'Esempio', 'email' => 'info@esempio.it', 'deleted' => 'false', 'creation' => '2025-01-01 10:00:00', 'custom_column' => 'x'],
        ['id' => 1, 'street' => 'Via Roma', 'city' => 'Milano', 'timetable' => '{}'],
        ['id' => 1, 'legal_street' => 'Via Legale'],
        ['id' => 1, 'site' => 'https://esempio.it', 'instagram' => ''],
        ['slug', 'label', 'is_default', 'visible', 'position', 'business_status', 'name', 'email', 'street', 'city', 'legal_street', 'instagram', 'site']
    ) === [
        'slug' => 'sede-principale',
        'label' => 'Sede principale',
        'is_default' => 'true',
        'visible' => 'true',
        'position' => 1,
        'business_status' => 'operational',
        'name' => 'Esempio',
        'email' => 'info@esempio.it',
        'street' => 'Via Roma',
        'city' => 'Milano',
        'legal_street' => 'Via Legale',
        'site' => 'https://esempio.it',
        'instagram' => '',
    ]
);

check('orari da society_timetable, righe cancellate escluse, JSON ignorato', fn () =>
    LegacySocietyMapper::hours([
        ['day' => 'Mon', 'from_time' => '09:00', 'to_time' => '13:00', 'deleted' => 'false'],
        ['day' => 'Mon', 'from_time' => '15:00', 'to_time' => '19:00', 'deleted' => 'false'],
        ['day' => 'Tue', 'from_time' => '09:00', 'to_time' => '13:00', 'deleted' => 'true'],
    ], '{"Wed":[{"from":"08:00","to":"12:00"}]}') === [
        ['hours_type' => 'regular', 'open_day' => 'Mon', 'open_time' => '09:00', 'close_day' => 'Mon', 'close_time' => '13:00', 'position' => 0],
        ['hours_type' => 'regular', 'open_day' => 'Mon', 'open_time' => '15:00', 'close_day' => 'Mon', 'close_time' => '19:00', 'position' => 1],
    ]
);

check('orari dal JSON se la tabella è vuota; mezzanotte e giorno dopo', fn () =>
    LegacySocietyMapper::hours([], '{"Wed":[{"from":"8:00","to":"12:00"}],"Fri":[{"from":"20:00","to":"00:00"},{"from":"22:00","to":"02:00"}]}') === [
        ['hours_type' => 'regular', 'open_day' => 'Wed', 'open_time' => '08:00', 'close_day' => 'Wed', 'close_time' => '12:00', 'position' => 0],
        ['hours_type' => 'regular', 'open_day' => 'Fri', 'open_time' => '20:00', 'close_day' => 'Fri', 'close_time' => '24:00', 'position' => 1],
        ['hours_type' => 'regular', 'open_day' => 'Fri', 'open_time' => '22:00', 'close_day' => 'Sat', 'close_time' => '02:00', 'position' => 2],
    ]
);

check('righe non valide e JSON non valido ignorati', fn () =>
    LegacySocietyMapper::hours([
        ['day' => 'Xyz', 'from_time' => '09:00', 'to_time' => '10:00'],
        ['day' => 'Mon', 'from_time' => '', 'to_time' => '10:00'],
    ], null) === []
    && LegacySocietyMapper::hours([], 'non json') === []
);

summary();
```

- [ ] **Step 2: Eseguire il test e verificare che fallisca**

Run: `php tests/App/Support/LegacySocietyMapperTest.php`
Expected: errore fatale `Class "Wonder\App\Support\LegacySocietyMapper" not found`.

- [ ] **Step 3: Implementare il mapper**

`class/App/Support/LegacySocietyMapper.php`:

```php
<?php

namespace Wonder\App\Support;

/**
 * Converte i vecchi dati aziendali a riga unica (`society`, `society_address`,
 * `society_legal_address`, `society_social`, `society_timetable`) nella sede
 * predefinita e nei suoi orari regolari. Classe pura.
 */
final class LegacySocietyMapper
{
    private const SKIPPED_COLUMNS = ['id', 'deleted', 'creation', 'last_modified', 'timetable'];

    /**
     * @param list<string> $columns colonne di `society_locations`
     * @return array<string, mixed>
     */
    public static function location(array $society, array $address, array $legalAddress, array $social, array $columns): array
    {
        $location = [
            'slug' => 'sede-principale',
            'label' => 'Sede principale',
            'is_default' => 'true',
            'visible' => 'true',
            'position' => 1,
            'business_status' => 'operational',
        ];

        foreach ([$society, $address, $legalAddress, $social] as $row) {
            foreach ($row as $column => $value) {
                if (!is_string($column) || in_array($column, self::SKIPPED_COLUMNS, true) || !in_array($column, $columns, true)) {
                    continue;
                }

                $location[$column] = $value;
            }
        }

        return $location;
    }

    /**
     * Orari da `society_timetable`; se non ci sono righe valide, dal JSON
     * `society_address.timetable`. `00:00` di chiusura diventa `24:00`; una
     * chiusura prima dell'apertura passa al giorno dopo.
     *
     * @return list<array<string, mixed>>
     */
    public static function hours(array $timetableRows, ?string $timetableJson): array
    {
        $ranges = [];

        foreach ($timetableRows as $row) {
            if (is_array($row) && ($row['deleted'] ?? 'false') !== 'true') {
                $ranges[] = [(string) ($row['day'] ?? ''), (string) ($row['from_time'] ?? ''), (string) ($row['to_time'] ?? '')];
            }
        }

        if ($ranges === []) {
            $decoded = json_decode((string) $timetableJson, true);

            foreach (is_array($decoded) ? $decoded : [] as $day => $items) {
                foreach (is_array($items) ? $items : [] as $item) {
                    if (is_array($item)) {
                        $ranges[] = [(string) $day, (string) ($item['from'] ?? ''), (string) ($item['to'] ?? '')];
                    }
                }
            }
        }

        $hours = [];

        foreach ($ranges as [$day, $from, $to]) {
            $open = OpeningHours::time($from);
            $close = OpeningHours::time($to);

            if (!in_array($day, OpeningHours::DAYS, true) || $open === '' || $close === '') {
                continue;
            }

            $closeDay = $day;

            if ($close === '00:00') {
                $close = '24:00';
            } elseif ($close <= $open) {
                $closeDay = OpeningHours::nextDay($day);
            }

            $hours[] = [
                'hours_type' => OpeningHours::REGULAR,
                'open_day' => $day,
                'open_time' => $open,
                'close_day' => $closeDay,
                'close_time' => $close,
                'position' => count($hours),
            ];
        }

        return $hours;
    }
}
```

- [ ] **Step 4: Eseguire il test e verificare che passi**

Run: `php -l class/App/Support/LegacySocietyMapper.php && php tests/App/Support/LegacySocietyMapperTest.php`
Expected: `4 test, 0 falliti`.

- [ ] **Step 5: Migrazione e passo di `UpdateRunner`**

`class/App/Support/SocietyLocationsMigration.php`:

```php
<?php

namespace Wonder\App\Support;

use RuntimeException;
use Wonder\App\Models\Config\SocietyLocation;
use Wonder\App\Models\Config\SocietyLocationHour;
use Wonder\Sql\Transaction;

/**
 * Crea la sede predefinita (`id = 1`) dai vecchi dati aziendali, una sola
 * volta: solo se `society_locations` non ha righe, nemmeno cancellate.
 */
final class SocietyLocationsMigration
{
    public static function runIfNeeded(): bool
    {
        if (!sqlTableExists(SocietyLocation::$table) || sqlSelect(SocietyLocation::$table, null, 1)->exists) {
            return false;
        }

        $location = self::legacyLocation();
        $hours = self::legacyHours();

        Transaction::run(static function () use ($location, $hours): void {
            if (empty(sqlInsert(SocietyLocation::$table, array_merge($location, ['id' => 1]))->success)) {
                throw new RuntimeException('Migrazione dati aziendali: sede predefinita non creata.');
            }

            foreach ($hours as $row) {
                if (empty(sqlInsert(SocietyLocationHour::$table, array_merge($row, ['society_location_id' => 1]))->success)) {
                    throw new RuntimeException('Migrazione dati aziendali: orari non copiati.');
                }
            }
        });

        return true;
    }

    /** Sede predefinita dalle vecchie tabelle (senza `id`). */
    public static function legacyLocation(): array
    {
        return LegacySocietyMapper::location(
            self::legacyRow('society'),
            self::legacyRow('society_address'),
            self::legacyRow('society_legal_address'),
            self::legacyRow('society_social'),
            array_keys(SocietyLocation::getColumns())
        );
    }

    /** Orari regolari dalle vecchie tabelle (senza `society_location_id`). */
    public static function legacyHours(): array
    {
        $timetable = sqlTableExists('society_timetable')
            ? (array) sqlSelect('society_timetable', ['society_address_id' => 1, 'deleted' => 'false'], null, 'position', 'ASC')->row
            : [];

        return LegacySocietyMapper::hours($timetable, (string) (self::legacyRow('society_address')['timetable'] ?? ''));
    }

    private static function legacyRow(string $table): array
    {
        if (!sqlTableExists($table)) {
            return [];
        }

        $row = sqlSelect($table, ['id' => 1], 1)->row;

        return is_array($row) ? $row : [];
    }
}
```

In `class/App/UpdateRunner.php`:

1. aggiungi `use Wonder\App\Support\SocietyLocationsMigration;` dopo `use Wonder\App\Support\DefaultRows;`;
2. in `stats`, dopo `'rows' => 0,` aggiungi `'society_locations' => false,`;
3. dopo `$result->stats->rows = $this->runFiles($this->rowDirectories());` aggiungi:

```php
            $result->stats->society_locations = SocietyLocationsMigration::runIfNeeded();
```

- [ ] **Step 6: Verificare sintassi e test**

Run: `php -l class/App/Support/SocietyLocationsMigration.php && php -l class/App/UpdateRunner.php && php tests/App/Support/LegacySocietyMapperTest.php`
Expected: nessun errore, `4 test, 0 falliti`. La migrazione vera si verifica con il database nel Task 10.

- [ ] **Step 7: Commit**

```bash
git add class/App/Support/LegacySocietyMapper.php class/App/Support/SocietyLocationsMigration.php class/App/UpdateRunner.php
git add -f tests/App/Support/LegacySocietyMapperTest.php
git commit -m "Migrate legacy society data to the default location on update

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 5: Lettura delle sedi e `infoSociety()` per sede

**Files:**
- Create: `class/App/Support/SocietyLocations.php`
- Modify: `app/function/info.php` (funzione `infoSociety`, nuova `infoSocietyLocations`), `app/service/lang.php`, `class/App/Support/SocietyLocationsMigration.php`
- Test: `tests/App/Support/SocietyLocationsTest.php`

**Interfaces:**
- Consumes: `SocietyLocationResolver` (Task 2), `OpeningHours` (Task 1), Model (Task 3), `SocietyLocationsMigration::legacyLocation()`/`legacyHours()` (Task 4).
- Produces:
  - `SocietyLocations::default(): object`, `find(int|string $idOrSlug): ?object`, `all(bool $onlyVisible = true): list<object>`, `hoursFor(object $location, DateTimeInterface $date): array`, `isOpen(object $location, ?DateTimeInterface $at = null): bool`, `reset(): void`;
  - `SocietyLocations::assemble(array $locationRows, array $hourRows, array $specialRows): array{default_id: int, locations: array<int, object>}`;
  - oggetto sede: colonne di `society_locations` già completate, `inherited_fields`, `hours`, `specialHours`, `hoursInherited`;
  - `infoSociety(int|string|null $location = null): object`, `infoSocietyLocations(): array`.

- [ ] **Step 1: Scrivere il test**

`tests/App/Support/SocietyLocationsTest.php`:

```php
<?php
/** php tests/App/Support/SocietyLocationsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Support\SocietyLocations;

$rows = [
    ['id' => 2, 'slug' => 'brescia', 'label' => 'Brescia', 'is_default' => 'false', 'visible' => 'true', 'position' => 2, 'email' => '', 'tel' => '030 1'],
    ['id' => 1, 'slug' => 'sede-principale', 'label' => 'Sede principale', 'is_default' => 'true', 'visible' => 'true', 'position' => 1, 'email' => 'info@esempio.it', 'tel' => '02 1'],
    ['id' => 3, 'slug' => 'nascosta', 'label' => 'Nascosta', 'is_default' => 'false', 'visible' => 'false', 'position' => 3, 'email' => 'n@esempio.it', 'tel' => ''],
];
$hours = [
    ['society_location_id' => 1, 'hours_type' => 'regular', 'open_day' => 'Mon', 'open_time' => '09:00', 'close_day' => 'Mon', 'close_time' => '18:00', 'position' => 0],
    ['society_location_id' => 3, 'hours_type' => 'regular', 'open_day' => 'Tue', 'open_time' => '10:00', 'close_day' => 'Tue', 'close_time' => '12:00', 'position' => 0],
];
$special = [
    ['society_location_id' => 1, 'start_date' => '2026-12-25', 'end_date' => '', 'closed' => 'true'],
    ['society_location_id' => 3, 'start_date' => '2026-12-26', 'end_date' => '', 'closed' => 'true', 'deleted' => 'true'],
];

check('predefinita dal flag, sedi ordinate per posizione', function () use ($rows, $hours, $special) {
    $data = SocietyLocations::assemble($rows, $hours, $special);

    return $data['default_id'] === 1 && array_keys($data['locations']) === [1, 2, 3];
});

check('senza flag la predefinita è la prima per posizione', function () use ($rows) {
    $rows = array_map(static fn (array $row): array => ['is_default' => 'false'] + $row, $rows);
    $data = SocietyLocations::assemble($rows, [], []);

    return $data['default_id'] === 1
        && $data['locations'][1]->is_default === 'true'
        && $data['locations'][2]->is_default === 'false';
});

check('contatti e orari con le chiusure presi dalla predefinita', function () use ($rows, $hours, $special) {
    $brescia = SocietyLocations::assemble($rows, $hours, $special)['locations'][2];

    return $brescia->email === 'info@esempio.it' && $brescia->tel === '030 1'
        && $brescia->hoursInherited === true
        && count($brescia->hours) === 1 && $brescia->hours[0]['open_day'] === 'Mon'
        && count($brescia->specialHours) === 1;
});

check('orari propri e righe cancellate ignorate', function () use ($rows, $hours, $special) {
    $nascosta = SocietyLocations::assemble($rows, $hours, $special)['locations'][3];

    return $nascosta->hoursInherited === false
        && $nascosta->hours[0]['open_day'] === 'Tue'
        && $nascosta->specialHours === [];
});

check('nessuna sede', fn () => SocietyLocations::assemble([], [], []) === ['default_id' => 0, 'locations' => []]);

summary();
```

- [ ] **Step 2: Eseguire il test e verificare che fallisca**

Run: `php tests/App/Support/SocietyLocationsTest.php`
Expected: errore fatale `Class "Wonder\App\Support\SocietyLocations" not found`.

- [ ] **Step 3: Implementare `SocietyLocations`**

`class/App/Support/SocietyLocations.php`:

```php
<?php

namespace Wonder\App\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Wonder\App\Models\Config\SocietyLocation;
use Wonder\App\Models\Config\SocietyLocationHour;
use Wonder\App\Models\Config\SocietyLocationSpecialHour;

/**
 * Sedi della società già completate dall'eredità, lette una volta per
 * richiesta. Prima della migrazione legge i vecchi dati aziendali come
 * un'unica sede.
 */
final class SocietyLocations
{
    /** @var array{default_id: int, locations: array<int, object>}|null */
    private static ?array $cache = null;

    public static function default(): object
    {
        $data = self::data();

        return $data['locations'][$data['default_id']] ?? self::emptyLocation();
    }

    public static function find(int|string $idOrSlug): ?object
    {
        $key = trim((string) $idOrSlug);
        $locations = self::data()['locations'];

        if ($key === '') {
            return null;
        }

        if (ctype_digit($key)) {
            return $locations[(int) $key] ?? null;
        }

        foreach ($locations as $location) {
            if ((string) ($location->slug ?? '') === $key) {
                return $location;
            }
        }

        return null;
    }

    /** @return list<object> */
    public static function all(bool $onlyVisible = true): array
    {
        return array_values(array_filter(
            self::data()['locations'],
            static fn (object $location): bool => !$onlyVisible || (string) ($location->visible ?? 'true') === 'true'
        ));
    }

    /** Fasce effettive di una data, nel fuso orario del sito. */
    public static function hoursFor(object $location, DateTimeInterface $date): array
    {
        return OpeningHours::periodsFor(
            (array) ($location->hours ?? []),
            (array) ($location->specialHours ?? []),
            self::inSiteTimezone($date)
        );
    }

    public static function isOpen(object $location, ?DateTimeInterface $at = null): bool
    {
        return OpeningHours::isOpenAt(
            (array) ($location->hours ?? []),
            (array) ($location->specialHours ?? []),
            self::inSiteTimezone($at ?? new DateTimeImmutable('now'))
        );
    }

    public static function reset(): void
    {
        self::$cache = null;
    }

    /**
     * @return array{default_id: int, locations: array<int, object>}
     */
    public static function assemble(array $locationRows, array $hourRows, array $specialRows): array
    {
        $rows = array_values(array_filter(
            $locationRows,
            static fn (mixed $row): bool => is_array($row) && ($row['deleted'] ?? 'false') !== 'true'
        ));

        usort($rows, static fn (array $a, array $b): int => [(int) ($a['position'] ?? 0), (int) ($a['id'] ?? 0)]
            <=> [(int) ($b['position'] ?? 0), (int) ($b['id'] ?? 0)]);

        $defaultRow = null;

        foreach ($rows as $row) {
            if (($row['is_default'] ?? '') === 'true') {
                $defaultRow = $row;
                break;
            }
        }

        $defaultRow ??= $rows[0] ?? null;
        $defaultId = (int) ($defaultRow['id'] ?? 0);
        $hoursByLocation = self::groupByLocation($hourRows);
        $specialByLocation = self::groupByLocation($specialRows);
        $locations = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $isDefault = $id === $defaultId;
            $resolved = SocietyLocationResolver::resolve($row, $isDefault ? null : $defaultRow);
            $hours = SocietyLocationResolver::hours(
                $hoursByLocation[$id] ?? [],
                $specialByLocation[$id] ?? [],
                $hoursByLocation[$defaultId] ?? [],
                $specialByLocation[$defaultId] ?? [],
                $isDefault
            );

            $resolved['is_default'] = $isDefault ? 'true' : 'false';
            $location = (object) $resolved;
            $location->hours = $hours['hours'];
            $location->specialHours = $hours['special_hours'];
            $location->hoursInherited = $hours['inherited'];
            $locations[$id] = $location;
        }

        return ['default_id' => $defaultId, 'locations' => $locations];
    }

    private static function data(): array
    {
        return self::$cache ??= self::load();
    }

    private static function load(): array
    {
        if (!function_exists('sqlTableExists')) {
            return self::assemble([], [], []);
        }

        if (sqlTableExists(SocietyLocation::$table)) {
            return self::assemble(
                self::rows(SocietyLocation::$table),
                sqlTableExists(SocietyLocationHour::$table) ? self::rows(SocietyLocationHour::$table) : [],
                sqlTableExists(SocietyLocationSpecialHour::$table) ? self::rows(SocietyLocationSpecialHour::$table) : []
            );
        }

        if (!sqlTableExists('society')) {
            return self::assemble([], [], []);
        }

        $hours = array_map(
            static fn (array $row): array => $row + ['society_location_id' => 1],
            SocietyLocationsMigration::legacyHours()
        );

        return self::assemble([['id' => 1] + SocietyLocationsMigration::legacyLocation()], $hours, []);
    }

    private static function rows(string $table): array
    {
        $rows = sqlSelect($table, ['deleted' => 'false'])->row;

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    private static function groupByLocation(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            if (is_array($row) && ($row['deleted'] ?? 'false') !== 'true') {
                $grouped[(int) ($row['society_location_id'] ?? 0)][] = $row;
            }
        }

        foreach ($grouped as $id => $list) {
            usort($list, static fn (array $a, array $b): int => (int) ($a['position'] ?? 0) <=> (int) ($b['position'] ?? 0));
            $grouped[$id] = $list;
        }

        return $grouped;
    }

    private static function inSiteTimezone(DateTimeInterface $date): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($date)->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }

    private static function emptyLocation(): object
    {
        return (object) [
            'id' => 0,
            'slug' => '',
            'label' => '',
            'is_default' => 'true',
            'visible' => 'true',
            'inherited_fields' => [],
            'hours' => [],
            'specialHours' => [],
            'hoursInherited' => false,
        ];
    }
}
```

- [ ] **Step 4: Eseguire il test e verificare che passi**

Run: `php -l class/App/Support/SocietyLocations.php && php tests/App/Support/SocietyLocationsTest.php`
Expected: `5 test, 0 falliti`.

- [ ] **Step 5: `infoSociety()` per sede e `$SOCIETY`**

In `app/function/info.php` sostituisci l'intera funzione `infoSociety()` (da `function infoSociety() {` alla sua `}` di chiusura, prima di `function infoSeo()`) con:

```php
    function infoSociety(int|string|null $location = null) {

        global $PATH;

        $LOCATION = $location === null || $location === ''
            ? \Wonder\App\Support\SocietyLocations::default()
            : (\Wonder\App\Support\SocietyLocations::find($location) ?? \Wonder\App\Support\SocietyLocations::default());

        $RETURN = (object) array();

        foreach (get_object_vars($LOCATION) as $column => $value) {
            if (is_scalar($value) || $value === null) { $RETURN->$column = $value ?? ''; }
        }

        $RETURN->social = [];

        foreach (\Wonder\App\Support\SocietyLocationResolver::LINK_FIELDS as $column) {
            if ($column !== 'site' && !empty($RETURN->$column ?? '')) {
                $RETURN->social[$column] = $RETURN->$column;
            }
        }

        foreach ([
            'street',
            'number',
            'cap',
            'city',
            'province',
            'country',
            'gmaps',
            'legal_street',
            'legal_number',
            'legal_cap',
            'legal_city',
            'legal_province',
            'legal_country',
            'name',
            'legal_name',
            'email',
            'site',
            'pi',
            'cf',
        ] as $field) {
            if (!isset($RETURN->$field)) {
                $RETURN->$field = '';
            }
        }

        $RETURN->domain = empty($RETURN->site) ? '' : (parse_url($RETURN->site, PHP_URL_HOST) ?? '');

        $address = prettyAddress($RETURN->street, $RETURN->number, $RETURN->cap, $RETURN->city, $RETURN->province, $RETURN->country);
        $RETURN->address = "$RETURN->street $RETURN->number, $RETURN->cap $RETURN->city ($RETURN->province)";
        $RETURN->prettyAddress = $address->pretty;
        $RETURN->prettyAddressPDF = $address->prettyPDF;

        if (empty($RETURN->gmaps) && !empty($RETURN->google_place_id)) {
            $RETURN->gmaps = \Wonder\App\Support\GoogleMapsLink::forPlace((string) $RETURN->google_place_id, $RETURN->address);
        }

        $legalAddress = prettyAddress($RETURN->legal_street, $RETURN->legal_number, $RETURN->legal_cap, $RETURN->legal_city, $RETURN->legal_province, $RETURN->legal_country);
        $RETURN->addressLegal = "$RETURN->legal_street $RETURN->legal_number, $RETURN->legal_cap $RETURN->legal_city ($RETURN->legal_province)";
        $RETURN->prettyLegalAddress = $legalAddress->pretty;
        $RETURN->prettyLegalAddressPDF = $legalAddress->prettyPDF;

        $RETURN->prettyLegal = "";

        if (!empty($RETURN->legal_name)) { $RETURN->prettyLegal .= $RETURN->legal_name; }

        if (!empty($RETURN->pi) || !empty($RETURN->cf)) {
            if ($RETURN->pi == $RETURN->cf) {
                $RETURN->prettyLegal .= ' - P.Iva e C.Fiscale '.$RETURN->pi;
            } else {
                if (!empty($RETURN->pi)) { $RETURN->prettyLegal .= ' - P.Iva '.$RETURN->pi; }
                if (!empty($RETURN->cf)) { $RETURN->prettyLegal .= ' - C.Fiscale '.$RETURN->cf; }
            }
        }

        $RETURN->timetable = \Wonder\App\Support\OpeningHours::timetable((array) ($LOCATION->hours ?? []));

        $PRETTY_TIMEGROUP = prettyTimeTable($RETURN->timetable);

        $RETURN->timeGroup = $PRETTY_TIMEGROUP->timeGroup;
        $RETURN->prettyTime = $PRETTY_TIMEGROUP->prettyTime;
        $RETURN->prettyTimeGroup = $PRETTY_TIMEGROUP->prettyTimeGroup;

        $RETURN->location = (object) [
            'id' => (int) ($LOCATION->id ?? 0),
            'slug' => (string) ($LOCATION->slug ?? ''),
            'label' => (string) ($LOCATION->label ?? ''),
            'is_default' => (string) ($LOCATION->is_default ?? '') === 'true',
        ];
        $RETURN->google_place_id = (string) ($LOCATION->google_place_id ?? '');
        $RETURN->hours = (array) ($LOCATION->hours ?? []);
        $RETURN->specialHours = \Wonder\App\Support\OpeningHours::upcomingSpecial(
            (array) ($LOCATION->specialHours ?? []),
            new DateTimeImmutable('today')
        );
        $RETURN->businessStatus = (string) ($LOCATION->business_status ?? 'operational');

        $LOGOS = sqlSelect('logos', [ 'id' => '1'], 1)->row;

        $LOGO = [];

        foreach ((array) $LOGOS as $key => $value) {
            if (!empty($value) && !empty(json_decode($value)) && is_array(json_decode($value))) {
                $logo = json_decode($value)[0];
                $LOGO[$key] = $logo;
            }
        }

        $RETURN->logo = isset($LOGO['main']) ? $PATH->upload.'/logos/'.$LOGO['main'] : "";
        $RETURN->logoBlack = isset($LOGO['black']) ? $PATH->upload.'/logos/'.$LOGO['black'] : "";
        $RETURN->logoWhite = isset($LOGO['white']) ? $PATH->upload.'/logos/'.$LOGO['white'] : "";
        
        $RETURN->icon = isset($LOGO['icon']) ? $PATH->upload.'/logos/'.$LOGO['icon'] : "";
        $RETURN->iconBlack = isset($LOGO['icon_black']) ? $PATH->upload.'/logos/'.$LOGO['icon_black'] : "";
        $RETURN->iconWhite = isset($LOGO['icon_white']) ? $PATH->upload.'/logos/'.$LOGO['icon_white'] : "";

        $RETURN->favicon = isset($LOGO['favicon']) ? $PATH->site.'/'.$LOGO['favicon'] : "";
        $RETURN->appIcon = isset($LOGO['app_icon']) ? $PATH->upload.'/logos/'.$LOGO['app_icon'] : "";

        return $RETURN;

    }

    /** Tutte le sedi visibili, nel formato di `infoSociety()`. */
    function infoSocietyLocations(): array {

        $LOCATIONS = [];

        foreach (\Wonder\App\Support\SocietyLocations::all() as $location) {
            $LOCATIONS[] = infoSociety((int) $location->id);
        }

        return $LOCATIONS;

    }
```

In `app/service/lang.php` sostituisci `if (sqlTableExists('society')) {` con:

```php
            if (sqlTableExists('society_locations') || sqlTableExists('society')) {
```

In `SocietyLocationsMigration::runIfNeeded()`, subito prima di `return true;`, aggiungi:

```php
        SocietyLocations::reset();
```

- [ ] **Step 6: Verificare sintassi e test**

Run: `php -l app/function/info.php && php -l app/service/lang.php && php -l class/App/Support/SocietyLocationsMigration.php && php tests/App/Support/SocietyLocationsTest.php && php tests/App/Support/SocietyLocationResolverTest.php`
Expected: nessun errore, `5 test, 0 falliti` e `11 test, 0 falliti`. Il confronto dei campi di `infoSociety()` prima e dopo si fa nel Task 10.

- [ ] **Step 7: Commit**

```bash
git add class/App/Support/SocietyLocations.php class/App/Support/SocietyLocationsMigration.php app/function/info.php app/service/lang.php
git add -f tests/App/Support/SocietyLocationsTest.php
git commit -m "Read society locations per request and add infoSociety location argument

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 6: Header dei form con azioni e sottotitolo, suggerimenti dei campi

**Files:**
- Create: `app/view/layout/backend/partials/header-actions.php`
- Modify: `app/view/layout/backend/show.php`, `app/view/layout/backend/form.php`, `class/Backend/Support/ResourcePagePresenter.php`, `class/App/ResourceSchema/Input.php`, `class/App/Resource.php`
- Test: `tests/App/ResourceSchema/InputPlaceholderTest.php`, `tests/Backend/Support/HeaderActionsPartialTest.php`

**Interfaces:**
- Consumes: `PageActionNormalizer::normalize()`, `DocsAction` (esistenti).
- Produces:
  - `Input::placeholder(string $placeholder): static` (attributo `placeholder` al render);
  - `Resource::formPlaceholders(array $values, string $mode): array<string, string>` (default `[]`);
  - `ResourcePagePresenter::form()` restituisce anche `SUBTITLE` e `ACTIONS` (azioni di `PageSchema::actions($mode, ...)` più "Guida"); non restituisce più `DOCS_URL` e `DOCS_LABEL`;
  - `form.php` mostra `SUBTITLE`, `ACTIONS` e, se valorizzato, `FORM_ERROR_MESSAGE` al posto del messaggio generico.

- [ ] **Step 1: Scrivere i test**

`tests/App/ResourceSchema/InputPlaceholderTest.php`:

```php
<?php
/** php tests/App/ResourceSchema/InputPlaceholderTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\ResourceSchema\FormField;

check('placeholder nello schema e negli attributi dell\'elemento', function () {
    $field = FormField::key('email')->text()->placeholder('info@esempio.it');
    $element = $field->compile();

    return $field->get('placeholder') === 'info@esempio.it'
        && $element !== null
        && $element->getAttr('placeholder') === 'info@esempio.it';
});

check('senza placeholder l\'attributo resta vuoto', function () {
    $element = FormField::key('email')->text()->compile();

    return $element !== null && (string) $element->getAttr('placeholder') === '';
});

summary();
```

`tests/Backend/Support/HeaderActionsPartialTest.php`:

```php
<?php
/** php tests/Backend/Support/HeaderActionsPartialTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\Backend\Support\PageActionNormalizer;

$render = static function (array $ACTIONS): string {
    ob_start();
    include __DIR__ . '/../../../app/view/layout/backend/partials/header-actions.php';

    return (string) ob_get_clean();
};

check('pulsanti con link, classe, icona e nuova scheda', function () use ($render) {
    $html = $render(PageActionNormalizer::normalize([
        ['label' => 'Orari e chiusure', 'href' => '/backend/app/config/opening-hours/1/edit/', 'icon' => 'bi bi-clock', 'class' => 'btn-outline-secondary'],
        ['label' => 'Guida', 'href' => 'https://guide.example.it', 'target' => '_blank'],
    ]));

    return str_contains($html, 'href="/backend/app/config/opening-hours/1/edit/"')
        && str_contains($html, 'btn btn-outline-secondary')
        && str_contains($html, 'bi bi-clock')
        && str_contains($html, 'Orari e chiusure')
        && str_contains($html, 'target="_blank" rel="noopener noreferrer"');
});

check('nessuna azione, nessun markup', fn () => trim($render([])) === '');

summary();
```

- [ ] **Step 2: Eseguire i test e verificare che falliscano**

Run: `php tests/App/ResourceSchema/InputPlaceholderTest.php; php tests/Backend/Support/HeaderActionsPartialTest.php`
Expected: il primo fallisce con `Call to undefined method ...placeholder()`; il secondo con `Failed opening ... header-actions.php`.

- [ ] **Step 3: Spostare i pulsanti dell'header in un partial**

Verifica che il blocco sia alle righe 20–130 di `show.php`:

Run: `sed -n '20p;130p' app/view/layout/backend/show.php`
Expected: `            <?php if (!empty($ACTIONS) && is_array($ACTIONS)) : ?>` e `            <?php endif; ?>`.

Poi sposta il blocco (togliendo 12 spazi di rientro) e mettilo al suo posto con un `include`:

```bash
mkdir -p app/view/layout/backend/partials
php -r '
$file = "app/view/layout/backend/show.php";
$lines = file($file);
$block = array_map(static fn (string $line): string => preg_replace("/^ {12}/", "", $line), array_slice($lines, 19, 111));
file_put_contents("app/view/layout/backend/partials/header-actions.php", implode("", $block));
array_splice($lines, 19, 111, ["            <?php include __DIR__.\"/partials/header-actions.php\"; ?>\n"]);
file_put_contents($file, implode("", $lines));
'
```

Correggi l'include appena scritto in `show.php` per usare apici singoli, come nel resto del codice:

```php
            <?php include __DIR__.'/partials/header-actions.php'; ?>
```

- [ ] **Step 4: Header del form come quello della scheda**

Sostituisci in `app/view/layout/backend/form.php` tutto il blocco dal primo `<wi-card class="col-12">` fino al `<?php } ?>` che chiude `if (!empty($FORM_ERRORS))` con:

```php
    <wi-card class="col-12">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div class="flex-grow-1 min-w-0">
                <h3 class="mb-0">
                    <?php if (!empty($BACK_URL)) { ?>
                    <a href="<?=htmlspecialchars((string) ($BACK_URL ?? ''), ENT_QUOTES, 'UTF-8')?>" class="text-dark text-decoration-none"><i class="bi bi-arrow-left-short"></i></a>
                    <?php } ?>
                    <?=htmlspecialchars((string) ($TITLE ?? ''), ENT_QUOTES, 'UTF-8')?>
                </h3>
                <?php if (!empty($SUBTITLE)) { ?>
                <div class="text-body-secondary small mt-1">
                    <?=htmlspecialchars((string) $SUBTITLE, ENT_QUOTES, 'UTF-8')?>
                </div>
                <?php } ?>
            </div>
            <?php include __DIR__.'/partials/header-actions.php'; ?>
        </div>
    </wi-card>

    <?php if (!empty($FORM_ERRORS)) { ?>
    <wi-card class="col-12">
        <div class="col-12">
            <div class="alert alert-danger mb-0">
                <?=htmlspecialchars(
                    trim((string) ($FORM_ERROR_MESSAGE ?? '')) !== ''
                        ? (string) $FORM_ERROR_MESSAGE
                        : 'Operazione non riuscita. Controlla i campi del form.',
                    ENT_QUOTES,
                    'UTF-8'
                )?>
            </div>
        </div>
    </wi-card>
    <?php } ?>
```

- [ ] **Step 5: `placeholder()` sugli input e `formPlaceholders()` sulle Resource**

In `class/App/ResourceSchema/Input.php`, dopo il metodo `label()`:

```php
    /**
     * Suggerimento mostrato nel campo vuoto (attributo `placeholder`).
     */
    public function placeholder(string $placeholder): static
    {
        $this->schema['placeholder'] = $placeholder;

        return $this;
    }
```

Nello stesso file, in `hydrate()`, sostituisci:

```php
        if ($autocomplete !== null) {
            $attributes['autocomplete'] = $autocomplete;
        }
```

con:

```php
        if ($autocomplete !== null) {
            $attributes['autocomplete'] = $autocomplete;
        }

        $placeholder = (string) ($this->schema['placeholder'] ?? '');

        if ($placeholder !== '') {
            $attributes['placeholder'] = $placeholder;
        }
```

In `class/App/Resource.php`, dopo il metodo `mutateFormValues()`:

```php
    /**
     * Suggerimenti dei campi vuoti nel form backend: nome campo => testo
     * (es. il valore ereditato da un altro record).
     *
     * @return array<string, string>
     */
    public static function formPlaceholders(array $values, string $mode): array
    {
        return [];
    }
```

- [ ] **Step 6: Presenter del form**

In `class/Backend/Support/ResourcePagePresenter.php`, metodo `form()`:

1. dopo `$values = $this->resourceClass::mutateFormValues($values, $mode, 'backend');` aggiungi `$placeholders = $this->resourceClass::formPlaceholders($values, $mode);`;
2. dopo `'TITLE' => $this->pageTitle($mode),` aggiungi:

```php
            'SUBTITLE' => $this->pageSubtitle($mode),
            'ACTIONS' => $this->withDocsAction($mode, $this->pageActions($mode, $values)),
```

3. passa `$placeholders` come ultimo argomento a `hydrateFields(...)` e `hydrateLayout(...)`;
4. elimina le righe `'DOCS_URL' => ...` e `'DOCS_LABEL' => DocsAction::label(),`.

Nelle firme aggiungi l'ultimo parametro `array $placeholders = []` a `hydrateFields`, `hydrateLayout` e `hydrateField`; in `hydrateLayout` passalo sia alla chiamata ricorsiva sia a `hydrateField`. In `hydrateFields` e in `hydrateField`, subito dopo il blocco che chiama `$clone->value(...)`, aggiungi:

```php
            if ($name !== '' && isset($placeholders[$name]) && method_exists($clone, 'placeholder')) {
                $clone->placeholder((string) $placeholders[$name]);
            }
```

(in `hydrateField` il rientro è di 8 spazi).

- [ ] **Step 7: Eseguire i test e verificare che passino**

Run: `for f in app/view/layout/backend/show.php app/view/layout/backend/form.php app/view/layout/backend/partials/header-actions.php class/Backend/Support/ResourcePagePresenter.php class/App/ResourceSchema/Input.php class/App/Resource.php; do php -l $f; done && php tests/App/ResourceSchema/InputPlaceholderTest.php && php tests/Backend/Support/HeaderActionsPartialTest.php && php tests/App/ResourceSchema/PageSchemaDocsTest.php && php tests/App/ResourceReadonlyTest.php`
Expected: nessun errore; `2 test, 0 falliti`, `2 test, 0 falliti` e i test esistenti senza fallimenti. Controlla anche `grep -rn "DOCS_URL\|DOCS_LABEL" app class` → nessun risultato.

- [ ] **Step 8: Commit**

```bash
git add app/view/layout/backend class/Backend/Support/ResourcePagePresenter.php class/App/ResourceSchema/Input.php class/App/Resource.php
git add -f tests/App/ResourceSchema/InputPlaceholderTest.php tests/Backend/Support/HeaderActionsPartialTest.php
git commit -m "Show header actions and subtitle in backend forms, add field placeholders

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 7: Pagina "Orari e chiusure"

**Files:**
- Create: `class/App/Support/OpeningHoursInput.php`, `class/App/Resources/Config/OpeningHoursResource.php`, `class/Backend/Support/OpeningHoursPageController.php`, `app/http/backend/config/opening-hours.php`
- Test: `tests/App/Support/OpeningHoursInputTest.php`

**Interfaces:**
- Consumes: `OpeningHours` (Task 1), Model (Task 3), `SocietyLocations::reset()` (Task 5), `ResourcePagePresenter::form()` con `SUBTITLE` e `FORM_ERROR_MESSAGE` (Task 6), `Transaction::run()`, `Repeater::rowsFromRequest()`, `Repeater::syncRelatedRows()`.
- Produces:
  - `OpeningHoursInput::hours(array $rows): array{rows: list<array>, errors: list<string>}`, `OpeningHoursInput::specialHours(array $rows): array{rows: list<array>, errors: list<string>}`, `OpeningHoursInput::date(string $value): string`;
  - `OpeningHoursResource` (path `app/config/opening-hours`, slug `app-config-opening-hours`, `AUTHORITIES = ['admin', 'administrator']`), route `backend.resource.app-config-opening-hours.list`, `.edit`, `.update`;
  - `OpeningHoursPageController::handle(string $action, int $id): void`.

- [ ] **Step 1: Scrivere il test**

`tests/App/Support/OpeningHoursInputTest.php`:

```php
<?php
/** php tests/App/Support/OpeningHoursInputTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Support\OpeningHoursInput;

check('orari: stesso giorno, mezzanotte, giorno dopo, righe vuote saltate', function () {
    $result = OpeningHoursInput::hours([
        ['id' => '7', 'hours_type' => 'regular', 'open_day' => 'Mon', 'open_time' => '09:00', 'close_day' => '', 'close_time' => '13:00'],
        ['hours_type' => '', 'open_day' => 'Sat', 'open_time' => '18:00', 'close_day' => '', 'close_time' => '00:00'],
        ['hours_type' => 'delivery', 'open_day' => 'Fri', 'open_time' => '22:00', 'close_day' => 'Sat', 'close_time' => '02:00'],
        ['hours_type' => 'regular', 'open_day' => 'Sun', 'open_time' => '', 'close_day' => '', 'close_time' => ''],
    ]);

    return $result['errors'] === [] && $result['rows'] === [
        ['id' => '7', 'hours_type' => 'regular', 'open_day' => 'Mon', 'open_time' => '09:00', 'close_day' => 'Mon', 'close_time' => '13:00'],
        ['hours_type' => 'regular', 'open_day' => 'Sat', 'open_time' => '18:00', 'close_day' => 'Sat', 'close_time' => '24:00'],
        ['hours_type' => 'delivery', 'open_day' => 'Fri', 'open_time' => '22:00', 'close_day' => 'Sat', 'close_time' => '02:00'],
    ];
});

check('sempre aperto: chiusura vuota', function () {
    $result = OpeningHoursInput::hours([
        ['hours_type' => 'regular', 'open_day' => 'Sun', 'open_time' => '00:00', 'close_day' => 'Mon', 'close_time' => ''],
    ]);

    return $result['errors'] === [] && $result['rows'][0]['close_day'] === '' && $result['rows'][0]['close_time'] === '';
});

check('errori sugli orari, con il numero di riga', function () {
    $result = OpeningHoursInput::hours([
        ['hours_type' => 'regular', 'open_day' => 'Mon', 'open_time' => '13:00', 'close_day' => '', 'close_time' => '09:00'],
        ['hours_type' => 'regular', 'open_day' => 'Mon', 'open_time' => '09:00', 'close_day' => 'Wed', 'close_time' => '02:00'],
        ['hours_type' => 'sconosciuto', 'open_day' => 'Mon', 'open_time' => '09:00', 'close_day' => '', 'close_time' => '10:00'],
    ]);

    return $result['rows'] === [] && count($result['errors']) === 3 && str_contains($result['errors'][0], 'riga 1');
});

check('chiusure e aperture straordinarie', function () {
    $result = OpeningHoursInput::specialHours([
        ['id' => '3', 'start_date' => '10/08/2026', 'end_date' => '25/08/2026', 'closed' => 'true', 'open_time' => '', 'close_time' => '', 'note' => 'Ferie'],
        ['start_date' => '2026-12-20', 'end_date' => '', 'closed' => 'false', 'open_time' => '10:00', 'close_time' => '18:00', 'note' => ''],
        ['start_date' => '31/12/2026', 'end_date' => '', 'closed' => 'false', 'open_time' => '20:00', 'close_time' => '03:00', 'note' => ''],
        ['start_date' => '', 'end_date' => '', 'closed' => 'true', 'open_time' => '', 'close_time' => '', 'note' => ''],
    ]);

    return $result['errors'] === [] && $result['rows'] === [
        ['id' => '3', 'start_date' => '2026-08-10', 'end_date' => '2026-08-25', 'closed' => 'true', 'open_time' => '', 'close_time' => '', 'note' => 'Ferie', 'source' => 'manual'],
        ['start_date' => '2026-12-20', 'end_date' => '', 'closed' => 'false', 'open_time' => '10:00', 'close_time' => '18:00', 'note' => '', 'source' => 'manual'],
        ['start_date' => '2026-12-31', 'end_date' => '2027-01-01', 'closed' => 'false', 'open_time' => '20:00', 'close_time' => '03:00', 'note' => '', 'source' => 'manual'],
    ];
});

check('errori su chiusure e aperture', function () {
    $result = OpeningHoursInput::specialHours([
        ['start_date' => '25/08/2026', 'end_date' => '10/08/2026', 'closed' => 'true'],
        ['start_date' => '20/12/2026', 'end_date' => '', 'closed' => 'false', 'open_time' => '', 'close_time' => ''],
        ['start_date' => '20/12/2026', 'end_date' => '23/12/2026', 'closed' => 'false', 'open_time' => '10:00', 'close_time' => '12:00'],
        ['start_date' => '32/13/2026', 'closed' => 'true'],
    ]);

    return $result['rows'] === [] && count($result['errors']) === 4;
});

summary();
```

- [ ] **Step 2: Eseguire il test e verificare che fallisca**

Run: `php tests/App/Support/OpeningHoursInputTest.php`
Expected: errore fatale `Class "Wonder\App\Support\OpeningHoursInput" not found`.

- [ ] **Step 3: Implementare `OpeningHoursInput`**

`class/App/Support/OpeningHoursInput.php`:

```php
<?php

namespace Wonder\App\Support;

use DateTimeImmutable;
use Wonder\App\Models\Config\SocietyLocationSpecialHour;

/**
 * Normalizza e valida le righe dei repeater di "Orari e chiusure".
 * Classe pura: le righe senza orari (né date, per gli orari speciali) sono
 * considerate vuote e saltate.
 */
final class OpeningHoursInput
{
    /**
     * @return array{rows: list<array<string, mixed>>, errors: list<string>}
     */
    public static function hours(array $rows): array
    {
        $types = array_merge([OpeningHours::REGULAR], OpeningHours::SECONDARY_TYPES);
        $valid = [];
        $errors = [];

        foreach (array_values($rows) as $index => $row) {
            $line = $index + 1;
            $openRaw = trim((string) ($row['open_time'] ?? ''));
            $closeRaw = trim((string) ($row['close_time'] ?? ''));

            if ($openRaw === '' && $closeRaw === '') {
                continue;
            }

            $type = trim((string) ($row['hours_type'] ?? '')) ?: OpeningHours::REGULAR;
            $openDay = trim((string) ($row['open_day'] ?? ''));
            $closeDay = trim((string) ($row['close_day'] ?? ''));
            $open = OpeningHours::time($openRaw);
            $close = OpeningHours::time($closeRaw);

            if (!in_array($type, $types, true) || !in_array($openDay, OpeningHours::DAYS, true)) {
                $errors[] = "Orari, riga {$line}: tipo o giorno non valido.";
                continue;
            }

            if ($open === '' || ($closeRaw !== '' && $close === '') || ($closeDay !== '' && !in_array($closeDay, OpeningHours::DAYS, true))) {
                $errors[] = "Orari, riga {$line}: orario non valido.";
                continue;
            }

            if ($close === '') {
                $closeDay = '';
            } else {
                $closeDay = $closeDay !== '' ? $closeDay : $openDay;

                if ($closeDay === $openDay && $close === '00:00') {
                    $close = '24:00';
                } elseif ($closeDay === $openDay && $close <= $open) {
                    $errors[] = "Orari, riga {$line}: la chiusura deve essere dopo l'apertura (per chiudere dopo la mezzanotte scegli il giorno dopo).";
                    continue;
                } elseif ($closeDay !== $openDay && $closeDay !== OpeningHours::nextDay($openDay)) {
                    $errors[] = "Orari, riga {$line}: la chiusura può essere al massimo il giorno dopo.";
                    continue;
                }
            }

            $valid[] = self::withId($row, [
                'hours_type' => $type,
                'open_day' => $openDay,
                'open_time' => $open,
                'close_day' => $closeDay,
                'close_time' => $close,
            ]);
        }

        return ['rows' => $valid, 'errors' => $errors];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, errors: list<string>}
     */
    public static function specialHours(array $rows): array
    {
        $valid = [];
        $errors = [];

        foreach (array_values($rows) as $index => $row) {
            $line = $index + 1;
            $startRaw = trim((string) ($row['start_date'] ?? ''));
            $endRaw = trim((string) ($row['end_date'] ?? ''));
            $openRaw = trim((string) ($row['open_time'] ?? ''));
            $closeRaw = trim((string) ($row['close_time'] ?? ''));
            $note = trim((string) ($row['note'] ?? ''));

            if ($startRaw === '' && $endRaw === '' && $openRaw === '' && $closeRaw === '' && $note === '') {
                continue;
            }

            $start = self::date($startRaw);
            $end = $endRaw === '' ? '' : self::date($endRaw);
            $closed = (string) ($row['closed'] ?? 'true') !== 'false';

            if ($start === '' || ($endRaw !== '' && $end === '')) {
                $errors[] = "Orari speciali, riga {$line}: data non valida.";
                continue;
            }

            if ($end !== '' && $end < $start) {
                $errors[] = "Orari speciali, riga {$line}: la data di fine è prima di quella di inizio.";
                continue;
            }

            $open = '';
            $close = '';

            if (!$closed) {
                $open = OpeningHours::time($openRaw);
                $close = OpeningHours::time($closeRaw);
                $nextDay = (new DateTimeImmutable($start))->modify('+1 day')->format('Y-m-d');

                if ($open === '' || $close === '') {
                    $errors[] = "Orari speciali, riga {$line}: per un'apertura servono gli orari.";
                    continue;
                }

                if ($end !== '' && $end !== $start && $end !== $nextDay) {
                    $errors[] = "Orari speciali, riga {$line}: un'apertura straordinaria dura al massimo fino al giorno dopo.";
                    continue;
                }

                if ($close === '00:00') {
                    $close = '24:00';
                }

                $end = $close !== '24:00' && $close <= $open ? $nextDay : '';
            }

            $source = (string) ($row['source'] ?? '');

            $valid[] = self::withId($row, [
                'start_date' => $start,
                'end_date' => $end,
                'closed' => $closed ? 'true' : 'false',
                'open_time' => $open,
                'close_time' => $close,
                'note' => $note,
                'source' => in_array($source, SocietyLocationSpecialHour::SOURCES, true) ? $source : 'manual',
            ]);
        }

        return ['rows' => $valid, 'errors' => $errors];
    }

    /** `Y-m-d` da `d/m/Y`, `Y-m-d` o `Y-m-d H:i:s`; stringa vuota se non valida. */
    public static function date(string $value): string
    {
        $value = trim($value);

        foreach (['d/m/Y', 'Y-m-d', 'Y-m-d H:i:s'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

            if ($date instanceof DateTimeImmutable && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return '';
    }

    private static function withId(array $row, array $values): array
    {
        $id = trim((string) ($row['id'] ?? ''));

        return $id !== '' ? ['id' => $row['id']] + $values : $values;
    }
}
```

- [ ] **Step 4: Eseguire il test e verificare che passi**

Run: `php -l class/App/Support/OpeningHoursInput.php && php tests/App/Support/OpeningHoursInputTest.php`
Expected: `5 test, 0 falliti`.

- [ ] **Step 5: Resource "Orari e chiusure"**

`class/App/Resources/Config/OpeningHoursResource.php`:

```php
<?php

namespace Wonder\App\Resources\Config;

use Wonder\App\Models\Config\SocietyLocation;
use Wonder\App\Models\Config\SocietyLocationHour;
use Wonder\App\Models\Config\SocietyLocationSpecialHour;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\App\ResourceSchema\RepeaterRelation;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\App\Support\OpeningHours;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\HelpText;
use Wonder\Elements\Components\SectionTitle;
use Wonder\Elements\Form\Form;
use Wonder\Http\Route;

/**
 * "Orari e chiusure" delle sedi: orari regolari e secondari, orari speciali
 * e chiusure. Modificabile da `admin` e `administrator` anche in produzione;
 * la pagina di modifica è gestita da `OpeningHoursPageController`.
 */
final class OpeningHoursResource extends Resource
{
    public const AUTHORITIES = ['admin', 'administrator'];

    public static string $model = SocietyLocation::class;
    public static string $orderColumn = 'position';
    public static string $orderDirection = 'ASC';

    public static function path(): string
    {
        return 'app/config/opening-hours';
    }

    public static function icon(): string
    {
        return 'bi bi-clock';
    }

    public static function titleLabel(): string
    {
        return 'Orari e chiusure';
    }

    public static function textSchema(): array
    {
        return [
            'label' => 'sede',
            'plural_label' => 'sedi',
            'last' => 'ultime',
            'all' => 'tutte',
            'article' => 'le',
            'full' => 'visibile',
            'empty' => 'nascosta',
            'this' => 'questa',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'label' => 'Sede',
            'city' => 'Città',
            'is_default' => 'Predefinita',
            'hours' => 'Orari regolari e secondari',
            'special_hours' => 'Orari speciali e chiusure',
            'actions' => 'Azioni',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormField::key('hours')
                ->repeater([
                    RepeaterColumn::key('id')->hidden(),
                    RepeaterColumn::key('hours_type')->select(self::hoursTypes())->value(OpeningHours::REGULAR)->label('Tipo')->columnSpan(3),
                    RepeaterColumn::key('open_day')->select(self::days())->label('Apre il')->columnSpan(2),
                    RepeaterColumn::key('open_time')->timeInput(900)->label('Alle')->columnSpan(2),
                    RepeaterColumn::key('close_day')->select(['' => 'Stesso giorno'] + self::days())->label('Chiude il')->columnSpan(2),
                    RepeaterColumn::key('close_time')->timeInput(900)->label('Alle')->columnSpan(2),
                ])
                ->relation(
                    RepeaterRelation::make(SocietyLocationHour::$table, 'society_location_id')
                        ->model(SocietyLocationHour::class)
                        ->positionKey('position')
                )
                ->nested()
                ->repeaterSortable()
                ->repeaterAddLabel('Aggiungi fascia oraria')
                ->repeaterDeleteTitle('Elimina fascia oraria')
                ->repeaterDeleteText('Confermi l\'eliminazione di questa fascia oraria?')
                ->repeaterDeleteCancelLabel('Annulla')
                ->repeaterDeleteConfirmLabel('Elimina')
                ->repeaterDeleteConfirmClass('btn btn-danger')
                ->label('Orari regolari e secondari'),
            FormField::key('special_hours')
                ->repeater([
                    RepeaterColumn::key('id')->hidden(),
                    RepeaterColumn::key('start_date')->dateInput()->label('Dal')->columnSpan(2),
                    RepeaterColumn::key('end_date')->dateInput()->label('Al')->columnSpan(2),
                    RepeaterColumn::key('closed')->select(['true' => 'Chiuso', 'false' => 'Aperto'])->value('true')->label('Stato')->columnSpan(2),
                    RepeaterColumn::key('open_time')->timeInput(900)->label('Apre')->columnSpan(2),
                    RepeaterColumn::key('close_time')->timeInput(900)->label('Chiude')->columnSpan(2),
                    RepeaterColumn::key('note')->text()->label('Nota')->columnSpan(11),
                ])
                ->relation(
                    RepeaterRelation::make(SocietyLocationSpecialHour::$table, 'society_location_id')
                        ->model(SocietyLocationSpecialHour::class)
                )
                ->nested()
                ->repeaterAddLabel('Aggiungi chiusura o apertura straordinaria')
                ->repeaterDeleteTitle('Elimina orario speciale')
                ->repeaterDeleteText('Confermi l\'eliminazione di questo orario speciale?')
                ->repeaterDeleteCancelLabel('Annulla')
                ->repeaterDeleteConfirmLabel('Elimina')
                ->repeaterDeleteConfirmClass('btn btn-danger')
                ->label('Orari speciali e chiusure'),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Card)->components([
                SectionTitle::make('Orari regolari e secondari')->columnSpan(12),
                HelpText::make('Più fasce nello stesso giorno sono più righe (es. 9–13 e 15–19). Per chiudere dopo la mezzanotte scegli il giorno dopo in "Chiude il"; per chiudere a mezzanotte usa 00:00 dello stesso giorno. Una riga senza orario di chiusura indica "sempre aperto".')->columnSpan(12),
                static::getInput('hours')->columnSpan(12),
            ])->columns(12)->columnSpan(12),
            (new Card)->components([
                SectionTitle::make('Orari speciali e chiusure')->columnSpan(12),
                HelpText::make('Le chiusure possono durare più giorni (es. ferie dal 10 al 25 agosto). Per un\'apertura straordinaria compila "Dal" e gli orari: se chiude dopo la mezzanotte vale fino al giorno dopo. Valgono per gli orari regolari.')->columnSpan(12),
                static::getInput('special_hours')->columnSpan(12),
            ])->columns(12)->columnSpan(12),
        ])->columns(12);
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('label')->text()->link('edit'),
            TableColumn::key('city')->text(),
            TableColumn::key('is_default')
                ->booleanBadge()
                ->badgeOn('Predefinita', 'bi bi-star-fill', 'primary')
                ->badgeOff('Secondaria')
                ->size('little'),
            TableColumn::key('actions')->button()->actions(['edit']),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title('Sedi')
            ->results()
            ->hideButtonAdd()
            ->filters();
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)
            ->disable(['create', 'store', 'view', 'delete'])
            ->titles(['list' => 'Orari e chiusure', 'edit' => 'Orari e chiusure']);
    }

    public static function customBackendPages(): array
    {
        return ['edit', 'update'];
    }

    public static function registerBackendRoutes(string $rootApp, string $slug): void
    {
        Route::get('/{id}/edit/', $rootApp.'/http/backend/config/opening-hours.php', [
            'resource' => $slug,
            'resource_action' => 'edit',
        ])->name('edit')
            ->permit(self::AUTHORITIES)
            ->where('id', '[0-9]+');

        Route::post('/{id}/edit/', $rootApp.'/http/backend/config/opening-hours.php', [
            'resource' => $slug,
            'resource_action' => 'update',
        ])->name('update')
            ->permit(self::AUTHORITIES)
            ->where('id', '[0-9]+');
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backend(['list', 'edit', 'update'], self::AUTHORITIES);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->enabled(false);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('set-up')
            ->title('Orari e chiusure')
            ->order(11)
            ->authority(self::AUTHORITIES);
    }

    public static function mutateFormValues(array $values, string $mode, string $context = 'backend'): array
    {
        if (is_array($values['special_hours'] ?? null)) {
            usort(
                $values['special_hours'],
                static fn ($a, $b): int => strcmp((string) ($a['start_date'] ?? ''), (string) ($b['start_date'] ?? ''))
            );
        }

        return $values;
    }

    /** @return array<string, string> */
    private static function days(): array
    {
        $days = [];

        foreach (OpeningHours::DAYS as $day) {
            $days[$day] = translateDate($day, 'day');
        }

        return $days;
    }

    /** @return array<string, string> */
    private static function hoursTypes(): array
    {
        return [
            OpeningHours::REGULAR => 'Orari regolari',
            'delivery' => 'Consegna a domicilio',
            'takeout' => 'Asporto',
            'pickup' => 'Ritiro',
            'drive_through' => 'Drive-through',
            'kitchen' => 'Cucina',
            'breakfast' => 'Colazione',
            'brunch' => 'Brunch',
            'lunch' => 'Pranzo',
            'dinner' => 'Cena',
            'happy_hour' => 'Happy hour',
            'access' => 'Accesso',
            'senior_hours' => 'Fascia anziani',
            'online_service_hours' => 'Servizio online',
        ];
    }
}
```

- [ ] **Step 6: Controller e handler della pagina**

`class/Backend/Support/OpeningHoursPageController.php`:

```php
<?php

namespace Wonder\Backend\Support;

use RuntimeException;
use Wonder\App\Models\Config\SocietyLocation;
use Wonder\App\Models\Config\SocietyLocationHour;
use Wonder\App\Resources\Config\OpeningHoursResource;
use Wonder\App\Support\OpeningHoursInput;
use Wonder\App\Support\Repeater;
use Wonder\App\Support\SocietyLocations;
use Wonder\Sql\Transaction;
use Wonder\View\View;

/**
 * Modifica di "Orari e chiusure" di una sede: valida i repeater e sincronizza
 * solo le tabelle degli orari, senza toccare la riga della sede.
 */
final class OpeningHoursPageController
{
    public static function handle(string $action, int $id): void
    {
        $location = $id > 0 ? SocietyLocation::findById($id) : null;

        if (!is_array($location) || $location === []) {
            throw new RuntimeException('Sede non trovata.');
        }

        $errors = [];
        $isUpdate = $action === 'update';

        if ($isUpdate) {
            $hours = OpeningHoursInput::hours(Repeater::rowsFromRequest('hours', $_POST, $_FILES));
            $special = OpeningHoursInput::specialHours(Repeater::rowsFromRequest('special_hours', $_POST, $_FILES));
            $errors = array_merge($hours['errors'], $special['errors']);

            if ($errors === []) {
                $relations = OpeningHoursResource::repeaterRelations();

                Transaction::run(static function () use ($relations, $id, $hours, $special): void {
                    Repeater::syncRelatedRows($relations['hours']['relation'], $id, $hours['rows']);
                    Repeater::syncRelatedRows($relations['special_hours']['relation'], $id, $special['rows']);
                });

                SocietyLocations::reset();

                header('Location: '.__r('backend.resource.'.OpeningHoursResource::slug().'.list'));
                exit();
            }
        }

        $values = OpeningHoursResource::hydrateRepeaterFormValues(
            $location,
            $id,
            $isUpdate ? $_POST : [],
            $isUpdate ? $_FILES : []
        );
        $presenter = new ResourcePagePresenter(OpeningHoursResource::class);
        $data = $presenter->form('edit', $values, $errors === [] ? [] : ['opening_hours' => implode(' ', $errors)], $id);

        $data['TITLE'] = 'Orari e chiusure · '.(string) ($location['label'] ?? '');
        $data['SUBTITLE'] = self::subtitle($id, $location);
        $data['FORM_ERROR_MESSAGE'] = implode(' ', $errors);

        View::make($presenter->viewPath('form'), $data)->render();
    }

    private static function subtitle(int $id, array $location): string
    {
        if (($location['is_default'] ?? '') === 'true') {
            return 'Sede predefinita: le sedi senza orari propri usano questi orari e queste chiusure.';
        }

        $ownHours = sqlSelect(SocietyLocationHour::$table, ['society_location_id' => $id, 'deleted' => 'false'], 1)->exists;

        return $ownHours
            ? ''
            : 'Questa sede usa orari e chiusure della sede predefinita finché non aggiungi orari propri.';
    }
}
```

`app/http/backend/config/opening-hours.php`:

```php
<?php

$routeMeta = is_array($ROUTE_META ?? null) ? $ROUTE_META : [];
$routeParameters = is_array($ROUTE_PARAMETERS ?? null) ? $ROUTE_PARAMETERS : [];

\Wonder\Backend\Support\OpeningHoursPageController::handle(
    (string) ($routeMeta['resource_action'] ?? 'edit'),
    (int) ($routeParameters['id'] ?? 0)
);
```

- [ ] **Step 7: Verificare sintassi e test**

Run: `for f in class/App/Resources/Config/OpeningHoursResource.php class/Backend/Support/OpeningHoursPageController.php app/http/backend/config/opening-hours.php; do php -l $f; done && php tests/App/Support/OpeningHoursInputTest.php`
Expected: nessun errore, `5 test, 0 falliti`. Route, permessi e pagina si verificano nel Task 10.

- [ ] **Step 8: Commit**

```bash
git add class/App/Support/OpeningHoursInput.php class/App/Resources/Config/OpeningHoursResource.php class/Backend/Support/OpeningHoursPageController.php app/http/backend/config/opening-hours.php
git add -f tests/App/Support/OpeningHoursInputTest.php
git commit -m "Add opening hours and closures page for society locations

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 8: "Dati aziendali" come elenco delle sedi

**Files:**
- Modify (riscrittura): `class/App/Resources/Config/CorporateDataResource.php`
- Modify: `app/config/routes/route.backend.php`
- Delete: `app/http/backend/config/corporate-data.php`, `app/view/pages/backend/config/corporate-data.php`, `class/App/PageSchema/CorporateDataPageSchema.php`

**Interfaces:**
- Consumes: `SocietyLocation` (Task 3), `SocietyLocationResolver::inheritedValues()`, `SocietyLocationDefaults` (Task 2), `SocietyLocations::reset()` (Task 5), `Resource::formPlaceholders()` e azioni nell'header dei form (Task 6), `OpeningHoursResource::slug()` (Task 7).
- Produces: `CorporateDataResource` (path `app/config/corporate-data`, slug `app-config-corporate-data`), route `backend.resource.app-config-corporate-data.*`; dichiara ancora la sezione `set-up`.

- [ ] **Step 1: Riscrivere la Resource**

Sostituisci il contenuto di `class/App/Resources/Config/CorporateDataResource.php` con:

```php
<?php

namespace Wonder\App\Resources\Config;

use RuntimeException;
use Wonder\App\Models\Config\SocietyLocation;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\App\Support\SocietyLocationDefaults;
use Wonder\App\Support\SocietyLocationResolver;
use Wonder\App\Support\SocietyLocations;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\HelpText;
use Wonder\Elements\Components\SectionTitle;
use Wonder\Elements\Form\Form;

/**
 * "Dati aziendali": sedi della società. Una sede è predefinita e le altre
 * prendono da lei ciò che manca. Questa Resource dichiara la sezione
 * "set-up" del backend (prima voce, order 10).
 */
final class CorporateDataResource extends Resource
{
    public const PLACE_ID_FINDER_URL = 'https://developers.google.com/maps/documentation/javascript/examples/places-placeid-finder';

    public static string $model = SocietyLocation::class;
    public static string $orderColumn = 'position';
    public static string $orderDirection = 'ASC';

    public static function path(): string
    {
        return 'app/config/corporate-data';
    }

    public static function icon(): string
    {
        return 'bi-building';
    }

    public static function titleLabel(): string
    {
        return 'Dati aziendali';
    }

    public static function textSchema(): array
    {
        return [
            'label' => 'sede',
            'plural_label' => 'sedi',
            'last' => 'ultime',
            'all' => 'tutte',
            'article' => 'le',
            'full' => 'visibile',
            'empty' => 'nascosta',
            'this' => 'questa',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'label' => 'Nome della sede',
            'slug' => 'Slug',
            'is_default' => 'Predefinita',
            'visible' => 'Stato',
            'business_status' => 'Attività',
            'opening_date' => 'Data di apertura',
            'google_place_id' => 'Google Place ID',
            'email' => 'Email',
            'pec' => 'Pec',
            'tel' => 'Telefono',
            'cel' => 'Cellulare',
            'name' => 'Nome',
            'legal_name' => 'Nome legale',
            'share_capital' => 'C.Sociale',
            'sdi' => 'SDI',
            'rea' => 'R.E.A.',
            'pi' => 'P.Iva',
            'cf' => 'C.Fiscale',
            'site' => 'Sito',
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'tiktok' => 'TikTok',
            'linkedin' => 'Linkedin',
            'whatsapp' => 'WhatsApp',
            'youtube' => 'Youtube',
            'actions' => 'Azioni',
            ...SocietyLocation::address()->labels(),
            ...SocietyLocation::legalAddress()->labels(),
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormField::key('label')->text()->required(),
            FormField::key('slug')->text(),
            FormField::key('is_default')->select(['true' => 'Sì', 'false' => 'No'])->value('false')->required(),
            FormField::key('visible')->select(['true' => 'Visibile', 'false' => 'Nascosta'])->value('true')->required(),
            FormField::key('business_status')->select([
                'operational' => 'Operativa',
                'closed_temporarily' => 'Chiusa temporaneamente',
                'closed_permanently' => 'Chiusa definitivamente',
                'future_opening' => 'Apertura futura',
            ])->value('operational')->required(),
            FormField::key('opening_date')->dateInput(),
            ...array_values(SocietyLocation::address()->formSchema()),
            FormField::key('google_place_id')->text(),
            FormField::key('email')->email(),
            FormField::key('pec')->text(),
            FormField::key('tel')->text(),
            FormField::key('cel')->text(),
            FormField::key('name')->text(),
            FormField::key('legal_name')->text(),
            FormField::key('share_capital')->price(),
            FormField::key('sdi')->text(),
            FormField::key('rea')->text(),
            FormField::key('pi')->text(),
            FormField::key('cf')->text(),
            ...array_values(SocietyLocation::legalAddress()->formSchema()),
            FormField::key('site')->url(),
            FormField::key('instagram')->url(),
            FormField::key('facebook')->url(),
            FormField::key('tiktok')->url(),
            FormField::key('linkedin')->url(),
            FormField::key('whatsapp')->url(),
            FormField::key('youtube')->url(),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([

            (new Container)->components([

                (new Card)->components([
                    SectionTitle::make('Sede')->columnSpan(12),
                    static::getInput('label')->columnSpan(8),
                    static::getInput('slug')->columnSpan(4),
                    HelpText::make('I campi vuoti prendono i dati dalla sede predefinita: contatti e link uno per uno; dati aziendali, indirizzo e sede legale solo se il riquadro è tutto vuoto.')->columnSpan(12),
                ])->columns(12)->columnSpan(2),

                (new Card)->components([
                    SectionTitle::make('Indirizzo')->columnSpan(12),
                    static::getInput('country')->columnSpan(6),
                    static::getInput('province')->columnSpan(6),
                    static::getInput('city')->columnSpan(8),
                    static::getInput('cap')->columnSpan(4),
                    static::getInput('street')->columnSpan(10),
                    static::getInput('number')->columnSpan(2),
                    static::getInput('more')->columnSpan(12),
                    static::getInput('gmaps')->columnSpan(12),
                    static::getInput('google_place_id')->columnSpan(12),
                    HelpText::make('Trova il Place ID con il <a href="'.self::PLACE_ID_FINDER_URL.'" target="_blank" rel="noopener noreferrer">Place ID Finder di Google</a>. Se il link a Google Maps è vuoto si costruisce dal Place ID.')->columnSpan(12),
                ])->columns(12)->columnSpan(1),

                (new Card)->components([
                    SectionTitle::make('Sede legale')->columnSpan(12),
                    static::getInput('legal_country')->columnSpan(6),
                    static::getInput('legal_province')->columnSpan(6),
                    static::getInput('legal_city')->columnSpan(8),
                    static::getInput('legal_cap')->columnSpan(4),
                    static::getInput('legal_street')->columnSpan(10),
                    static::getInput('legal_number')->columnSpan(2),
                    static::getInput('legal_more')->columnSpan(12),
                    static::getInput('legal_gmaps')->columnSpan(12),
                ])->columns(12)->columnSpan(1),

                (new Card)->components([
                    SectionTitle::make('Contatti')->columnSpan(12),
                    static::getInput('email')->columnSpan(6),
                    static::getInput('pec')->columnSpan(6),
                    static::getInput('tel')->columnSpan(6),
                    static::getInput('cel')->columnSpan(6),
                ])->columns(12)->columnSpan(1),

                (new Card)->components([
                    SectionTitle::make('Dati aziendali e legali')->columnSpan(12),
                    static::getInput('name')->columnSpan(6),
                    static::getInput('legal_name')->columnSpan(6),
                    static::getInput('pi')->columnSpan(6),
                    static::getInput('cf')->columnSpan(6),
                    static::getInput('sdi')->columnSpan(4),
                    static::getInput('rea')->columnSpan(4),
                    static::getInput('share_capital')->columnSpan(4),
                ])->columns(12)->columnSpan(1),

            ])->columns(2)->columnSpan(9),

            (new Container)->components([

                (new Card)->components([
                    SectionTitle::make('Stato')->columnSpan(12),
                    static::getInput('is_default')->columnSpan(12),
                    static::getInput('visible')->columnSpan(12),
                    static::getInput('business_status')->columnSpan(12),
                    static::getInput('opening_date')->columnSpan(12),
                ])->columns(12)->columnSpan(1),

                (new Card)->components([
                    SectionTitle::make('Link')->columnSpan(12),
                    static::getInput('site')->columnSpan(12),
                    static::getInput('instagram')->columnSpan(12),
                    static::getInput('facebook')->columnSpan(12),
                    static::getInput('tiktok')->columnSpan(12),
                    static::getInput('linkedin')->columnSpan(12),
                    static::getInput('whatsapp')->columnSpan(12),
                    static::getInput('youtube')->columnSpan(12),
                ])->columns(12)->columnSpan(1),

            ])->columns(1)->columnSpan(3),

        ])->columns(12);
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('label')->text()->link('edit'),
            TableColumn::key('city')->text(),
            TableColumn::key('is_default')
                ->booleanBadge()
                ->badgeOn('Predefinita', 'bi bi-star-fill', 'primary')
                ->badgeOff('Secondaria')
                ->size('little'),
            TableColumn::key('visible')->visibleBadge()->size('little'),
            TableColumn::key('actions')->button()->actions(['edit', 'delete']),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title('Sedi')
            ->results()
            ->buttonAdd('Aggiungi sede')
            ->filters();
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)
            ->titles([
                'list' => 'Dati aziendali',
                'create' => 'Nuova sede',
                'edit' => 'Modifica sede',
            ])
            ->actions('edit', static fn (array $item): array => [[
                'label' => 'Orari e chiusure',
                'icon' => 'bi bi-clock',
                'class' => 'btn-outline-secondary',
                'href' => __r('backend.resource.'.OpeningHoursResource::slug().'.edit', ['id' => (int) ($item['id'] ?? 0)]),
            ]]);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backendCrud(['admin']);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->enabled(false);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('set-up', 'Set Up', 'bi-gear', 1020, ['admin'])
            ->title('Dati aziendali')
            ->order(10)
            ->authority(['admin']);
    }

    public static function mutateRequestValues(
        array $values,
        string $action,
        string $context = 'backend',
        ?array $oldValues = null
    ): array {
        if (trim((string) ($values['slug'] ?? '')) === '' && trim((string) ($values['label'] ?? '')) !== '') {
            $values['slug'] = $values['label'];
        }

        $values['is_default'] = SocietyLocationDefaults::flagOnSave(
            $values['is_default'] ?? 'false',
            self::otherDefaultExists((int) ($oldValues['id'] ?? 0))
        );

        return $values;
    }

    public static function formPlaceholders(array $values, string $mode): array
    {
        if (($values['is_default'] ?? '') === 'true') {
            return [];
        }

        $default = SocietyLocation::find(['is_default' => 'true'], 1);

        return is_array($default) && $default !== []
            ? SocietyLocationResolver::inheritedValues($values, $default)
            : [];
    }

    public static function afterStore(object $result, array $values = []): void
    {
        self::keepSingleDefault((int) ($result->insert_id ?? 0), $values);
    }

    public static function afterUpdate(int|string $id, object $result, array $values = []): void
    {
        self::keepSingleDefault((int) $id, $values);
    }

    public static function deleteRecord(int|string $id): object
    {
        $row = SocietyLocation::findById($id);

        if (is_array($row) && !SocietyLocationDefaults::canDelete($row)) {
            throw new RuntimeException('La sede predefinita non si può eliminare: imposta prima un\'altra sede come predefinita.');
        }

        $result = parent::deleteRecord($id);
        SocietyLocations::reset();

        return $result;
    }

    private static function keepSingleDefault(int $id, array $values): void
    {
        if ($id > 0 && ($values['is_default'] ?? '') === 'true') {
            foreach ((array) SocietyLocation::find(['is_default' => 'true']) as $row) {
                if (is_array($row) && (int) ($row['id'] ?? 0) !== $id) {
                    sqlModify(SocietyLocation::$table, ['is_default' => 'false'], 'id', (int) $row['id']);
                }
            }
        }

        SocietyLocations::reset();
    }

    private static function otherDefaultExists(int $id): bool
    {
        foreach ((array) SocietyLocation::find(['is_default' => 'true']) as $row) {
            if (is_array($row) && (int) ($row['id'] ?? 0) !== $id) {
                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 2: Togliere la pagina legacy**

In `app/config/routes/route.backend.php`, dentro il gruppo `config.`, elimina le due route:

```php
                Route::get('/corporate-data/', $ROOT_APP.'/http/backend/config/corporate-data.php')
                    ->name('corporate-data')
                    ->permit(['admin']);

                Route::post('/corporate-data/', $ROOT_APP.'/http/backend/config/corporate-data.php')
                    ->permit(['admin']);

```

Poi:

```bash
git rm app/http/backend/config/corporate-data.php app/view/pages/backend/config/corporate-data.php class/App/PageSchema/CorporateDataPageSchema.php
```

- [ ] **Step 3: Verificare riferimenti, sintassi e suite**

Run: `grep -rn "backend.config.corporate-data\|CorporateDataPageSchema" app class resources` → nessun risultato.
Run: `php -l class/App/Resources/Config/CorporateDataResource.php && php -l app/config/routes/route.backend.php && composer dump-autoload -q`
Run: la suite completa (vedi "Mappa dei file").
Expected: nessun errore di sintassi, nessun `FAIL`.

- [ ] **Step 4: Commit**

```bash
git add class/App/Resources/Config/CorporateDataResource.php app/config/routes/route.backend.php
git commit -m "Turn corporate data into the society locations resource

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 9: Documentazione

**Files:**
- Create: `docs/app/concetti/dati-aziendali.md`
- Modify: `docs/app/SUMMARY.md`, `docs/app/piattaforma/multi-ambiente.md`, `docs/app/piattaforma/installazione-e-deploy.md`, `docs/app/concetti/risorse/custom-page-schema.md`, `docs/app/concetti/risorse/resource.md`

- [ ] **Step 1: Pagina "Dati aziendali e sedi"**

`docs/app/concetti/dati-aziendali.md`:

````markdown
---
icon: building
---

# Dati aziendali e sedi

## Cos'è

"Dati aziendali" è l'elenco delle **sedi della società**. Ogni sede ha nome, indirizzo con Google Place ID, contatti, dati aziendali e legali, sede legale e link. Una sede è **predefinita**: le altre prendono da lei ciò che manca.

## Dove si trova nel codice

| Cosa | File |
|---|---|
| Tabelle | `class/App/Models/Config/SocietyLocation.php`, `SocietyLocationHour.php`, `SocietyLocationSpecialHour.php` |
| Eredità | `class/App/Support/SocietyLocationResolver.php` |
| Orari | `class/App/Support/OpeningHours.php` |
| Lettura | `class/App/Support/SocietyLocations.php`, `infoSociety()` in `app/function/info.php` |
| Backend | `class/App/Resources/Config/CorporateDataResource.php`, `OpeningHoursResource.php` |
| Migrazione | `class/App/Support/SocietyLocationsMigration.php` |

## Tabelle

| Tabella | Contenuto | Sync |
|---|---|---|
| `society_locations` | sedi | `multiRow()->keepIds()`: gli `id` restano uguali tra locale e produzione |
| `society_location_hours` | orari regolari e secondari | no, dati di produzione |
| `society_location_special_hours` | orari speciali e chiusure | no, dati di produzione |

I loghi restano unici per la società (`logos`).

## Sede predefinita ed eredità

- C'è sempre una sola sede predefinita: impostarne una toglie il flag alle altre, la prima sede creata lo diventa, non si può eliminare.
- **Contatti** (`email`, `pec`, `tel`, `cel`) e **link** (`site`, `instagram`, `facebook`, `tiktok`, `linkedin`, `whatsapp`, `youtube`): campo per campo.
- **Dati aziendali e legali**, **indirizzo** (con Place ID) e **sede legale**: per gruppo intero, solo se il gruppo della sede è tutto vuoto (il paese da solo non conta). Così non si mescolano dati di sedi diverse.
- **Orari**: una sede senza orari propri usa orari e chiusure della predefinita.
- Nel form i campi vuoti mostrano come suggerimento il valore ereditato.

## Orari e chiusure

La pagina "Orari e chiusure" (menu Set Up) è modificabile da `admin` e `administrator`, anche in produzione. Il modello è quello di Google, così un futuro cron potrà confrontarlo con la scheda Google Business.

**Orari regolari e secondari** (`regularHours`):

- `hours_type`: `regular` o un tipo secondario di Google (`delivery`, `takeout`, `pickup`, `kitchen`, …);
- più fasce nello stesso giorno sono più righe;
- la chiusura può essere il giorno dopo; `24:00` è la mezzanotte a fine giornata;
- una riga senza chiusura indica "sempre aperto".

**Orari speciali e chiusure** (`specialHours`, solo per gli orari regolari):

- chiusura: anche su più giorni (es. ferie dal 10 al 25 agosto);
- apertura straordinaria: un giorno, al massimo fino al giorno dopo se chiude dopo la mezzanotte;
- una chiusura prevale su un'apertura dello stesso giorno.

## Leggere i dati

```php
$SOCIETY;                       // sede predefinita, caricata a ogni richiesta
infoSociety();                  // sede predefinita
infoSociety('negozio-milano');  // per slug o id; se non esiste, la predefinita
infoSocietyLocations();         // tutte le sedi visibili, stesso formato
```

`infoSociety()` restituisce gli stessi campi di sempre (`name`, `email`, `tel`, `prettyAddress`, `prettyLegal`, `social`, `timetable`, `prettyTime`, loghi…) più:

| Campo | Contenuto |
|---|---|
| `location` | `id`, `slug`, `label`, `is_default` |
| `google_place_id` | Place ID della sede |
| `hours` | righe degli orari effettivi |
| `specialHours` | orari speciali da oggi in avanti (`closed` booleano) |
| `businessStatus` | `operational`, `closed_temporarily`, `closed_permanently`, `future_opening` |

Se `gmaps` è vuoto e c'è un Place ID, `gmaps` diventa un link a Google Maps costruito senza chiave API.

Per orari e aperture:

```php
use Wonder\App\Support\SocietyLocations;

$sede = SocietyLocations::find('negozio-milano') ?? SocietyLocations::default();
SocietyLocations::isOpen($sede);                            // adesso, nel fuso orario del sito
SocietyLocations::hoursFor($sede, new DateTimeImmutable()); // [['open' => '09:00', 'close' => '13:00', 'overnight' => false], ...]
SocietyLocations::all();                                    // sedi visibili
```

## Siti esistenti

Al primo `forge update` dopo l'aggiornamento, se `society_locations` è vuota, la sede predefinita (`id = 1`, "Sede principale") viene creata da `society`, `society_address`, `society_legal_address` e `society_social`, con gli orari di `society_timetable` (o del JSON `society_address.timetable`). Le vecchie tabelle restano per una versione ma non sono più scritte né sincronizzate. Prima della migrazione `infoSociety()` legge ancora le vecchie tabelle.

## Moduli

Gli altri moduli puntano all'`id` delle sedi. Un modulo che vuole le sedi modificabili solo in locale sostituisce `CorporateDataResource` con una propria Resource di priorità maggiore che sovrascrive `isReadonly()`.

## In futuro

- Cron che confronta gli orari con la scheda Google tramite il Place ID.
- Place ID dall'autocomplete dell'indirizzo.
- Embed della mappa generato in automatico.
````

- [ ] **Step 2: Indice e pagine collegate**

In `docs/app/SUMMARY.md`, dopo `* [Performance frontend e cache](concetti/frontend-performance.md)` aggiungi:

```markdown
* [Dati aziendali e sedi](concetti/dati-aziendali.md)
```

In `docs/app/piattaforma/multi-ambiente.md`, nella tabella "Tabelle sincronizzabili nel framework", sostituisci le cinque righe da `| \`society\` | Singleton | Dati aziendali |` a `| \`society_timetable\` | Multi-row | Orari di apertura |` con:

```markdown
| `society_locations` | Multi-row con `keepIds()` | Sedi della società (orari e chiusure non sincronizzati, vedi [Dati aziendali e sedi](../concetti/dati-aziendali.md)) |
```

In `docs/app/piattaforma/installazione-e-deploy.md`, dopo la riga `  - \`Wonder\App\RuntimeDefaults\` non e' il posto giusto per i row seed: resta per fallback runtime` aggiungi:

```markdown
- se `society_locations` è vuota, crea la sede predefinita dai vecchi dati aziendali (`stats.society_locations`)
```

In `docs/app/concetti/risorse/custom-page-schema.md` sostituisci `` `class/App/PageSchema/CorporateDataPageSchema.php` `` con `` `class/App/PageSchema/UploadMassivePageSchema.php` ``.

In `docs/app/concetti/risorse/resource.md`, dopo la sezione "Pulsante \"Guida\"" aggiungi:

````markdown
## Azioni e suggerimenti nei form

Le azioni di `PageSchema::actions()` compaiono nell'header di scheda **e** form, prima del pulsante "Guida"; il callable riceve i valori del record:

```php
public static function pageSchema(): PageSchema
{
    return PageSchema::for(static::class)
        ->actions('edit', static fn (array $item): array => [[
            'label' => 'Orari e chiusure',
            'icon' => 'bi bi-clock',
            'class' => 'btn-outline-secondary',
            'href' => __r('backend.resource.app-config-opening-hours.edit', ['id' => (int) $item['id']]),
        ]]);
}
```

`formPlaceholders(array $values, string $mode): array` restituisce i suggerimenti dei campi vuoti (nome campo => testo), per esempio i valori ereditati da un altro record. Sugli input singoli: `FormField::key('email')->email()->placeholder('info@esempio.it')`.
````

- [ ] **Step 3: Verifica finale del codice**

Run: la suite completa e `git status --short`.
Expected: nessun `FAIL`; modificati solo i file del piano più `vendor-static/xml-sitemaps/data/generator.conf` (non toccato da noi).

- [ ] **Step 4: Commit**

```bash
git add docs/app
git commit -m "Document society locations, opening hours and form header actions

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 10: Verifica su `boilerplates/new-site` (piani 1, 2 e 3)

Verifiche con database su `/Users/andreamarinoni/Developer/boilerplates/new-site`, che usa `wonder-image/app` da `vendor/` e il database locale del proprio `.env` (letto dall'app, mai da noi). Nessun commit in `new-site`; i file toccati si ripristinano alla fine. `V` è una cartella nello scratchpad della sessione (`<scratchpad>/new-site-verifica`); `S=/Users/andreamarinoni/Developer/boilerplates/new-site`.

**Files (tutti temporanei):**
- `$V/bootstrap.php`, `$V/snapshot.php`, `$V/info-society.php`, `$V/compare-info.php`, `$V/update.php`, `$V/plan2.php`, `$V/lock-hold.php`, `$V/lock-try.php`, `$V/migration.php`, `$V/locations.php`, `$V/backend.php`, `$V/readonly.php`, `$V/sync.php`
- in `$S`: `app/Models/VerificaCoreItem.php`, `app/Resources/VerificaCoreItemResource.php`, `modules/verifica-core/` (`module.json`, `src/VerificaCore.php`, `src/Defaults.php`), `custom/config/modules.php`

- [ ] **Step 1: Stato iniziale e copie di sicurezza**

```bash
cd "$S" && git status --short > "$V/git-status-prima.txt" && mkdir -p "$V/backup" && cp shared/sync-data.json .htaccess "$V/backup/" && ls modules custom/config/modules.php app/Models app/Resources 2>&1 | tee "$V/presenti-prima.txt"
```

Expected: `modules` e `custom/config/modules.php` non esistono; se esistono, fermarsi e chiedere.

- [ ] **Step 2: Script comuni**

`$V/bootstrap.php`:

```php
<?php
declare(strict_types=1);

const NEW_SITE = '/Users/andreamarinoni/Developer/boilerplates/new-site';

chdir(NEW_SITE);
$ROOT = NEW_SITE;
$GLOBALS['ROOT'] = NEW_SITE;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Wonder\\Plugin\\VerificaCore\\';

    if (str_starts_with($class, $prefix)) {
        $file = NEW_SITE.'/modules/verifica-core/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

        if (is_file($file)) {
            require $file;
        }
    }
});

require NEW_SITE.'/vendor/wonder-image/app/wonder-image.php';

function verifica(string $name, bool $ok, string $detail = ''): void
{
    echo ($ok ? '  ✓ ' : '  ✗ ').$name.($detail !== '' ? ' — '.$detail : '')."\n";
    $GLOBALS['__verifica_falliti'] = ($GLOBALS['__verifica_falliti'] ?? 0) + ($ok ? 0 : 1);
}

register_shutdown_function(static function (): void {
    if (isset($GLOBALS['__verifica_falliti'])) {
        echo "\nfalliti: ".$GLOBALS['__verifica_falliti']."\n";
    }
});

final class Annulla extends RuntimeException
{
}
```

`$V/snapshot.php` (argomento: file di destinazione):

```php
<?php
require __DIR__.'/bootstrap.php';

$snapshot = [];

foreach (['society', 'society_address', 'society_legal_address', 'society_social', 'society_timetable', 'logos', 'seo', 'css_font', 'css_color', 'css_default', 'css_input', 'css_modal', 'css_dropdown', 'css_alert', 'society_locations'] as $table) {
    $rows = sqlTableExists($table) ? (array) sqlSelect($table, null, null, 'id', 'ASC')->row : null;

    if (is_array($rows)) {
        foreach ($rows as $i => $row) {
            unset($rows[$i]['id'], $rows[$i]['creation'], $rows[$i]['last_modified']);
        }
    }

    $snapshot[$table] = $rows;
}

file_put_contents($argv[1], json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo "scritto {$argv[1]}\n";
```

`$V/info-society.php` (argomento: file di destinazione):

```php
<?php
require __DIR__.'/bootstrap.php';

file_put_contents($argv[1], json_encode(get_object_vars(infoSociety()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo "scritto {$argv[1]}\n";
```

`$V/compare-info.php` (argomenti: prima, dopo):

```php
<?php
$before = json_decode((string) file_get_contents($argv[1]), true);
$after = json_decode((string) file_get_contents($argv[2]), true);
$differences = [];

foreach ($before as $key => $value) {
    if (in_array($key, ['id', 'deleted', 'creation', 'last_modified'], true)) {
        continue;
    }

    if (!array_key_exists($key, $after)) {
        $differences[] = "manca {$key}";
    } elseif ($after[$key] !== $value) {
        $differences[] = "{$key}: ".json_encode($value).' → '.json_encode($after[$key]);
    }
}

echo $differences === [] ? "infoSociety(): stessi campi e valori\n" : implode("\n", $differences)."\n";
echo 'campi nuovi: '.implode(', ', array_diff(array_keys($after), array_keys($before)))."\n";
```

`$V/update.php` (come `forge update`, con l'autoload del modulo di prova):

```php
<?php
require __DIR__.'/bootstrap.php';

$runner = new \Wonder\App\UpdateRunner();
$result = $runner->execute(['trigger_type' => 'cli', 'source' => 'local', 'include_cli_files' => false]);

echo $runner->jsonPayload($result), "\n";
exit($result->success ? 0 : 1);
```

- [ ] **Step 3: Fotografia con la versione attuale di `vendor`**

```bash
php "$V/snapshot.php" "$V/snapshot-prima.json" && php "$V/info-society.php" "$V/info-prima.json"
```

Expected: due file scritti, nessun errore.

- [ ] **Step 4: `new-site` sul ramo feature**

```bash
cd "$S/vendor/wonder-image" && mv app .app-dev-main && ln -s /Users/andreamarinoni/Developer/packages/app app && ls -la
```

Expected: `app -> /Users/andreamarinoni/Developer/packages/app`.

- [ ] **Step 5: File di prova nel sito**

`$S/app/Models/VerificaCoreItem.php`:

```php
<?php

namespace App\Models;

use Wonder\App\Model;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class VerificaCoreItem extends Model
{
    public static string $table = 'verifica_core_items';
    public static string $folder = 'verifica-core-items';

    public static function syncSchema(): ?SyncSchema
    {
        return SyncSchema::multiRow()->keepIds()->localOnly();
    }

    public static function tableSchema(): array
    {
        return [
            Column::key('code')->length(50)->unique(),
            ...static::sqlColumnsFromDataSchema(['name']),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('code')->text()->sanitize(false),
            Field::key('name')->text(),
        ];
    }
}
```

`$S/app/Resources/VerificaCoreItemResource.php`:

```php
<?php

namespace App\Resources;

use App\Models\VerificaCoreItem;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;

final class VerificaCoreItemResource extends Resource
{
    public static string $model = VerificaCoreItem::class;

    public static function path(): string
    {
        return 'verifica-core-items';
    }

    public static function formSchema(): array
    {
        return [
            FormField::key('code')->text()->required(),
            FormField::key('name')->text(),
        ];
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('code')->text()->link('edit'),
            TableColumn::key('name')->text(),
            TableColumn::key('actions')->button()->actions(['edit', 'delete']),
        ];
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)->docs('https://wonder-image.gitbook.io/app');
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)->backendCrud(['admin']);
    }
}
```

`$S/modules/verifica-core/module.json`:

```json
{
    "name": "Verifica core",
    "slug": "verifica-core",
    "version": "0.1.0",
    "description": "Modulo temporaneo per verificare i prerequisiti del core.",
    "namespace": "Wonder\\Plugin\\VerificaCore\\",
    "entrypoint": "Wonder\\Plugin\\VerificaCore\\VerificaCore",
    "frameworkCompatibility": { "wonder-app": "^2.1", "php": "^8.2" },
    "dependencies": { "modules": [] },
    "paths": { "src": "src" },
    "database": { "defaults": "Wonder\\Plugin\\VerificaCore\\Defaults" }
}
```

`$S/modules/verifica-core/src/VerificaCore.php`:

```php
<?php

namespace Wonder\Plugin\VerificaCore;

use Wonder\App\Module\Contracts\ModuleInterface;

final class VerificaCore implements ModuleInterface
{
    public static function root(): string { return dirname(__DIR__); }
    public static function manifestPath(): string { return self::root().'/module.json'; }
    public static function handlerPath(string $path): string { return self::root().'/http/'.ltrim($path, '/'); }
    public static function viewPath(string $path): string { return self::root().'/view/'.ltrim($path, '/'); }
    public static function langPath(): string { return self::root().'/lang'; }
    public static function assetPath(string $path = ''): string { return self::root().'/resources/assets/'.ltrim($path, '/'); }
}
```

`$S/modules/verifica-core/src/Defaults.php`:

```php
<?php

namespace Wonder\Plugin\VerificaCore;

use App\Models\VerificaCoreItem;
use Wonder\App\Module\Contracts\ModuleDefaults;
use Wonder\App\Support\DefaultRows;

final class Defaults implements ModuleDefaults
{
    public static function seed(DefaultRows $rows): void
    {
        $rows->ensure(VerificaCoreItem::class, 'code', [
            ['code' => 'uno', 'name' => 'Uno'],
            ['code' => 'due', 'name' => 'Due'],
            ['code' => 'tre', 'name' => 'Tre'],
        ]);
    }
}
```

`$S/custom/config/modules.php`:

```php
<?php

return ['verifica-core' => true];
```

- [ ] **Step 6: Piano 3 — update, migrazione reale e confronto di `infoSociety()`**

```bash
php "$V/update.php" > "$V/update-1.json"; tail -3 "$V/update-1.json"; grep -E '"(success|society_locations|sync_import|defaults|sync_export)"' "$V/update-1.json"
php "$V/update.php" > "$V/update-2.json"; grep -E '"(success|society_locations|defaults)"' "$V/update-2.json"
php "$V/info-society.php" "$V/info-dopo.json" && php "$V/compare-info.php" "$V/info-prima.json" "$V/info-dopo.json"
```

Expected: primo update `success: true`, `society_locations: true`, `defaults: 0` (niente `APP_ENV`, quindi produzione); secondo update `society_locations: false`; `infoSociety(): stessi campi e valori` e campi nuovi `slug, label, is_default, visible, position, business_status, …, location, hours, specialHours, businessStatus, hoursInherited`. Ogni differenza va spiegata o corretta nel codice del ramo.

- [ ] **Step 7: Piano 2 — transazioni, letture con lock, lock nominali**

`$V/plan2.php`:

```php
<?php
require __DIR__.'/bootstrap.php';

use App\Models\VerificaCoreItem;
use Wonder\App\Models\Config\SocietyLocation;
use Wonder\Sql\Connection;
use Wonder\Sql\Transaction;

verifica('sql*() e Transaction sulla stessa connessione', Connection::Connect('main') === $GLOBALS['mysqli']);

try {
    Transaction::run(static function (): void {
        sqlInsert('verifica_core_items', ['code' => 'tx-a', 'name' => 'A']);
        VerificaCoreItem::create(['code' => 'tx-b', 'name' => 'B']);
        verifica('righe visibili dentro la transazione', sqlSelect('verifica_core_items', ['code' => 'tx-a'], 1)->exists && sqlSelect('verifica_core_items', ['code' => 'tx-b'], 1)->exists);
        throw new Annulla('annulla');
    });
} catch (Annulla) {
}

verifica('sqlInsert() e Model::create() annullati insieme', !sqlSelect('verifica_core_items', ['code' => 'tx-a'], 1)->exists && !sqlSelect('verifica_core_items', ['code' => 'tx-b'], 1)->exists);

try {
    SocietyLocation::findByIdForUpdate(1);
    verifica('findByIdForUpdate() fuori transazione rifiutata', false);
} catch (RuntimeException $exception) {
    verifica('findByIdForUpdate() fuori transazione rifiutata', str_contains($exception->getMessage(), 'transazione'));
}

verifica('findByIdForUpdate() dentro la transazione', is_array(Transaction::run(static fn () => SocietyLocation::findByIdForUpdate(1))));

try {
    sqlSelectForUpdate('society_locations', ['id' => 1], 1);
    verifica('sqlSelectForUpdate() fuori transazione rifiutata', false);
} catch (RuntimeException) {
    verifica('sqlSelectForUpdate() fuori transazione rifiutata', true);
}
```

`$V/lock-hold.php`:

```php
<?php
require __DIR__.'/bootstrap.php';

$marker = __DIR__.'/lock-preso';
@unlink($marker);

$result = \Wonder\App\Support\NamedLock::run('verifica-core', static function () use ($marker): string {
    touch($marker);
    sleep(4);

    return 'eseguito';
});

@unlink($marker);
echo "processo A: {$result}\n";
```

`$V/lock-try.php` (argomento `atteso` = `occupato` oppure `libero`):

```php
<?php
require __DIR__.'/bootstrap.php';

use Wonder\App\Support\NamedLock;

if ($argv[1] === 'occupato') {
    for ($i = 0; $i < 50 && !is_file(__DIR__.'/lock-preso'); $i++) {
        usleep(100000);
    }
}

$result = NamedLock::run('verifica-core', static fn (): string => 'eseguito');

verifica(
    $argv[1] === 'occupato' ? 'secondo processo non eseguito mentre il primo tiene il lock' : 'lock rilasciato dopo il primo processo',
    $argv[1] === 'occupato' ? $result === NamedLock::NOT_ACQUIRED : $result === 'eseguito'
);
```

```bash
php "$V/plan2.php"
php "$V/lock-hold.php" > "$V/lock-a.log" & php "$V/lock-try.php" occupato; wait; cat "$V/lock-a.log"; php "$V/lock-try.php" libero
```

Expected: tutte le righe `✓`, `falliti: 0`, `processo A: eseguito`. Se la prima verifica fallisce (connessioni diverse), fermarsi: la spec chiede di allineare la risoluzione della connessione a `Connection::Connect()` prima di continuare, e gli step 8–9 usano `Transaction::run()` per annullare le modifiche.

- [ ] **Step 8: Piano 3 — migrazione con dati compilati (annullata alla fine)**

`$V/migration.php`:

```php
<?php
require __DIR__.'/bootstrap.php';

use Wonder\App\Support\SocietyLocations;
use Wonder\App\Support\SocietyLocationsMigration;
use Wonder\Sql\Transaction;

$countBefore = sqlSelect('society_locations')->Nrow;
$upsert = static function (string $table, array $values): void {
    sqlSelect($table, ['id' => 1], 1)->exists
        ? sqlModify($table, $values, 'id', 1)
        : sqlInsert($table, ['id' => 1] + $values);
};

try {
    Transaction::run(static function () use ($upsert): void {
        global $mysqli;

        $mysqli->query('DELETE FROM society_location_special_hours');
        $mysqli->query('DELETE FROM society_location_hours');
        $mysqli->query('DELETE FROM society_locations');

        $upsert('society', ['name' => 'Verifica', 'legal_name' => 'Verifica srl', 'email' => 'verifica@esempio.it', 'tel' => '02 000', 'pi' => '01234567890', 'cf' => '01234567890']);
        $upsert('society_address', ['street' => 'Via Verifica', 'number' => '1', 'cap' => '20100', 'city' => 'Milano', 'province' => 'MI', 'country' => 'IT', 'timetable' => '{"Mon":[{"from":"09:00","to":"13:00"}],"Fri":[{"from":"20:00","to":"00:00"}]}']);
        $upsert('society_legal_address', ['legal_street' => 'Via Legale', 'legal_city' => 'Milano', 'legal_country' => 'IT']);
        $upsert('society_social', ['site' => 'https://verifica.example', 'instagram' => 'https://instagram.com/verifica']);

        if (sqlTableExists('society_timetable')) {
            $mysqli->query("UPDATE society_timetable SET deleted = 'true'");
        }

        verifica('migrazione eseguita', SocietyLocationsMigration::runIfNeeded() === true);

        $row = sqlSelect('society_locations', ['id' => 1], 1)->row;
        verifica('sede predefinita id 1 dai vecchi dati', ($row['slug'] ?? '') === 'sede-principale' && ($row['is_default'] ?? '') === 'true' && ($row['legal_name'] ?? '') === 'Verifica srl' && ($row['street'] ?? '') === 'Via Verifica' && ($row['legal_street'] ?? '') === 'Via Legale' && ($row['site'] ?? '') === 'https://verifica.example');

        $hours = (array) sqlSelect('society_location_hours', ['society_location_id' => 1], null, 'position', 'ASC')->row;
        verifica('orari convertiti dal JSON', count($hours) === 2 && $hours[0]['open_day'] === 'Mon' && $hours[1]['close_time'] === '24:00');
        verifica('secondo avvio senza modifiche', SocietyLocationsMigration::runIfNeeded() === false);

        $info = infoSociety();
        verifica('infoSociety() dai dati migrati', $info->name === 'Verifica' && $info->prettyLegal === 'Verifica srl - P.Iva e C.Fiscale 01234567890' && $info->timetable === ['Mon' => [['from' => '09:00', 'to' => '13:00']], 'Fri' => [['from' => '20:00', 'to' => '24:00']]] && ($info->social['instagram'] ?? '') === 'https://instagram.com/verifica' && $info->domain === 'verifica.example');

        throw new Annulla('annulla');
    });
} catch (Annulla) {
}

SocietyLocations::reset();
verifica('modifiche annullate', sqlSelect('society_locations')->Nrow === $countBefore);
```

```bash
php "$V/migration.php"
```

Expected: tutte `✓`, `falliti: 0`.

- [ ] **Step 9: Piano 3 — più sedi, eredità, predefinita unica (annullato alla fine)**

`$V/locations.php`:

```php
<?php
require __DIR__.'/bootstrap.php';

use Wonder\App\Models\Config\SocietyLocation;
use Wonder\App\Resources\Config\CorporateDataResource;
use Wonder\App\Support\SocietyLocations;
use Wonder\Sql\Transaction;

try {
    Transaction::run(static function (): void {
        verifica('seconda sede non predefinita', CorporateDataResource::mutateRequestValues(['label' => 'Negozio', 'is_default' => 'false'], 'store')['is_default'] === 'false');
        verifica('la sola predefinita resta predefinita', CorporateDataResource::mutateRequestValues(['label' => 'Sede', 'is_default' => 'false'], 'update', 'backend', ['id' => 1])['is_default'] === 'true');

        sqlModify('society_locations', ['email' => 'info@esempio.it', 'legal_name' => 'Esempio srl', 'pi' => '01234567890', 'street' => 'Via Roma', 'city' => 'Milano'], 'id', 1);
        sqlInsert('society_location_hours', ['society_location_id' => 1, 'hours_type' => 'regular', 'open_day' => 'Mon', 'open_time' => '09:00', 'close_day' => 'Mon', 'close_time' => '18:00', 'position' => 0]);
        sqlInsert('society_location_special_hours', ['society_location_id' => 1, 'start_date' => date('Y-m-d', strtotime('+3 days')), 'closed' => 'true', 'source' => 'manual']);
        $id = (int) sqlInsert('society_locations', ['slug' => 'negozio-di-prova', 'label' => 'Negozio di prova', 'is_default' => 'false', 'visible' => 'true', 'position' => 2, 'tel' => '030 000', 'country' => 'IT', 'city' => 'Brescia', 'street' => 'Via Prova'])->insert_id;
        SocietyLocations::reset();

        $default = infoSociety();
        $branch = infoSociety('negozio-di-prova');

        verifica('infoSociety(slug) restituisce la sede', $branch->location->id === $id && $branch->location->is_default === false);
        verifica('infoSociety(id) restituisce la sede', infoSociety($id)->location->slug === 'negozio-di-prova');
        verifica('telefono proprio, email della predefinita', $branch->tel === '030 000' && $branch->email === 'info@esempio.it');
        verifica('indirizzo proprio, dati legali della predefinita', $branch->city === 'Brescia' && $branch->street === 'Via Prova' && $branch->legal_name === 'Esempio srl' && $branch->pi === '01234567890');
        verifica('orari e chiusure della predefinita', $branch->timetable === ['Mon' => [['from' => '09:00', 'to' => '18:00']]] && count($branch->specialHours) === 1);
        verifica('slug inesistente → predefinita', infoSociety('non-esiste')->location->id === $default->location->id);
        verifica('infoSocietyLocations() con due sedi', count(infoSocietyLocations()) === 2);
        verifica('isOpen() senza errori', is_bool(SocietyLocations::isOpen(SocietyLocations::find($id))));
        verifica('suggerimenti del form dalla predefinita', (CorporateDataResource::formPlaceholders(['id' => $id, 'is_default' => 'false', 'city' => 'Brescia'], 'edit')['email'] ?? '') === 'info@esempio.it');

        sqlModify('society_locations', ['is_default' => 'true'], 'id', $id);
        CorporateDataResource::afterUpdate($id, (object) ['success' => true], ['is_default' => 'true']);
        verifica('una sola predefinita dopo il cambio', count((array) SocietyLocation::find(['is_default' => 'true'])) === 1 && (int) SocietyLocations::default()->id === $id);

        try {
            CorporateDataResource::deleteRecord($id);
            verifica('la predefinita non si elimina', false);
        } catch (RuntimeException $exception) {
            verifica('la predefinita non si elimina', !$exception instanceof Annulla);
        }

        throw new Annulla('annulla');
    });
} catch (Annulla) {
}

SocietyLocations::reset();
verifica('modifiche annullate', count(infoSocietyLocations()) === 1);
```

```bash
php "$V/locations.php"
```

Expected: tutte `✓`, `falliti: 0`.

- [ ] **Step 10: Route, menu, header dei form e pulsante "Guida"**

`$V/backend.php`:

```php
<?php
require __DIR__.'/bootstrap.php';

use App\Resources\VerificaCoreItemResource;
use Wonder\App\ResourceRouteRegistrar;
use Wonder\App\Resources\Config\CorporateDataResource;
use Wonder\App\Resources\Config\OpeningHoursResource;
use Wonder\Backend\Support\BackendNavigation;
use Wonder\Backend\Support\ResourcePagePresenter;
use Wonder\Http\Route;

Route::area('backend')->prefix('/backend')->name('backend.')->group(function () use ($ROOT_APP): void {
    ResourceRouteRegistrar::registerBackend($ROOT_APP);
});

verifica('route delle sedi', str_ends_with(__r('backend.resource.app-config-corporate-data.list'), '/backend/app/config/corporate-data/'), __r('backend.resource.app-config-corporate-data.list'));
verifica('route di orari e chiusure', str_ends_with(__r('backend.resource.app-config-opening-hours.update', ['id' => 1]), '/backend/app/config/opening-hours/1/edit/'));

$sections = json_encode(BackendNavigation::all(), JSON_UNESCAPED_UNICODE);
verifica('menu Set Up con Dati aziendali e Orari e chiusure', str_contains($sections, 'Dati aziendali') && str_contains($sections, 'Orari e chiusure') && str_contains($sections, 'administrator'));

$form = (new ResourcePagePresenter(CorporateDataResource::class))->form('edit', ['id' => 1, 'label' => 'Sede principale', 'is_default' => 'true'], [], 1);
verifica('scheda della sede con "Orari e chiusure"', str_contains(json_encode($form['ACTIONS']), 'opening-hours\/1\/edit'));

$docs = (new ResourcePagePresenter(VerificaCoreItemResource::class))->form('create');
verifica('pulsante "Guida" nel form', str_contains(json_encode($docs['ACTIONS'], JSON_UNESCAPED_UNICODE), 'wonder-image.gitbook.io'));
```

```bash
php "$V/backend.php"
```

Expected: tutte `✓`. Se `BackendNavigation::all()` o `__r()` richiedono il contesto HTTP, annotare la verifica come "solo visiva" (Step 13).

- [ ] **Step 11: Piano 1 — righe precaricate, export e import con `id` stabili, sola lettura**

`$V/sync.php`:

```php
<?php
require __DIR__.'/bootstrap.php';

use Wonder\App\Support\TableSync;

$file = NEW_SITE.'/shared/sync-data.json';
$data = json_decode((string) file_get_contents($file), true);
$items = $data['verifica_core_items'] ?? [];

verifica('export con id e deleted', count($items) === 3 && isset($items[0]['id'], $items[0]['deleted']));
verifica('society_locations nel file, vecchie tabelle fuori', isset($data['society_locations'][0]['id']) && !isset($data['society']) && !isset($data['society_timetable']));

$ids = array_column($items, 'id', 'code');
$data['verifica_core_items'] = array_values(array_filter($items, static fn (array $row): bool => $row['code'] !== 'tre'));
file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");

TableSync::importIfExists(NEW_SITE);

$rows = array_column((array) sqlSelect('verifica_core_items', null, null, 'id', 'ASC')->row, null, 'code');
verifica('id invariati dopo l\'import', (int) $rows['uno']['id'] === (int) $ids['uno'] && (int) $rows['due']['id'] === (int) $ids['due']);
verifica('riga assente dal file segnata come cancellata', $rows['tre']['deleted'] === 'true' && count($rows) === 3);
```

`$V/readonly.php` (con `APP_ENV` nell'ambiente del processo):

```php
<?php
require __DIR__.'/bootstrap.php';

use App\Resources\VerificaCoreItemResource;
use Wonder\App\ResourceRouteRegistrar;
use Wonder\Http\Route;

\Wonder\App\Environment::reset();
Route::area('backend')->prefix('/backend')->name('backend.')->group(function () use ($ROOT_APP): void {
    ResourceRouteRegistrar::registerBackend($ROOT_APP);
});

$local = getenv('APP_ENV') === 'local';
$slug = VerificaCoreItemResource::slug();

verifica('isReadonly() '.($local ? 'falso in locale' : 'vero in produzione'), VerificaCoreItemResource::isReadonly() === !$local);
verifica('elenco sempre presente', __r("backend.resource.{$slug}.list") !== '');

foreach (['create', 'store', 'update', 'delete'] as $action) {
    $exists = __r("backend.resource.{$slug}.{$action}", ['id' => 1]) !== '';
    verifica("route {$action} ".($local ? 'presente' : 'assente'), $exists === $local);
}
```

```bash
php "$V/snapshot.php" "$V/snapshot-prima-import.json"
APP_ENV=local php "$V/update.php" > "$V/update-local-1.json"; grep -E '"(success|defaults|sync_export)"' "$V/update-local-1.json"
APP_ENV=local php "$V/update.php" > "$V/update-local-2.json"; grep -E '"(success|defaults|sync_export)"' "$V/update-local-2.json"
php "$V/sync.php"
php "$V/snapshot.php" "$V/snapshot-dopo-import.json" && diff <(php -r '$s=json_decode(file_get_contents($argv[1]),true); unset($s["society_locations"]); echo json_encode($s, JSON_PRETTY_PRINT);' "$V/snapshot-prima-import.json") <(php -r '$s=json_decode(file_get_contents($argv[1]),true); unset($s["society_locations"]); echo json_encode($s, JSON_PRETTY_PRINT);' "$V/snapshot-dopo-import.json") && echo "tabelle CSS e SEO invariate"
APP_ENV=production php "$V/readonly.php"
APP_ENV=local php "$V/readonly.php"
```

Expected: primo update locale `defaults: 3`, `sync_export: true`; secondo `defaults: 0`, `sync_export: false`; `sync.php` e `readonly.php` tutte `✓`; `tabelle CSS e SEO invariate`. Se `__r()` di una route inesistente lancia un'eccezione invece di restituire `''`, sostituire nel controllo `__r(...) !== ''` con un `try/catch` che vale `false` in caso di eccezione.

- [ ] **Step 12: Pulizia dei file di prova**

```bash
cd "$S" && rm -r modules/verifica-core && rmdir modules && rm app/Models/VerificaCoreItem.php app/Resources/VerificaCoreItemResource.php custom/config/modules.php && cp "$V/backup/sync-data.json" shared/sync-data.json && cp "$V/backup/.htaccess" .htaccess && git status --short > "$V/git-status-dopo.txt"; diff "$V/git-status-prima.txt" "$V/git-status-dopo.txt"
```

Expected: nessuna differenza, oppure solo file generati da `forge update` (CSS, `robots.txt`) da elencare nel resoconto. Nel database restano: la tabella di prova `verifica_core_items` (da segnalare con il comando `DROP TABLE verifica_core_items;` per chi vuole eliminarla) e le tabelle delle sedi con i dati migrati di `new-site`, che sono lo stato corretto per la nuova versione.

- [ ] **Step 13: Controllo visivo e ripristino di `vendor`**

Il link `vendor/wonder-image/app` resta sul ramo feature finché l'utente non ha visto (o rinunciato a vedere) nel browser: elenco "Dati aziendali", scheda di una sede con suggerimenti e pulsante "Orari e chiusure", pagina "Orari e chiusure" con salvataggio di orari e chiusure, menu da `administrator`. L'accesso al backend lo fa l'utente. Poi:

```bash
cd "$S/vendor/wonder-image" && rm app && mv .app-dev-main app && ls -la
```

Expected: `app` torna una cartella.

- [ ] **Step 14: Registrare l'esito**

Nel TODO del gestionale (`packages/gestionale/TODO.md`) segnare come fatte le verifiche con database dei piani 1 e 2 e il piano 3, annotando eventuali verifiche rimaste solo visive.
