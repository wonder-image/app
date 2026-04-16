<?php \Wonder\View\View::layout('backend.show'); ?>

    <wi-card class="col-12">
        <?php foreach ((array) ($ITEM ?? []) as $key => $value) { ?>
        <div class="col-12">
            <strong><?=htmlspecialchars((string) ($RESOURCE_CLASS::getLabel((string) $key) ?: $key), ENT_QUOTES, 'UTF-8')?>:</strong>
            <?=htmlspecialchars(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8')?>
        </div>
        <?php } ?>
    </wi-card>

<?php \Wonder\View\View::end(); ?>
