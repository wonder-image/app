<!-- Bootstrap => .js -->
<script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/bootstrap/bootstrap.js"></script>

<!-- Default => .js -->
<script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/backend/body-end.js"></script>

<!-- Custom => (.css, .js) -->
<?php include $ROOT."/custom/utility/backend/body-end.php";?>

<script> window.addEventListener('load', (event) => {  setUpPage(); <?=alert()?> }); </script>