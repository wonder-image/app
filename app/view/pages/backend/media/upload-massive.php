<?php \Wonder\View\View::layout('backend.main'); ?>

<div class="row g-3">
    <wi-card class="col-12">
        <h3>
            <?php if (!empty($BACK_URL)) { ?>
            <a href="<?=htmlspecialchars((string) $BACK_URL, ENT_QUOTES, 'UTF-8')?>" class="text-dark text-decoration-none"><i class="bi bi-arrow-left-short"></i></a>
            <?php } ?>
            <?=htmlspecialchars((string) ($TITLE ?? ''), ENT_QUOTES, 'UTF-8')?>
        </h3>
    </wi-card>

    <form action="" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
        <div class="row g-3">
            <div class="col-9">
                <div class="row g-3">
                    <wi-card class="col-12">
                        <div class="col-12">
                            <?=inputFileDragDrop('', 'file', 'classic')?>
                        </div>
                    </wi-card>
                </div>
            </div>

            <wi-card class="col-3">
                <div class="col-12">
                    <?=select('Tipologia', 'type', [ 'image' => 'Immagine', 'icon' => 'Icona', 'document' => 'Documento' ])?>
                </div>
                <div class="col-12">
                    <?=submit()?>
                </div>
            </wi-card>
        </div>
    </form>
</div>

<?php \Wonder\View\View::end(); ?>
