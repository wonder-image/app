<?php

\Wonder\View\View::layout('backend.show');

$error = (object) ($ITEM ?? []);
?>

<div class="row g-3">
    <wi-card class="col-12">
        <h6><?=htmlspecialchars((string) ($error->table ?? ''), ENT_QUOTES, 'UTF-8')?></h6>
        <div>Errore N° <?=htmlspecialchars((string) ($error->error_n ?? ''), ENT_QUOTES, 'UTF-8')?></div>
    </wi-card>

    <div class="col-8">
        <div class="row g-3">
            <wi-card class="col-12">
                <h6 class="col-12">Errore</h6>
                <div class="col-12">
                    <?=wiPreIfArray((string) ($error->error ?? ''))?>
                </div>
            </wi-card>

            <wi-card class="col-12">
                <h6 class="col-12">Query</h6>
                <div class="col-12">
                    <?=wiPreIfArray((string) ($error->query ?? ''))?>
                </div>
            </wi-card>
        </div>
    </div>

    <div class="col-4">
        <div class="row g-3">
            <wi-card class="col-12">
                <h6 class="col-12">Contesto</h6>
                <div class="col-12">
                    Tabella: <b><?=htmlspecialchars((string) ($error->table ?? ''), ENT_QUOTES, 'UTF-8')?></b><br>
                    Funzione:
                    <div class="mt-2">
                        <?=wiPreIfArray((string) ($error->function ?? ''))?>
                    </div>
                </div>
            </wi-card>
        </div>
    </div>
</div>

<?php \Wonder\View\View::end(); ?>
