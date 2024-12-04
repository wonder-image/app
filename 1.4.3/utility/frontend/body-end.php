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

<!-- Fundamental .js -->
<script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/frontend/body-end.js"></script>

<!-- Custom .css - .js  -->
<?php include $ROOT.'/custom/utility/frontend/body-end.php'; ?>

<script> setAos(); window.addEventListener('load', (event) => { setUpPage(); <?=alert()?> }); </script>