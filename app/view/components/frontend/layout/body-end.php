<?=\Wonder\View\View::component('overlay.loading-spinner')?>
<?=\Wonder\View\View::component('overlay.popup')?>

<?=Wonder\App\Dependencies::Body()?>

<script> setAos(); window.addEventListener('load', (event) => { setUpPage(); <?=alert()?> }); </script>
