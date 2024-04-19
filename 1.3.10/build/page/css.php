<?php

    $CSS_DIR = $ROOT.'/assets/'.$_ENV['ASSETS_VERSION'].'/css/set-up/';

    if (is_dir($CSS_DIR) === false) { mkdir($CSS_DIR); }

    # Aggiorno il file root.css
    cssRoot();

    # Aggiorno il file color.css
    cssColor();