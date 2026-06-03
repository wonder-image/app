<?php \Wonder\View\View::layout('backend.main'); ?>

<div class="row g-3">
    <wi-card class="col-12">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div class="flex-grow-1 min-w-0">
                <h3 class="mb-0">
                    <?php if (!empty($BACK_URL)) : ?>
                        <a href="<?=htmlspecialchars((string) $BACK_URL, ENT_QUOTES, 'UTF-8')?>" class="text-dark text-decoration-none"><i class="bi bi-arrow-left-short"></i></a>
                    <?php endif; ?>
                    <?=htmlspecialchars((string) ($TITLE ?? ''), ENT_QUOTES, 'UTF-8')?>
                </h3>
                <?php if (!empty($SUBTITLE)) : ?>
                    <div class="text-body-secondary small mt-1 ms-4 ps-1">
                        <?=htmlspecialchars((string) $SUBTITLE, ENT_QUOTES, 'UTF-8')?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($ACTIONS) && is_array($ACTIONS)) : ?>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <?php foreach ($ACTIONS as $action) :
                        $href     = (string) ($action['href'] ?? '');
                        $label    = (string) ($action['label'] ?? '');
                        $class    = (string) ($action['class'] ?? 'btn-primary');
                        $icon     = (string) ($action['icon'] ?? '');
                        $target   = (string) ($action['target'] ?? '');
                        $onclick  = (string) ($action['onclick'] ?? '');

                        $iconHtml = $icon !== '' ? '<i class="'.htmlspecialchars($icon, ENT_QUOTES, 'UTF-8').'"></i> ' : '';
                        $extra    = [];

                        if ($target !== '') {
                            $extra[] = 'target="'.htmlspecialchars($target, ENT_QUOTES, 'UTF-8').'"';
                            if ($target === '_blank') {
                                $extra[] = 'rel="noopener noreferrer"';
                            }
                        }

                        if ($onclick !== '') {
                            $extra[] = 'onclick="'.htmlspecialchars($onclick, ENT_QUOTES, 'UTF-8').'"';
                        }

                        $extraAttr = $extra === [] ? '' : ' '.implode(' ', $extra);
                        $tag = $href !== '' ? 'a' : 'button';
                        $hrefAttr = $href !== '' ? ' href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'"' : ' type="button"';
                    ?>
                        <<?=$tag?> class="btn <?=htmlspecialchars($class, ENT_QUOTES, 'UTF-8')?>"<?=$hrefAttr?><?=$extraAttr?>>
                            <?=$iconHtml?><?=htmlspecialchars($label, ENT_QUOTES, 'UTF-8')?>
                        </<?=$tag?>>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </wi-card>

    <div class="col-12">
        <?=$PAGE_CONTENT?>
    </div>
</div>

<?php \Wonder\View\View::end(); ?>
