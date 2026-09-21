<?php \Wonder\View\View::layout('backend.main'); ?>
<?php
$navigation = \Wonder\Backend\Support\BackendNavigation::all();
$renderNavigationItems = static function (array $items, int $depth = 0) use (&$renderNavigationItems, $USER, $PATH): string {
    $markup = '';

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $authority = (array) ($item['authority'] ?? []);

        if ($authority && count(array_intersect($authority, (array) $USER->authority)) < 1) {
            continue;
        }

        $children = (array) ($item['subnavs'] ?? []);
        $title = (string) ($item['title'] ?? 'ND');

        if ($children !== []) {
            $childrenMarkup = $renderNavigationItems($children, $depth + 1);

            if ($childrenMarkup === '') {
                continue;
            }

            $padding = $depth > 0 ? 'ps-4' : 'ps-3';
            $markup .= '<div class="list-group-item border-0 bg-transparent be-nav-heading text-uppercase fw-semibold pt-2 pb-1 '.e($padding).'">'.e($title).'</div>';
            $markup .= $childrenMarkup;
            continue;
        }

        $folder = trim((string) ($item['folder'] ?? ''), '/');
        $file = trim((string) ($item['file'] ?? ''), '/');
        $urlParser = new \Wonder\Http\UrlParser($file);
        $href = $urlParser->isAbsolute()
            ? $file
            : rtrim((string) $PATH->backend, '/').'/'.trim($folder.'/'.$file, '/');
        $padding = $depth > 0 ? 'ps-4' : '';
        $markup .= '<a class="list-group-item list-group-item-action '.e($padding).'" href="'.e($href).'">'
            .e($title).'<i class="bi bi-chevron-right float-end"></i></a>';
    }

    return $markup;
};
?>
<div class="row g-3">

    <?php // Riquadri dichiarati dai moduli abilitati (backend.home_widgets). ?>
    <?=\Wonder\Backend\Support\HomeWidgets::renderAll((array) ($USER->authority ?? []))?>

    <wi-card class="col-3">

        <?php foreach ($navigation as $navs) {
            $titleNav = (string) ($navs['title'] ?? 'ND');
            $authNav = (array) ($navs['authority'] ?? []);

            if ($titleNav === 'Home' || ($authNav && count(array_intersect($authNav, (array) $USER->authority)) < 1)) {
                continue;
            }

            $iconNav = (string) ($navs['icon'] ?? 'bi-bug');
            $subnavs = (array) ($navs['subnavs'] ?? []);
            $folderNav = trim((string) ($navs['folder'] ?? ''), '/');
            $fileNav = trim((string) ($navs['file'] ?? ''), '/');
            $urlParser = new \Wonder\Http\UrlParser($fileNav);
            $hrefNav = $urlParser->isAbsolute()
                ? $fileNav
                : rtrim((string) $PATH->backend, '/').'/'.trim($folderNav.'/'.$fileNav, '/');
            $subnavsMarkup = $renderNavigationItems($subnavs);

            if ($subnavs !== [] && $subnavsMarkup === '') {
                continue;
            }
        ?>
            <div class="list-group be-nav-list ps-2">
                <?php if ($subnavs !== []) { ?>
                    <div class="list-group-item list-group-item-dark"><i class="bi <?=e($iconNav)?>"></i> <?=e($titleNav)?></div>
                    <?=$subnavsMarkup?>
                <?php } else { ?>
                    <a href="<?=e($hrefNav)?>" class="list-group-item list-group-item-dark list-group-item-action">
                        <i class="bi <?=e($iconNav)?>"></i> <?=e($titleNav)?> <i class="bi bi-chevron-right float-end"></i>
                    </a>
                <?php } ?>
            </div>
        <?php } ?>
    </wi-card>

    <div class="col-9">
        <div class="row g-3">

            <?php if (in_array('admin', $USER->authority, true)) { ?>
            <wi-card class="col-12">
                <div class="col-12">
                    <h6>Update applicativo</h6>
                </div>
                <?php if (is_array($UPDATE_RESULT)) { ?>
                <div class="col-12">
                    <?php
                        $icon = !empty($UPDATE_RESULT['success']) ? 'bi-check2 text-success' : 'bi-x-lg text-danger';
                        $message = e($UPDATE_RESULT['message'] ?? $UPDATE_RESULT['response'] ?? 'Operazione conclusa.');
                    ?>
                    <p class="mb-2"><i class="bi <?=$icon?> me-1"></i><?=$message?></p>
                    <?php if (!empty($UPDATE_RESULT['release_id'])) { ?>
                    <p class="mb-1"><b>Release:</b> <?=e($UPDATE_RESULT['release_id'])?></p>
                    <?php } ?>
                    <?php if (isset($UPDATE_RESULT['stats']) && is_array($UPDATE_RESULT['stats'])) { ?>
                    <p class="mb-0">
                        <b>Tabelle:</b> <?= (int) ($UPDATE_RESULT['stats']['tables'] ?? 0) ?>
                        |
                        <b>Row:</b> <?= (int) ($UPDATE_RESULT['stats']['rows'] ?? 0) ?>
                        |
                        <b>Page:</b> <?= (int) ($UPDATE_RESULT['stats']['pages'] ?? 0) ?>
                    </p>
                    <?php } ?>
                </div>
                <?php } ?>
                <form method="post" class="col-12 mt-2">
                    <div class="row g-3 align-items-end">
                        <div class="col-8">
                            <label for="release_id" class="form-label">Release ID</label>
                            <input type="text" class="form-control" id="release_id" name="release_id" placeholder="es. commit SHA o tag deploy">
                        </div>
                        <div class="col-4">
                            <button type="submit" name="run_app_update" value="true" class="btn btn-dark w-100">Esegui update</button>
                        </div>
                    </div>
                </form>
            </wi-card>
            <?php } ?>

            <wi-card class="col-12">
                <div class="col-12">
                    <h6>Contatti</h6>
                </div>
                <div class="col-12">
                    Cellulare: <a href="tel:393911220336" target="_blank" rel="noopener noreferrer">391 1220336</a> <br>
                    Whatsapp:  <a href="https://wa.me/3911220336?text=Ciao%20Andrea%20ho%20bisogno%20di%20un%20aiuto" target="_blank" rel="noopener noreferrer">391 1220336</a> <br>
                    Email:     <a href="mailto:marinoni@wonderimage.it" target="_blank" rel="noopener noreferrer">marinoni@wonderimage.it</a>
                </div>
            </wi-card>

        </div>
    </div>

</div>
<?php \Wonder\View\View::end(); ?>
