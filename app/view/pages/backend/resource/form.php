<?php \Wonder\View\View::layout('backend.form'); ?>

<form method="<?=htmlspecialchars((string) ($FORM_METHOD ?? 'POST'), ENT_QUOTES, 'UTF-8')?>"
      enctype="<?=htmlspecialchars((string) ($FORM_ENCTYPE ?? 'multipart/form-data'), ENT_QUOTES, 'UTF-8')?>"
      action="<?=htmlspecialchars((string) ($FORM_ACTION ?? ''), ENT_QUOTES, 'UTF-8')?>"
      onsubmit="loadingSpinner()">
    <div class="row g-3">
        <div class="<?=!empty($SIDEBAR_FIELDS) ? 'col-9' : 'col-12'?>">
            <wi-card class="col-12">
                <?php foreach ((array) ($FIELDS ?? []) as $field) { ?>
                    <?=$field->render()?>
                <?php } ?>
            </wi-card>
        </div>

        <?php if (!empty($SIDEBAR_FIELDS)) { ?>
        <div class="col-3">
            <wi-card class="col-12">
                <?php foreach ((array) ($SIDEBAR_FIELDS ?? []) as $field) { ?>
                    <?=$field->render()?>
                <?php } ?>
                <div class="col-12">
                    <button type="submit" class="btn btn-dark w-100">Salva</button>
                </div>
            </wi-card>
        </div>
        <?php } else { ?>
        <div class="col-12">
            <wi-card class="col-12">
                <div class="col-12">
                    <button type="submit" class="btn btn-dark">Salva</button>
                </div>
            </wi-card>
        </div>
        <?php } ?>
    </div>
</form>

<?php \Wonder\View\View::end(); ?>
