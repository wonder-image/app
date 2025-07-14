<?php
// install-wonder.php - da eseguire post update composer
echo "> Installazione libreria npm wonder-image...\n";

// Controlla se esiste package.json, altrimenti lo crea
if (!file_exists('./package.json')) {

    $packageJson = [
        "private" => true,
        "dependencies" => new stdClass()
    ];

    file_put_contents('./package.json', json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    
    echo "✓ Creato package.json\n";

} else {

    echo "✓ package.json già presente\n";

}

// Esegui npm install wonder-image (specifica versione se vuoi)
passthru('npm update && npm install wonder-image', $retval);

if ($retval !== 0) {

    echo "❌ Errore durante npm install\n";
    echo "▶ Assicurati di avere Node.js e npm installati correttamente.\n";

    exit($retval);

}

echo "✓ wonder-image installato\n";
