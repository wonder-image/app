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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe" crossorigin="anonymous"></script>

<!-- Default .css - .js -->
<script src="<?=$PATH->appJs?>/global/utility.js"></script>
<script src="<?=$PATH->appJs?>/global/canvas.js"></script>

<script src="<?=$PATH->appJs?>/backend/utility.js"></script>
<script src="<?=$PATH->appJs?>/backend/ajax.js"></script>
<script src="<?=$PATH->appJs?>/backend/form/input.js"></script>
<script src="<?=$PATH->appJs?>/backend/form/file.js"></script>
<script src="<?=$PATH->appJs?>/backend/form/autonumeric.js"></script>
<script src="<?=$PATH->appJs?>/backend/alert.js"></script>
<script src="<?=$PATH->appJs?>/backend/modal.js"></script>
<script src="<?=$PATH->appJs?>/backend/bootstrap.js"></script>
<script src="<?=$PATH->appJs?>/backend/jquery.js"></script>
<script src="<?=$PATH->appJs?>/backend/pageSetUp.js"></script>

<!-- Custom .css - .js  -->
<?php include $ROOT."/custom/utility/backend/body-end.php";?>

<script>

    window.addEventListener('load', (event) => {

        <?=alert()?>
        setUpPage();

    });

</script>