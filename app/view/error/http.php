<?php

$ERROR = (int) ($ERROR ?? ($_GET['errCode'] ?? 500));
$ERROR_MESSAGE = trim((string) ($ERROR_MESSAGE ?? ''));
$ERROR_FILE = trim((string) ($ERROR_FILE ?? ''));
$ERROR_LINE = (int) ($ERROR_LINE ?? 0);
$ERROR_TRACE = trim((string) ($ERROR_TRACE ?? ''));
$ERROR_REQUEST_ID = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ($_SERVER['REQUEST_ID'] ?? '')));
$ERROR_DEBUG_VISIBLE = $ERROR_MESSAGE !== '' || $ERROR_FILE !== '' || $ERROR_TRACE !== '';

$ERROR_MAP = [
    400 => [
        'title' => 'Richiesta non valida',
        'summary' => 'La richiesta non puo essere elaborata cosi come e stata inviata.',
        'hint' => 'Verifica i dati inseriti e riprova.',
    ],
    401 => [
        'title' => 'Accesso richiesto',
        'summary' => 'Per continuare devi autenticarti prima di accedere a questa risorsa.',
        'hint' => 'Effettua il login e ripeti l\'operazione.',
    ],
    403 => [
        'title' => 'Accesso negato',
        'summary' => 'Hai raggiunto una risorsa che non e disponibile per il tuo account o per questa richiesta.',
        'hint' => 'Controlla i permessi oppure torna alla dashboard.',
    ],
    404 => [
        'title' => 'Pagina non trovata',
        'summary' => 'L\'indirizzo richiesto non esiste oppure non e piu disponibile.',
        'hint' => 'Controlla il link oppure torna alla home.',
    ],
    405 => [
        'title' => 'Metodo non consentito',
        'summary' => 'La risorsa esiste, ma non accetta questo tipo di richiesta.',
        'hint' => 'Ricarica la pagina o ripeti l\'azione dal flusso corretto.',
    ],
    422 => [
        'title' => 'Dati non validi',
        'summary' => 'La richiesta e stata ricevuta ma alcuni dati non sono corretti o completi.',
        'hint' => 'Rivedi i campi compilati e prova di nuovo.',
    ],
    429 => [
        'title' => 'Troppe richieste',
        'summary' => 'Sono state inviate troppe richieste in poco tempo.',
        'hint' => 'Attendi qualche secondo prima di riprovare.',
    ],
    500 => [
        'title' => 'Errore interno',
        'summary' => 'Si e verificato un problema inatteso durante l\'elaborazione della richiesta.',
        'hint' => 'Riprova tra poco. Se il problema continua, segnala l\'orario e il codice richiesta.',
    ],
    503 => [
        'title' => 'Servizio non disponibile',
        'summary' => 'Il servizio non e temporaneamente disponibile oppure e in manutenzione.',
        'hint' => 'Attendi qualche minuto e riprova.',
    ],
];

$ERROR_META = $ERROR_MAP[$ERROR] ?? [
    'title' => 'Errore applicativo',
    'summary' => 'La richiesta non puo essere completata in questo momento.',
    'hint' => 'Riprova tra poco oppure torna alla pagina precedente.',
];

$homeUrl = '/';
if (function_exists('__u')) {
    $resolvedHomeUrl = trim((string) __u());
    if ($resolvedHomeUrl !== '') {
        $homeUrl = $resolvedHomeUrl;
    }
}

http_response_code($ERROR);

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=htmlspecialchars($ERROR_META['title'], ENT_QUOTES, 'UTF-8')?> | Errore <?=$ERROR?></title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4efe8;
            --card: rgba(255, 255, 255, 0.82);
            --card-border: rgba(46, 33, 24, 0.12);
            --text: #2e2118;
            --muted: #6f6257;
            --accent: #b85c38;
            --accent-dark: #8f4324;
            --shadow: 0 24px 80px rgba(56, 35, 24, 0.14);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(184, 92, 56, 0.20), transparent 32%),
                radial-gradient(circle at bottom right, rgba(143, 67, 36, 0.16), transparent 28%),
                linear-gradient(135deg, #f7f2ec 0%, var(--bg) 52%, #efe7de 100%);
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .error-shell {
            width: min(920px, 100%);
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: 28px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(16px);
            overflow: hidden;
        }

        .error-grid {
            display: grid;
            grid-template-columns: minmax(0, 280px) minmax(0, 1fr);
        }

        .error-code,
        .error-content {
            padding: 40px;
        }

        .error-code {
            background: linear-gradient(180deg, rgba(184, 92, 56, 0.12), rgba(184, 92, 56, 0.02));
            border-right: 1px solid var(--card-border);
        }

        .error-kicker {
            display: inline-flex;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--accent-dark);
            background: rgba(184, 92, 56, 0.10);
        }

        .error-number {
            margin: 18px 0 8px;
            font-size: clamp(64px, 10vw, 116px);
            line-height: 0.9;
            font-weight: 800;
            letter-spacing: -0.05em;
        }

        .error-title {
            margin: 0;
            font-size: clamp(28px, 4vw, 40px);
            line-height: 1.05;
        }

        .error-summary {
            margin: 14px 0 0;
            color: var(--muted);
            font-size: 18px;
            line-height: 1.65;
        }

        .error-hint {
            margin: 18px 0 0;
            padding: 16px 18px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(46, 33, 24, 0.08);
            line-height: 1.55;
        }

        .error-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 26px;
        }

        .error-button {
            appearance: none;
            border: 0;
            border-radius: 999px;
            padding: 14px 18px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: transform 160ms ease, box-shadow 160ms ease, background 160ms ease;
        }

        .error-button:hover {
            transform: translateY(-1px);
        }

        .error-button-primary {
            color: #fff;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            box-shadow: 0 14px 26px rgba(143, 67, 36, 0.24);
        }

        .error-button-secondary {
            color: var(--text);
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(46, 33, 24, 0.12);
        }

        .error-meta {
            margin-top: 18px;
            color: var(--muted);
            font-size: 14px;
        }

        .error-debug {
            margin-top: 24px;
            padding: 22px;
            border-radius: 20px;
            border: 1px solid rgba(46, 33, 24, 0.12);
            background: rgba(46, 33, 24, 0.04);
        }

        .error-debug h2 {
            margin: 0 0 14px;
            font-size: 18px;
        }

        .error-debug p,
        .error-debug pre {
            margin: 0;
            line-height: 1.6;
        }

        .error-debug-row + .error-debug-row {
            margin-top: 12px;
        }

        .error-debug pre {
            overflow: auto;
            padding: 14px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(46, 33, 24, 0.08);
            white-space: pre-wrap;
            word-break: break-word;
        }

        @media (max-width: 760px) {
            .error-grid {
                grid-template-columns: 1fr;
            }

            .error-code {
                border-right: 0;
                border-bottom: 1px solid var(--card-border);
            }

            .error-code,
            .error-content {
                padding: 28px 22px;
            }
        }
    </style>
</head>
<body>
    <main class="error-shell">
        <section class="error-grid">
            <aside class="error-code">
                <div class="error-kicker">HTTP Error</div>
                <div class="error-number"><?=$ERROR?></div>
                <p class="error-summary"><?=htmlspecialchars($ERROR_META['summary'], ENT_QUOTES, 'UTF-8')?></p>
            </aside>

            <section class="error-content">
                <h1 class="error-title"><?=htmlspecialchars($ERROR_META['title'], ENT_QUOTES, 'UTF-8')?></h1>
                <div class="error-hint"><?=htmlspecialchars($ERROR_META['hint'], ENT_QUOTES, 'UTF-8')?></div>

                <div class="error-actions">
                    <a class="error-button error-button-primary" href="<?=htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8')?>">Torna alla home</a>
                    <button class="error-button error-button-secondary" type="button" onclick="window.history.back()">Torna indietro</button>
                </div>

                <?php if ($ERROR_REQUEST_ID !== '') { ?>
                <div class="error-meta">
                    Codice richiesta: <strong><?=htmlspecialchars($ERROR_REQUEST_ID, ENT_QUOTES, 'UTF-8')?></strong>
                </div>
                <?php } ?>

                <?php if ($ERROR_DEBUG_VISIBLE) { ?>
                <section class="error-debug">
                    <h2>Dettagli tecnici</h2>

                    <?php if ($ERROR_MESSAGE !== '') { ?>
                    <div class="error-debug-row">
                        <p><strong>Messaggio:</strong> <?=htmlspecialchars($ERROR_MESSAGE, ENT_QUOTES, 'UTF-8')?></p>
                    </div>
                    <?php } ?>

                    <?php if ($ERROR_FILE !== '') { ?>
                    <div class="error-debug-row">
                        <p><strong>File:</strong> <?=htmlspecialchars($ERROR_FILE, ENT_QUOTES, 'UTF-8')?><?php if ($ERROR_LINE > 0) { ?>:<?=$ERROR_LINE?><?php } ?></p>
                    </div>
                    <?php } ?>

                    <?php if ($ERROR_TRACE !== '') { ?>
                    <div class="error-debug-row">
                        <p><strong>Trace:</strong></p>
                        <pre><?=htmlspecialchars($ERROR_TRACE, ENT_QUOTES, 'UTF-8')?></pre>
                    </div>
                    <?php } ?>
                </section>
                <?php } ?>
            </section>
        </section>
    </main>
</body>
</html>
