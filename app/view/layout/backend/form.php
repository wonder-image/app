<?php \Wonder\View\View::layout('backend.main'); ?>

<div class="row g-3">
    <wi-card class="col-12">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <h3 class="mb-0">
                <?php if (!empty($BACK_URL)) { ?>
                <a href="<?=htmlspecialchars((string) ($BACK_URL ?? ''), ENT_QUOTES, 'UTF-8')?>" class="text-dark text-decoration-none"><i class="bi bi-arrow-left-short"></i></a>
                <?php } ?>
                <?=htmlspecialchars((string) ($TITLE ?? ''), ENT_QUOTES, 'UTF-8')?>
            </h3>
            <?php if (!empty($DOCS_URL)) { ?>
            <a href="<?=htmlspecialchars((string) $DOCS_URL, ENT_QUOTES, 'UTF-8')?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary">
                <i class="bi bi-question-circle"></i> <?=htmlspecialchars((string) ($DOCS_LABEL ?? ''), ENT_QUOTES, 'UTF-8')?>
            </a>
            <?php } ?>
        </div>
    </wi-card>

    <?php if (!empty($FORM_ERRORS)) { ?>
    <wi-card class="col-12">
        <div class="col-12">
            <div class="alert alert-danger mb-0">
                Operazione non riuscita. Controlla i campi del form.
            </div>
        </div>
    </wi-card>
    <?php } ?>

    <div class="col-12">
        <?=$PAGE_CONTENT?>
    </div>
</div>

<?php \Wonder\View\View::end(); ?>
