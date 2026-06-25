<?php \Wonder\View\View::layout('backend.form'); ?>

<?php
$renderInput = static function (array $schema, string $key): string {
    foreach ($schema as $item) {
        if (!is_object($item) || !property_exists($item, 'name') || $item->name !== $key) {
            continue;
        }

        return (clone $item)->render();
    }

    return '';
};
?>

<div class="row g-3">
    <div class="col-12">
        <div class="card border">
            <div class="card-body row g-3">
                <div class="col-12">
                    <h6 class="mb-2">Esportazione database</h6>
                    <div class="text-body-secondary">
                        Seleziona una tabella e il formato di esportazione, poi apri il download in una nuova scheda.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border">
            <div class="card-body row g-3">
                <div class="col-4">
                    <?=$renderInput((array) ($FORM_SCHEMA ?? []), 'table')?>
                </div>

                <div class="col-4">
                    <?=$renderInput((array) ($FORM_SCHEMA ?? []), 'format')?>
                </div>

                <div class="col-12">
                    <button type="button" class="btn btn-dark" onclick="sqlDownloadExport()">
                        Scarica
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function sqlDownloadExport() {
        let table = '';
        let format = '';

        document.querySelectorAll("input[name='table']").forEach(function (element) {
            if (element.checked) {
                table = element.value;
            }
        });

        const formatField = document.querySelector("select[name='format']");
        if (formatField && formatField.value !== '') {
            format = formatField.value;
        }

        if (table === '' || format === '') {
            return;
        }

        const link = pathApp + '/api/export.php?table=' + encodeURIComponent(table) + '&format=' + encodeURIComponent(format);
        window.open(link, '_blank');
    }
</script>

<?php \Wonder\View\View::end(); ?>
