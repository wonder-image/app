<?php \Wonder\View\View::layout('backend.form'); ?>

<?php
    $readonly = (bool) ($READONLY ?? false);
    // Sola lettura parziale: la pagina arriva dal deploy ma alcuni campi
    // (es. orari e chiusure della sede) restano modificabili in produzione.
    $editableWhenReadonly = (array) ($READONLY_EDITABLE ?? []);
    $partial = $readonly && $editableWhenReadonly !== [];
    $locked = $readonly && !$partial;
    $readonlyNotice = htmlspecialchars((string) ($READONLY_NOTICE ?? ''), ENT_QUOTES, 'UTF-8');
    $noticeHtml = '
        <div class="col-12">
            <wi-card class="col-12">
                <div class="alert alert-warning mb-0">'.$readonlyNotice.'</div>
            </wi-card>
        </div>';
    $submitHtml = function (string $class = ''): string {
        if (!function_exists('submit')) {
            return '<button type="submit" class="btn btn-dark'.($class !== '' ? ' '.$class : '').'">Salva</button>';
        }

        return $class !== '' ? submit('Salva', 'upload', $class) : submit('Salva', 'upload');
    };
?>

<?php if (is_object($FORM_LAYOUT ?? null)) { ?>
    <?=
        \Wonder\Backend\Support\ResourceFormLayoutRenderer::render(
            $FORM_LAYOUT,
            [
                'id' => 'resource-layout-form',
                'method' => (string) ($FORM_METHOD ?? 'POST'),
                'enctype' => (string) ($FORM_ENCTYPE ?? 'multipart/form-data'),
                'action' => $locked ? '' : (string) ($FORM_ACTION ?? ''),
                'footer' => $locked
                    ? $noticeHtml
                    : ($partial ? $noticeHtml : '').'
                    <div class="col-12">
                        <wi-card class="col-12">
                            <div class="col-12">'.$submitHtml().'</div>
                        </wi-card>
                    </div>',
            ]
        )
    ?>
<?php } else { ?>
<form method="<?=htmlspecialchars((string) ($FORM_METHOD ?? 'POST'), ENT_QUOTES, 'UTF-8')?>"
      enctype="<?=htmlspecialchars((string) ($FORM_ENCTYPE ?? 'multipart/form-data'), ENT_QUOTES, 'UTF-8')?>"
      action="<?=$locked ? '' : htmlspecialchars((string) ($FORM_ACTION ?? ''), ENT_QUOTES, 'UTF-8')?>"
      <?=$locked ? 'onsubmit="return false"' : 'onsubmit="loadingSpinner()"'?>>
    <div class="row g-3">
        <?php if ($readonly) { echo $noticeHtml; } ?>
        <div class="<?=!empty($SIDEBAR_FIELDS) ? 'col-9' : 'col-12'?>">
            <wi-card class="col-12">
                <?php foreach ((array) ($FIELDS ?? []) as $field) { ?>
                    <?php
                        if (is_object($field) && method_exists($field, 'render')) {
                            echo $field->render();
                        }
                    ?>
                <?php } ?>
            </wi-card>
        </div>

        <?php if (!empty($SIDEBAR_FIELDS)) { ?>
        <div class="col-3">
            <wi-card class="col-12">
                <?php foreach ((array) ($SIDEBAR_FIELDS ?? []) as $field) { ?>
                    <?php
                        if (is_object($field) && method_exists($field, 'render')) {
                            echo $field->render();
                        }
                    ?>
                <?php } ?>
                <?php if (!$locked) { ?>
                <div class="col-12">
                    <?=$submitHtml('w-100')?>
                </div>
                <?php } ?>
            </wi-card>
        </div>
        <?php } elseif (!$locked) { ?>
        <div class="col-12">
            <wi-card class="col-12">
                <div class="col-12">
                    <?=$submitHtml()?>
                </div>
            </wi-card>
        </div>
        <?php } ?>
    </div>
</form>
<?php } ?>

<?php \Wonder\View\View::end(); ?>
