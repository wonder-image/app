<?php
    
    $ERROR = $_GET['errCode'];
    http_response_code($ERROR);

?>
<!DOCTYPE html>
<html lang="it">
<head>

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Errore <?=$ERROR?></title>

    <link rel="stylesheet" href="<?=$PATH->app?>/assets/css/error.css">

</head>
<body>

    <div id="contenitore">
        <div class="titolo">
            ERRORE <?=$ERROR?>
        </div>
        <div class="testo">
            Oooppsss un errore, stiamo investigando... <br>
            Continua la tua navigazione
        </div>
        <a href ="<?=$PATH->site?>">
            <div class="btn">
                TORNA AL SITO
            </div>
        </a>
        <div id="background"></div>
    </div>

</body>
</html>