<?php

use Wonder\App\Integrations\Integration;
use Wonder\App\Integrations\Integrations;
use Wonder\App\Integrations\IntegrationStatus;
use Wonder\App\ResourceSchema\Inputs\InputPassword;
use Wonder\App\Resources\Config\SecurityResource;
use Wonder\Elements\Components\Badge;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\HelpText;
use Wonder\Elements\Components\IntegrationCard;
use Wonder\Elements\Components\Modal;
use Wonder\Elements\Components\SectionTitle;
use Wonder\Elements\Components\Text;

\Wonder\View\View::layout('backend.form');

$row = (array) ($VALUES ?? []);
$readonly = (bool) ($READONLY ?? false);
$integrations = Integrations::all();
$statuses = Integrations::statuses($integrations, $row);
$fields = [];

foreach ((array) ($FIELDS ?? []) as $field) {
    if (is_object($field) && property_exists($field, 'name')) {
        $fields[(string) $field->name] = $field;
    }
}

$columns = [];

foreach ($integrations as $integration) {
    $columns = [...$columns, ...array_keys($integration->fields())];
}

$values = Integrations::effectiveValues($row, $columns);

$card = static function (Integration $integration, IntegrationStatus $status): IntegrationCard {
    $card = IntegrationCard::make($integration->name(), 'integration-'.$integration->key())
        ->icon($integration->icon())
        ->variant($integration->variant())
        ->description($integration->description())
        ->status($status->label(), $status->variant());

    if ($status->test) {
        $card->badge('Test', 'warning', 'bi-cone-striped');
    }

    if ($status->env !== []) {
        $card->badge('.env', 'secondary', 'bi-file-earmark-lock');
    }

    if ($status->note !== null && $status->note !== '') {
        $card->badge($status->note, 'primary', 'bi-send');
    }

    if ($status->blockedBy !== null) {
        $card->notice('Richiede '.$status->blockedBy);
    }

    return $card;
};

$modal = static function (Integration $integration, IntegrationStatus $status) use ($fields, $values, $readonly, $FORM_ACTION): Modal {
    $editable = array_diff(array_keys(array_intersect_key($integration->fields(), $fields)), $status->env);
    $locked = $readonly || $integration->readonly() || $editable === [];
    $body = [];

    if ($integration->guide() !== '') {
        $body[] = HelpText::make($integration->guide())->columnSpan(12);
    }

    if ($status->env !== []) {
        $body[] = Text::make(Badge::make('.env')->variant('secondary')->render().' I campi in sola lettura arrivano dal file <code>.env</code> e lì si cambiano.')
            ->html()->small()->muted()->columnSpan(12);
    }

    foreach ($integration->fields() as $column => $width) {
        if (!isset($fields[$column])) {
            continue;
        }

        $field = (clone $fields[$column])->columnSpan(max(1, min(12, (int) $width)));
        $env = in_array($column, $status->env, true);
        $password = $field instanceof InputPassword;

        if ($password) {
            $field->value('')->placeholder($env ? 'Impostata nel .env' : 'Lascia vuoto per non modificarla');
        } elseif ($env) {
            $field->value((string) ($values[$column] ?? ''));
        }

        if ($env || $locked) {
            $field->readonly();
        }

        $body[] = $field;
    }

    foreach ($integration->panel($values, $status) as $component) {
        $body[] = $component;
    }

    if ($integration->docsUrl() !== null) {
        $body[] = Text::make()
            ->small()
            ->link($integration->docsUrl(), 'Guida di '.$integration->name(), ['blank' => true, 'icon' => 'bi bi-box-arrow-up-right'])
            ->columnSpan(12);
    }

    $modal = Modal::make($integration->name())
        ->id('integration-'.$integration->key())
        ->size('lg')
        ->scrollable()
        ->columns(12)
        ->components($body);

    if ($locked) {
        return $modal->cancel('Chiudi');
    }

    $modal->form((string) $FORM_ACTION, 'post', ['integration' => $integration->key()]);

    if (in_array($integration->key(), ['smtp', 'brevo-mail'], true)) {
        [$cancel, $save] = $modal->footerComponents();

        $modal->footer([
            $cancel,
            Button::make('Invia email di prova')
                ->type('submit')
                ->variant('secondary')
                ->icon('bi bi-send')
                ->attr('formaction', SecurityResource::mailTestUrl()),
            $save,
        ]);
    }

    return $modal;
};
?>

<div class="row g-4">
    <?php foreach (Integrations::grouped($integrations) as $group) { ?>
    <div class="col-12">
        <?=SectionTitle::make($group['title'])->render()?>
        <div class="row g-3">
            <?php foreach ($group['items'] as $key => $integration) { ?>
            <?php if (!isset($statuses[$key])) { continue; } ?>
            <div class="col-12 col-md-6 col-xl-4">
                <?=$card($integration, $statuses[$key])->render()?>
            </div>
            <?php } ?>
        </div>
    </div>
    <?php } ?>
</div>

<?php foreach ($integrations as $key => $integration) { ?>
<?php if (isset($statuses[$key])) { echo $modal($integration, $statuses[$key])->render(); } ?>
<?php } ?>

<?php \Wonder\View\View::end(); ?>
