<?=\Wonder\View\View::component('overlay.loading-spinner')?>

<?=Wonder\App\Dependencies::Body()?>

<script> setAos(); window.addEventListener('load', (event) => { setUpPage(); <?=alert()?> }); </script>
