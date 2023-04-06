<?php
    
    if (sqlTableExists('seo')) { $SEO = infoSeo(); }

    include $ROOT.'/custom/utility/frontend/set-up.php';

?>