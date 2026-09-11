<?=\Wonder\View\View::component('overlay.loading-spinner')?>
<?=\Wonder\View\View::component('overlay.popup')?>

<?=Wonder\App\Dependencies::Body()?>

<script>
    document.addEventListener('DOMContentLoaded', function () { setAos(); }, { once: true });
    window.addEventListener('load', function () { setUpPage(); <?=alert()?> }, { once: true });
</script>
