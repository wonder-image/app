<?=\Wonder\View\View::component('overlay.loading-spinner')?>

<?=Wonder\App\Dependencies::Body()?>

<!-- Custom .css - .js  -->
<?php include $ROOT.'/custom/utility/frontend/body-end.php'; ?>

<script> setAos(); window.addEventListener('load', (event) => { setUpPage(); <?=alert()?> }); </script>
