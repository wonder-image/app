<?php \Wonder\View\View::layout('backend.form'); ?>

<?php
$renderInput = static function (array $schema, string $key, array $values = [], array $context = []): string {
    foreach ($schema as $item) {
        if (!is_object($item) || !property_exists($item, 'name') || $item->name !== $key) {
            continue;
        }

        $field = clone $item;
        $field->value($values[$key] ?? null);

        if ($context !== []) {
            $field->context($context);
        }

        return $field->render();
    }

    return '';
};
?>

<form class="col-12" action="<?=htmlspecialchars(__r('backend.config.corporate-data'), ENT_QUOTES, 'UTF-8')?>" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
    <div class="row g-3">

        <div class="col-9">
            <div class="row g-3">

                <wi-card class="col-12">
                    <div class="col-12">
                        <h6>Dati</h6>
                    </div>
                    <div class="col-8">
                        <?=$renderInput((array) ($COMPANY_FORM_SCHEMA ?? []), 'name', (array) ($VALUES ?? []))?>
                    </div>
                    <div class="col-6">
                        <?=$renderInput((array) ($COMPANY_FORM_SCHEMA ?? []), 'email', (array) ($VALUES ?? []))?>
                    </div>
                    <div class="col-3">
                        <?=$renderInput((array) ($COMPANY_FORM_SCHEMA ?? []), 'tel', (array) ($VALUES ?? []))?>
                    </div>
                    <div class="col-3">
                        <?=$renderInput((array) ($COMPANY_FORM_SCHEMA ?? []), 'cel', (array) ($VALUES ?? []))?>
                    </div>
                </wi-card>

                <wi-card class="col-12">
                    <div class="col-12">
                        <h6>Dati legali</h6>
                    </div>
                    <div class="col-8">
                        <?=$renderInput((array) ($LEGAL_FORM_SCHEMA ?? []), 'legal_name', (array) ($VALUES ?? []))?>
                    </div>
                    <div class="col-4">
                        <?=$renderInput((array) ($LEGAL_FORM_SCHEMA ?? []), 'share_capital', (array) ($VALUES ?? []))?>
                    </div>
                    <div class="col-8">
                        <?=$renderInput((array) ($LEGAL_FORM_SCHEMA ?? []), 'pec', (array) ($VALUES ?? []))?>
                    </div>
                    <div class="col-4">
                        <?=$renderInput((array) ($LEGAL_FORM_SCHEMA ?? []), 'sdi', (array) ($VALUES ?? []))?>
                    </div>
                    <div class="col-4">
                        <?=$renderInput((array) ($LEGAL_FORM_SCHEMA ?? []), 'rea', (array) ($VALUES ?? []))?>
                    </div>
                    <div class="col-4">
                        <?=$renderInput((array) ($LEGAL_FORM_SCHEMA ?? []), 'pi', (array) ($VALUES ?? []))?>
                    </div>
                    <div class="col-4">
                        <?=$renderInput((array) ($LEGAL_FORM_SCHEMA ?? []), 'cf', (array) ($VALUES ?? []))?>
                    </div>
                </wi-card>

                <wi-card class="col-6">
                    <div class="col-12">
                        <h6>Indirizzo legale</h6>
                    </div>
                    <div class="col-6">
                        <?=$renderInput((array) ($LEGAL_ADDRESS_FORM_SCHEMA ?? []), 'legal_country', (array) ($VALUES ?? []))?>
                    </div>
                    <div class="col-6">
                        <?=$renderInput(
                            (array) ($LEGAL_ADDRESS_FORM_SCHEMA ?? []),
                            'legal_province',
                            (array) ($VALUES ?? []),
                            ['country' => $VALUES['legal_country'] ?? 'IT']
                        )?>
                    </div>
                    <div class="col-8">
                        <?=$renderInput((array) ($LEGAL_ADDRESS_FORM_SCHEMA ?? []), 'legal_city', (array) ($VALUES ?? []))?>
                    </div>
                    <div class="col-4">
                        <?=$renderInput((array) ($LEGAL_ADDRESS_FORM_SCHEMA ?? []), 'legal_cap', (array) ($VALUES ?? []))?>
                    </div>
                    <div class="col-10">
                        <?=$renderInput((array) ($LEGAL_ADDRESS_FORM_SCHEMA ?? []), 'legal_street', (array) ($VALUES ?? []))?>
                    </div>
                    <div class="col-2">
                        <?=$renderInput((array) ($LEGAL_ADDRESS_FORM_SCHEMA ?? []), 'legal_number', (array) ($VALUES ?? []))?>
                    </div>
                    <div class="col-12">
                        <?=$renderInput((array) ($LEGAL_ADDRESS_FORM_SCHEMA ?? []), 'legal_more', (array) ($VALUES ?? []))?>
                    </div>
                    <div class="col-12">
                        <?=$renderInput((array) ($LEGAL_ADDRESS_FORM_SCHEMA ?? []), 'legal_gmaps', (array) ($VALUES ?? []))?>
                    </div>
                </wi-card>

                <wi-card class="col-6">
                    <div class="col-12">
                        <h6>Indirizzo aziendale</h6>
                    </div>
                    <div class="col-6">
                        <?=$renderInput((array) ($ADDRESS_FORM_SCHEMA ?? []), 'country', (array) ($VALUES ?? []))?>
                    </div>
                    <div class="col-6">
                        <?=$renderInput(
                            (array) ($ADDRESS_FORM_SCHEMA ?? []),
                            'province',
                            (array) ($VALUES ?? []),
                            ['country' => $VALUES['country'] ?? 'IT']
                        )?>
                    </div>
                    <div class="col-8">
                        <?=$renderInput((array) ($ADDRESS_FORM_SCHEMA ?? []), 'city', (array) ($VALUES ?? []))?>
                    </div>
                    <div class="col-4">
                        <?=$renderInput((array) ($ADDRESS_FORM_SCHEMA ?? []), 'cap', (array) ($VALUES ?? []))?>
                    </div>
                    <div class="col-10">
                        <?=$renderInput((array) ($ADDRESS_FORM_SCHEMA ?? []), 'street', (array) ($VALUES ?? []))?>
                    </div>
                    <div class="col-2">
                        <?=$renderInput((array) ($ADDRESS_FORM_SCHEMA ?? []), 'number', (array) ($VALUES ?? []))?>
                    </div>
                    <div class="col-12">
                        <?=$renderInput((array) ($ADDRESS_FORM_SCHEMA ?? []), 'more', (array) ($VALUES ?? []))?>
                    </div>
                    <div class="col-12">
                        <?=$renderInput((array) ($ADDRESS_FORM_SCHEMA ?? []), 'gmaps', (array) ($VALUES ?? []))?>
                    </div>
                </wi-card>

                <wi-card class="col-12">
                    <?php if (isset($TIMETABLE_REPEATER_FIELD) && is_object($TIMETABLE_REPEATER_FIELD)) { ?>
                        <?php $timetableField = clone $TIMETABLE_REPEATER_FIELD; ?>
                        <?=$timetableField->value((array) ($TIMETABLES ?? []))->render()?>
                    <?php } ?>
                </wi-card>

            </div>
        </div>

        <wi-card class="col-3">
            <div class="col-12">
                <h6>Link</h6>
            </div>
            <?php foreach ((array) ($SOCIAL_FORM_SCHEMA ?? []) as $item) { ?>
                <?php if (!is_object($item) || !property_exists($item, 'name')) { continue; } ?>
                <div class="col-12">
                    <?=$renderInput((array) ($SOCIAL_FORM_SCHEMA ?? []), (string) $item->name, (array) ($VALUES ?? []))?>
                </div>
            <?php } ?>
            <div class="col-12">
                <?=submit('Modifica dati', 'modify'); ?>
            </div>
        </wi-card>

    </div>
</form>

<?php \Wonder\View\View::end(); ?>
