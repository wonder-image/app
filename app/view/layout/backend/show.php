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
                    <div class="text-body-secondary small mt-1">
                        <?=htmlspecialchars((string) $SUBTITLE, ENT_QUOTES, 'UTF-8')?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($ACTIONS) && is_array($ACTIONS)) : ?>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <?php foreach ($ACTIONS as $action) :
                        $label    = (string) ($action['label'] ?? '');
                        $class    = (string) ($action['class'] ?? 'btn-primary');
                        $icon     = (string) ($action['icon'] ?? '');
                        $items    = (array) ($action['items'] ?? []);

                        $iconHtml = $icon !== '' ? '<i class="'.htmlspecialchars($icon, ENT_QUOTES, 'UTF-8').' ml-1"></i> ' : '';
                    ?>
                        <?php if ($items !== []) :
                            $align     = (string) ($action['align'] ?? 'end');
                            $menuClass = $align === 'start' ? 'dropdown-menu' : 'dropdown-menu dropdown-menu-end';
                        ?>
                            <div class="dropdown">
                                <button type="button" class="btn <?=htmlspecialchars($class, ENT_QUOTES, 'UTF-8')?> dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?=$iconHtml?><?=htmlspecialchars($label, ENT_QUOTES, 'UTF-8')?>
                                </button>
                                <ul class="<?=$menuClass?>">
                                    <?php foreach ($items as $item) :
                                        $kind = (string) ($item['kind'] ?? 'item');

                                        if ($kind === 'divider') : ?>
                                            <li><hr class="dropdown-divider"></li>
                                        <?php elseif ($kind === 'header') : ?>
                                            <li><h6 class="dropdown-header"><?=htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8')?></h6></li>
                                        <?php else :
                                            $iLabel    = (string) ($item['label'] ?? '');
                                            $iHref     = (string) ($item['href'] ?? '');
                                            $iIcon     = (string) ($item['icon'] ?? '');
                                            $iTarget   = (string) ($item['target'] ?? '');
                                            $iOnclick  = (string) ($item['onclick'] ?? '');
                                            $iDisabled = !empty($item['disabled']);

                                            $iIconHtml = $iIcon !== '' ? '<i class="'.htmlspecialchars($iIcon, ENT_QUOTES, 'UTF-8').' me-2"></i>' : '';

                                            $itemClasses = 'dropdown-item';
                                            if (!empty($item['class'])) {
                                                $itemClasses .= ' '.(string) $item['class'];
                                            }
                                            if (!empty($item['active'])) {
                                                $itemClasses .= ' active';
                                            }
                                            if ($iDisabled) {
                                                $itemClasses .= ' disabled';
                                            }

                                            $iExtra = [];
                                            if ($iTarget !== '') {
                                                $iExtra[] = 'target="'.htmlspecialchars($iTarget, ENT_QUOTES, 'UTF-8').'"';
                                                if ($iTarget === '_blank') {
                                                    $iExtra[] = 'rel="noopener noreferrer"';
                                                }
                                            }
                                            if ($iOnclick !== '') {
                                                $iExtra[] = 'onclick="'.htmlspecialchars($iOnclick, ENT_QUOTES, 'UTF-8').'"';
                                            }

                                            $iTag = $iHref !== '' ? 'a' : 'button';
                                            if ($iTag === 'a') {
                                                $iAttr = ' href="'.htmlspecialchars($iHref, ENT_QUOTES, 'UTF-8').'"';
                                                if ($iDisabled) {
                                                    $iExtra[] = 'aria-disabled="true"';
                                                    $iExtra[] = 'tabindex="-1"';
                                                }
                                            } else {
                                                $iAttr = ' type="button"';
                                                if ($iDisabled) {
                                                    $iExtra[] = 'disabled';
                                                }
                                            }

                                            $iExtraAttr = $iExtra === [] ? '' : ' '.implode(' ', $iExtra);
                                        ?>
                                            <li>
                                                <<?=$iTag?> class="<?=htmlspecialchars($itemClasses, ENT_QUOTES, 'UTF-8')?>"<?=$iAttr?><?=$iExtraAttr?>>
                                                    <?=$iIconHtml?><?=htmlspecialchars($iLabel, ENT_QUOTES, 'UTF-8')?>
                                                </<?=$iTag?>>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php else :
                            $href     = (string) ($action['href'] ?? '');
                            $target   = (string) ($action['target'] ?? '');
                            $onclick  = (string) ($action['onclick'] ?? '');
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
                        <?php endif; ?>
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
