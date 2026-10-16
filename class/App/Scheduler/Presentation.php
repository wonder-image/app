<?php

namespace Wonder\App\Scheduler;

use Wonder\Elements\Components\{Badge, Button, Modal, Text};
use Wonder\Themes\Support\PageAssets;

final class Presentation
{
    public static function number(mixed $value, float $factor = 1, string $unit = ''): string
    {
        return $value === null ? 'Non disponibile' : number_format((float) $value / $factor, 2, ',', '.').($unit !== '' ? ' '.$unit : '');
    }

    public static function status(string $status): string
    {
        [$label, $color] = match ($status) {
            'success' => ['Riuscita', 'success'], 'failed' => ['Fallita', 'danger'],
            'interrupted' => ['Interrotta', 'warning'], 'running' => ['In esecuzione', 'primary'],
            'pending' => ['In attesa', 'secondary'], 'skipped' => ['Saltata', 'secondary'],
            default => [$status, 'secondary'],
        };
        return Badge::make($label)->variant($color)->render('bootstrap');
    }

    /** Badge dello stato: attiva, sospesa o sempre attiva. */
    public static function state(array $row): string
    {
        [$label, $color] = Repository::isAlwaysEnabled($row) ? ['Sempre attiva', 'success']
            : ((($row['enabled'] ?? '') === 'true') ? ['Attiva', 'success'] : ['Sospesa', 'secondary']);

        return Badge::make($label)->variant($color)->render('bootstrap');
    }

    /**
     * Etichetta e campi del POST che attiva o sospende la pianificazione
     * (`toggle`), per la voce del menu e il bottone del dettaglio; `null`
     * per quelle sempre attive. `$return` (`list` o `view`) dice dove tornare.
     *
     * @return array{label: string, hidden: array<string, string>}|null
     */
    public static function toggleAction(array $row, string $return = 'list'): ?array
    {
        if (Repository::isAlwaysEnabled($row)) {
            return null;
        }

        $enabled = ($row['enabled'] ?? '') === 'true';

        return [
            'label' => $enabled ? 'Sospendi' : 'Attiva',
            'hidden' => ['enabled' => $enabled ? 'false' : 'true', 'return' => $return],
        ];
    }

    public const CRON_MODAL = 'wi-scheduler-cron';

    /** Bottone dell'intestazione della lista che apre `cronModal()`. */
    public static function cronButton(): Button
    {
        return Button::make('Cron sul server')->variant('light')->opensModal(self::CRON_MODAL);
    }

    /** Istruzioni per il cron del server, in una finestra aperta da `cronButton()`. */
    public static function cronModal(string $root): string
    {
        $command = '/usr/local/bin/php '.rtrim($root, '/').'/bin/scheduler.php';
        $fields = ['Minuto', 'Ora', 'Giorno del mese', 'Mese', 'Giorno della settimana'];

        return Modal::make('Cron sul server')->id(self::CRON_MODAL)->size('lg')
            ->components([
                '<p class="text-body-secondary mb-2">In cPanel &rarr; <b>Processi Cron</b> aggiungi un solo comando, eseguito ogni minuto:</p>'
                .'<pre class="bg-body-tertiary border rounded p-2 mb-3 text-wrap"><code>'.e($command).'</code></pre>'
                .'<div class="table-responsive"><table class="table table-sm table-bordered text-center align-middle mb-3">'
                .'<thead><tr><th>'.implode('</th><th>', array_map('e', $fields)).'</th></tr></thead>'
                .'<tbody><tr>'.str_repeat('<td><code>*</code></td>', count($fields)).'</tr></tbody></table></div>'
                .'<p class="small text-body-secondary mb-0">Il percorso del PHP (<code>/usr/local/bin/php</code>) dipende dall\'hosting; il PHP da riga di comando deve consentire <code>proc_open</code>.</p>',
            ])
            ->footer([Button::make('Chiudi')->variant('dark')->attr('data-bs-dismiss', 'modal')])
            ->render('bootstrap');
    }

    /**
     * Finestra dell'esito e script di "Esegui ora", una volta per pagina.
     * Il click arriva dopo la conferma `data-wi-confirm` della lib: lo script
     * chiede l'esecuzione, mostra lo spinner finché lo scheduler non la
     * conclude e poi apre la finestra con l'esito.
     */
    public static function runAssets(): string
    {
        return PageAssets::once('wi-scheduler-run', static function (): string {
            $modal = Modal::make('Esecuzione')->id('wi-scheduler-run-result')->size('lg')
                ->components([Text::make('')->tag('div')->attr('data-wi-scheduler-run-body', 'true')])
                ->footer([
                    Button::make('Apri esecuzione')->href('#')->variant('outline-dark')->class('d-none')->attr('data-wi-scheduler-run-link', 'true'),
                    Button::make('Chiudi')->variant('dark')->attr('data-bs-dismiss', 'modal'),
                ])
                ->render('bootstrap');

            return $modal.'<script>'.self::runScript().'</script>';
        });
    }

    private static function runScript(): string
    {
        return <<<'JS'
(function () {
    if (window.wiSchedulerRun) { return; }
    window.wiSchedulerRun = true;
    var labels = { success: 'Riuscita', failed: 'Fallita', interrupted: 'Interrotta', skipped: 'Saltata' };
    var wait = function (ms) { return new Promise(function (resolve) { setTimeout(resolve, ms); }); };
    var token = function () { var meta = document.querySelector('meta[name="wi-csrf"]'); return meta ? meta.content : ''; };
    function request(url, method) {
        return fetch(url, { method: method, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-WI-CSRF': token() } })
            .then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (data) {
                    if (!response.ok) { throw new Error(data.message || 'Richiesta non riuscita (' + response.status + ').'); }
                    return data;
                });
            });
    }
    function show(title, text, run) {
        var modal = document.getElementById('wi-scheduler-run-result');
        if (!modal || !window.wi || !wi.modal) { window.alert(title + '\n\n' + text); return; }
        var body = modal.querySelector('[data-wi-scheduler-run-body]');
        body.textContent = '';
        var message = document.createElement('p');
        message.textContent = text;
        body.appendChild(message);
        var detail = run && run.output;
        if (detail) {
            var pre = document.createElement('pre');
            pre.className = 'mb-0 small text-wrap text-break bg-light p-2 rounded';
            pre.textContent = detail;
            body.appendChild(pre);
        }
        var link = modal.querySelector('[data-wi-scheduler-run-link]');
        if (link) {
            link.classList.toggle('d-none', !(run && run.url));
            if (run && run.url) { link.setAttribute('href', run.url); }
        }
        wi.modal.open('wi-scheduler-run-result', { title: title });
    }
    async function run(button) {
        loadingSpinner(true);
        try {
            var start = await request(button.getAttribute('data-wi-scheduler-run'), 'POST');
            var begun = Date.now();
            while (true) {
                await wait(3000);
                var state = await request(start.status_url, 'GET');
                var current = state.run;
                var elapsed = (Date.now() - begun) / 1000;
                if (current && current.status !== 'pending' && current.status !== 'running') {
                    var ok = current.status === 'success';
                    show(ok ? 'Esecuzione riuscita' : 'Esecuzione ' + (labels[current.status] || current.status).toLowerCase(),
                        ok ? 'La pianificazione è stata eseguita correttamente.' : (current.status === 'skipped'
                            ? "Lo scheduler ha saltato l'esecuzione (pianificazione sospesa o già in corso)."
                            : "L'esecuzione non è andata a buon fine."), current);
                    return;
                }
                if (!current && (elapsed > 150 || (state.heartbeat_age !== null && state.heartbeat_age > 180 && elapsed > 10))) {
                    show('Esecuzione in coda', "Lo scheduler non ha ancora preso in carico la richiesta: controlla che il cron del server sia attivo. L'esecuzione resta in coda e partirà al prossimo passaggio.", null);
                    return;
                }
                if (current && elapsed > 150 + state.timeout + 30) {
                    show('Esecuzione ancora in corso', "L'esecuzione sta impiegando più del previsto: l'esito comparirà nel registro.", current);
                    return;
                }
            }
        } catch (error) {
            show('Esecuzione non avviata', error.message, null);
        } finally {
            loadingSpinner(false);
        }
    }
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-wi-scheduler-run]');
        if (!button || button.disabled || button.getAttribute('aria-disabled') === 'true') { return; }
        event.preventDefault();
        run(button);
    });
})();
JS;
    }
}
