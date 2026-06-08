<?php

    $CSS_DIR = $ROOT.'/assets/'.$_ENV['ASSETS_VERSION'].'/css/set-up/';

    if (is_dir($CSS_DIR) === false) { mkdir($CSS_DIR, 0777, true); }

    // Se css-config.json esiste nel root del progetto, importa la
    // configurazione nel DB prima di rigenerare i CSS. Questo garantisce
    // che ogni ambiente (locale, staging, produzione) generi gli stessi
    // CSS partendo dallo stesso source of truth committato in git.
    \Wonder\App\Support\CssConfigSync::importIfExists($ROOT);

    cssRoot();
    cssColor();
