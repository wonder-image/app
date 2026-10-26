<?php \Wonder\View\View::layout('backend.main'); ?>

<div class="row g-3">
    <wi-card class="col-12">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div class="flex-grow-1 min-w-0">
                <h3 class="mb-0">
                    <?php if (!empty($BACK_URL)) { ?>
                    <a href="<?=htmlspecialchars((string) ($BACK_URL ?? ''), ENT_QUOTES, 'UTF-8')?>" class="text-dark text-decoration-none" data-wi-save-bar-cancel><i class="bi bi-arrow-left-short"></i></a>
                    <?php } ?>
                    <?=htmlspecialchars((string) ($TITLE ?? ''), ENT_QUOTES, 'UTF-8')?>
                </h3>
                <?php if (!empty($SUBTITLE)) { ?>
                <div class="text-body-secondary small mt-1">
                    <?=htmlspecialchars((string) $SUBTITLE, ENT_QUOTES, 'UTF-8')?>
                </div>
                <?php } ?>
            </div>
            <?php include __DIR__.'/partials/header-actions.php'; ?>
        </div>
    </wi-card>

    <?php if (!empty($FORM_ERRORS)) { ?>
    <wi-card class="col-12">
        <div class="col-12">
            <?=\Wonder\Elements\Components\Alert::make(
                trim((string) ($FORM_ERROR_MESSAGE ?? '')) !== ''
                    ? (string) $FORM_ERROR_MESSAGE
                    : 'Operazione non riuscita. Controlla i campi del form.',
                'error'
            )->inline()->addClass('mb-0')->render()?>
        </div>
    </wi-card>
    <?php } ?>

    <?php foreach ((array) ($FORM_NOTICES ?? []) as $notice) { ?>
    <div class="col-12">
        <?php // formNotices() usa i livelli Bootstrap (info, warning, danger). ?>
        <?=\Wonder\Elements\Components\Alert::make(
            (string) $notice['message'],
            match ((string) $notice['level']) { 'danger' => 'error', 'warning' => 'warning', default => 'info' }
        )->inline()->addClass('mb-0')->render()?>
    </div>
    <?php } ?>

    <div class="col-12">
        <?=$PAGE_CONTENT?>
    </div>
</div>

<?php \Wonder\View\View::end(); ?>
