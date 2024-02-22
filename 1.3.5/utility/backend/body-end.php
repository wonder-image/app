<!-- Bootstrap => .js -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

<!-- FilePond => .js -->
<script src="<?=$PATH->lib?>/filepond/plugin/image-exif-orientation/index.min.js"></script>
<script src="<?=$PATH->lib?>/filepond/plugin/image-edit/index.min.js"></script>
<script src="<?=$PATH->lib?>/filepond/plugin/file-metadata/index.min.js"></script>
<script src="<?=$PATH->lib?>/filepond/plugin/file-validate-type/index.min.js"></script>
<script src="<?=$PATH->lib?>/filepond/plugin/file-validate-size/index.min.js"></script>
<script src="<?=$PATH->lib?>/filepond/plugin/image-preview/index.min.js"></script>
<script src="<?=$PATH->lib?>/filepond/plugin/image-crop/index.min.js"></script>
<script src="<?=$PATH->lib?>/filepond/plugin/image-resize/index.min.js"></script>
<script src="<?=$PATH->lib?>/filepond/plugin/image-transform/index.min.js"></script>
<script src="<?=$PATH->lib?>/filepond/plugin/get-file/index.min.js"></script>
<script src="<?=$PATH->lib?>/filepond/index.min.js"></script>

<!-- Default => .js -->
<script src="<?=$PATH->appJs?>/backend/form/filepond.js"></script>
<script src="<?=$PATH->appJs?>/backend/list.js"></script>
<script src="<?=$PATH->appJs?>/backend/pageSetUp.js"></script>

<!-- Custom => (.css, .js) -->
<?php include $ROOT."/custom/utility/backend/body-end.php";?>

<script>

    window.addEventListener('load', (event) => {

        <?=alert()?>
        setUpPage();

    });

</script>