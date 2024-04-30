<section id="loading-spinner" class="p-f full-page bg-dark-0 d-none no-interaction" style="z-index: 1100">
    <div class="bg bg-dark-10 blur-2"></div>
    <div class="p-a center">
        <div class="title a-c">
            <span class="spinner-border"></span> 
        </div>
        <div class="a-c text mt-4">
            Loading...
        </div>
    </div>
</section>

<!-- Video.js -->
<script src="https://vjs.zencdn.net/7.18.1/video.min.js"></script>

<!-- Animazioni AOS -->
<script id="aos-js" src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<!-- Disabilita AOS mobile -->
<script src="<?=$PATH->appJs?>/frontend/aos.js"></script>

<!-- Default .css - .js -->
<script src="<?=$PATH->appJs?>/frontend/header.js"></script>
<script src="<?=$PATH->appJs?>/frontend/alert.js"></script>
<script src="<?=$PATH->appJs?>/frontend/dropdown.js"></script>
<script src="<?=$PATH->appJs?>/frontend/modal.js"></script>
<script src="<?=$PATH->appJs?>/frontend/form/select.js"></script>
<script src="<?=$PATH->appJs?>/frontend/form/send.js"></script>
<script src="<?=$PATH->appJs?>/frontend/pageSetUp.js"></script>

<!-- Custom .css - .js  -->
<?php include $ROOT.'/custom/utility/frontend/body-end.php'; ?>

<script>

    window.addEventListener('load', (event) => {

        <?=alert()?>

        <?php

            $scroll = isset($_GET['scroll']) ? $_GET['scroll'] : '';
            if (!empty($scroll)) {
                echo "scrolla('#$scroll');";
            }
            
        ?>

        setUpPage();

    });

</script>