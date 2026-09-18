<?php

use Wonder\Elements\Components\InfoCard;

\Wonder\View\View::layout('backend.show');
$run = (array) ($ITEM ?? []);
foreach (\Wonder\App\Resources\Scheduler\RunResource::labelSchema() as $key => $label) {
    if (in_array($key, ['output', 'result'], true)) { continue; }
    echo (new InfoCard($label, $run[$key] ?? 'Non disponibile'))->render('bootstrap');
}
?>
<h2>Output</h2><pre><?= e((string) ($run['output'] ?? '')) ?></pre>
<h2>Risultato</h2><pre><?= e((string) ($run['result'] ?? '')) ?></pre>
<?php \Wonder\View\View::end(); ?>
