<!-- Progress bar -->
<div id="loading-spinner" class="position-fixed container-fluid bg-dark bg-opacity-50 h-100 d-none" style="z-index: 1100">
    <div class="position-absolute top-50 start-50 translate-middle text-center">
        <div class="spinner-border" role="status">
        </div> 
        <br>
        <br>
        <span>Caricamento</span>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js" integrity="sha384-w76AqPfDkMBDXo30jS1Sgez6pr3x5MlQ1ZAGC+nuZB+EYdgRZgiwxhTBTkF7CXvN" crossorigin="anonymous"></script>

<!-- Default .css - .js -->
<script src="<?=$PATH->app?>/assets/js/backend/utility.js"></script>
<script src="<?=$PATH->app?>/assets/js/backend/ajax.js"></script>
<script src="<?=$PATH->app?>/assets/js/backend/input.js"></script>
<script src="<?=$PATH->app?>/assets/js/backend/alert.js"></script>
<script src="<?=$PATH->app?>/assets/js/backend/modal.js"></script>
<script src="<?=$PATH->app?>/assets/js/backend/bootstrap.js"></script>
<script src="<?=$PATH->app?>/assets/js/backend/pageSetUp.js"></script>

<!-- Custom .css - .js  -->
<?php include $ROOT.'/custom/utility/backend/include-bottom.php';?>

<script>

    window.addEventListener('load', (event) => {

        <?=alert()?>
        setUpPage();

    });

</script>