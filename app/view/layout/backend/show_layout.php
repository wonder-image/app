<?php \Wonder\View\View::layout('backend.main'); ?>

<div class="row g-3">
    <wi-card class="col-12">
        <h3>
            <a href="<?=htmlspecialchars((string) ($BACK_URL ?? ''), ENT_QUOTES, 'UTF-8')?>" class="text-dark">
                <i class="bi bi-arrow-left-short"></i>
            </a>
            <?=htmlspecialchars((string) ($TITLE ?? ''), ENT_QUOTES, 'UTF-8')?>
        </h3>
    </wi-card>

    <div class="col-12">
        <?=$PAGE_CONTENT?>
    </div>
</div>

<?php \Wonder\View\View::end(); ?>
