<script>
    
    if (localStorage.theme != 'dark' && localStorage.theme != 'light') {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            localStorage.setItem('theme', 'dark');
        } else {
            localStorage.setItem('theme', 'light');
        }
    }

    document.querySelector("html").setAttribute("data-bs-theme", localStorage.theme);

</script>

<link rel="shortcut icon" href="<?=$DEFAULT->BeFavicon?>" type="image/x-icon">

<!-- Inizio librerie -->

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

    <!-- JQuery -->
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/jquery/jquery.js"></script>

    <!-- Moment.js -->
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/moment/moment.js"></script>

    <!-- JQuery => Plugin -->
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/jquery/jquery-plugin.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/jquery/jquery-plugin.css" rel="stylesheet">

    <!-- Bootstrap => .css -->
    <link href="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/bootstrap/bootstrap.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/bootstrap/bootstrap-icons.css" rel="stylesheet">

    <!-- Bootstrap Datepicker -->
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/bootstrap/bootstrap-datepicker.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/bootstrap/bootstrap-datepicker.css" rel="stylesheet">
    
    <!-- ! PDF Make ! => Utilizzato da DataTables per la creazione dei pdf -->
    <!-- <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/pdfmake/pdfmake.js"></script>  -->

    <!-- JSZip -->
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/jszip/jszip.js"></script> 

    <!-- DataTables -->
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/datatables/datatables.js"></script> 
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/datatables/datatables.css">

    <!-- Quill.js -->
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/quilljs/quill.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/quilljs/quill.css" rel="stylesheet">

    <!-- Editor.js -->
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/editorjs/editor.js"></script>

    <!-- FilePond -->
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/filepond/filepond.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/filepond/filepond.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/chartjs/chart.js"></script>

    <!-- FullCalendar -->
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/fullcalendar/fullcalendar.js"></script>

    <!-- AutoNumeric.js -->
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/autonumeric/autonumeric.js"></script>

    <!-- Swiper.js -->
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/swiperjs/swiper.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/swiperjs/swiper.css" rel="stylesheet">

    <!-- Fancyapps -->
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/fancyapps/fancyapps.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/fancyapps/fancyapps.css" rel="stylesheet">
    
    <!-- JsTree -->
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/jstree/jstree.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/jstree/jstree.css" rel="stylesheet">
    
<!-- Fine librerie -->

<!-- Inizio file fondamentali  -->

    <script>

        const pathSite = '<?=$PATH->site?>';
        const pathApp = '<?=$PATH->app?>';
        var NO_INTERNET_ALERT = null;

    </script>

    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/backend/head.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/backend/head.css">

<!-- Fine file fondamentali  -->

<!-- Custom => (.css, .js) -->
<?php include $ROOT.'/custom/utility/backend/head.php';?>