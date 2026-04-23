<?php \Wonder\View\View::layout('backend.form'); ?>

<?php
$renderInput = static function (string $key, mixed $value = null) use ($RESOURCE_CLASS): string {
    $field = $RESOURCE_CLASS::getInput($key);

    if ($value !== null) {
        $field->value($value);
    }

    return $field->render();
};
?>

<form action="<?=htmlspecialchars(__r('backend.config.configuration-file'), ENT_QUOTES, 'UTF-8')?>" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
    <div class="row g-3">
        <div class="col-12">
            <div class="card border">
                <div class="card-body row g-3">
                    <div class="col-12">
                <h6 class="mb-2">Editor file configurazione</h6>
                <div class="text-body-secondary">
                    Qui puoi modificare direttamente i file <code>.htaccess</code> e <code>robots.txt</code> del progetto host.
                </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($ERRORS)) { ?>
        <div class="col-12">
            <div class="card border">
                <div class="card-body row g-3">
                    <div class="col-12">
                <div class="alert alert-danger mb-0">
                    <?php foreach ($ERRORS as $message) { ?>
                        <div><?=htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8')?></div>
                    <?php } ?>
                </div>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>

        <div class="col-6">
            <div class="card border h-100">
                <div class="card-body row g-3">
                    <div class="col-12">
                <h6 class="mb-2">File .htaccess</h6>
                <div class="text-body-secondary small">
                    Percorso: <code><?=htmlspecialchars((string) ($HTACCESS_PATH ?? ''), ENT_QUOTES, 'UTF-8')?></code>
                </div>
            </div>
            <div class="col-12">
                <?=$renderInput('htaccess', (string) ($HTACCESS_VALUE ?? ''))?>
            </div>
                </div>
            </div>
        </div>

        <div class="col-6">
            <div class="card border h-100">
                <div class="card-body row g-3">
                    <div class="col-12">
                <h6 class="mb-2">File robots.txt</h6>
                <div class="text-body-secondary small">
                    Percorso: <code><?=htmlspecialchars((string) ($ROBOTS_PATH ?? ''), ENT_QUOTES, 'UTF-8')?></code>
                </div>
            </div>
            <div class="col-12">
                <?=$renderInput('robots', (string) ($ROBOTS_VALUE ?? ''))?>
            </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card border">
                <div class="card-body row g-3">
                    <div class="col-12">
                <button type="submit" name="modify" value="true" class="btn btn-dark">
                    Modifica
                </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const htaccess = document.querySelector("textarea[name='htaccess']");
        const robots = document.querySelector("textarea[name='robots']");

        if (htaccess) {
            htaccess.style.height = '500px';
        }

        if (robots) {
            robots.style.height = '500px';
        }
    });
</script>

<?php \Wonder\View\View::end(); ?>
