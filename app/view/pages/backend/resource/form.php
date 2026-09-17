<?php \Wonder\View\View::layout('backend.form'); ?>

<?php
    $readonly = (bool) ($READONLY ?? false);
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
                'action' => $readonly ? '' : (string) ($FORM_ACTION ?? ''),
                'footer' => $readonly
                    ? $noticeHtml
                    : '
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
      action="<?=$readonly ? '' : htmlspecialchars((string) ($FORM_ACTION ?? ''), ENT_QUOTES, 'UTF-8')?>"
      <?=$readonly ? 'onsubmit="return false"' : 'onsubmit="loadingSpinner()"'?>>
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
                <?php if (!$readonly) { ?>
                <div class="col-12">
                    <?=$submitHtml('w-100')?>
                </div>
                <?php } ?>
            </wi-card>
        </div>
        <?php } elseif (!$readonly) { ?>
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
