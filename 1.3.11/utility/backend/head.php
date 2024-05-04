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

    <!-- Bootstrap => .css -->
    <link href="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/bootstrap/bootstrap.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/bootstrap/bootstrap-icons.css" rel="stylesheet">

    <!-- JQuery -->
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/jquery/jquery.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/jquery/jquery-plugin.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/jquery/jquery-plugin.css" rel="stylesheet">

    <!-- DataTables -->
    <script type="text/javascript" src="https://cdn.datatables.net/2.0.0/js/dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/2.0.0/js/dataTables.bootstrap5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/3.0.0/js/dataTables.responsive.min.js"></script>

    <link rel="stylesheet" href="https://cdn.datatables.net/2.0.0/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/3.0.0/css/responsive.dataTables.min.css">

    <!-- Summernote -->
    <link href="<?=$PATH->lib?>/summernote/0.8.18/summernote-bs4.min.css" rel="stylesheet">
    <script src="<?=$PATH->lib?>/summernote/0.8.18/summernote-bs4.min.js"></script>
    <script src="<?=$PATH->lib?>/summernote/0.8.18/lang/summernote-it-IT.min.js"></script>

    <!-- Quill.js -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

    <!-- Editor.js -->
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/editorjs/editor.js"></script>

    <!-- FilePond -->
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/filepond/filepond.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/filepond/filepond.css" rel="stylesheet">

    <!-- Bootstrap Datepicker -->
    <script src="<?=$PATH->lib?>/bootstrap-datepicker/js/bootstrap-datepicker.min.js" rel="stylesheet"></script>
    <script src="<?=$PATH->lib?>/bootstrap-datepicker/locales/bootstrap-datepicker.it.min.js" rel="stylesheet"></script>
    <link rel="stylesheet" href="<?=$PATH->lib?>/bootstrap-datepicker/css/bootstrap-datepicker3.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@^3"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@^2"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@^1"></script>
    <script>
        
        moment.locale('it', {
            months : 'Gennaio_Febbraio_Marzo_Aprile_Maggio_Giugno_Luglio_Agostp_Settembre_Ottobre_Novembre_Dicembre'.split('_'),
            monthsShort : 'Gen_Feb_Mar_Apr_Mag_Giu_Lug_Ago_Sep_Ott_Nov_Dic'.split('_'),
            monthsParseExact : true,
            weekdays : 'Domenica_Lunedì_Martedì_Mercoledì_Giovedì_Venerdì_Sabato'.split('_'),
            weekdaysShort : 'Dom_Lun_Mar_Mer_Gio_Ven_Sab'.split('_'),
            weekdaysMin : 'Do_Lu_Ma_Me_Gi_Ve_Sa'.split('_'),
        });
        
    </script>

    <!-- FullCalendar -->
    <script src="<?=$PATH->lib?>/fullcalendar/6.1.10/dist/rrule/index.min.js"></script>
    <script src="<?=$PATH->lib?>/fullcalendar/6.1.10/dist/index.global.min.js"></script>
    <script src="<?=$PATH->lib?>/fullcalendar/6.1.10/dist/rrule/index.global.min.js"></script>

    <!-- FullCalendar => Bootstrap 5 translator -->
    <script src="<?=$PATH->lib?>/fullcalendar/6.1.5/packages/bootstrap5/index.global.js"></script>

    <!-- FullCalendar => Moment -->
    <script src="<?=$PATH->lib?>/fullcalendar/6.1.5/packages/moment/index.global.js"></script>
    <script src="<?=$PATH->lib?>/fullcalendar/6.1.5/packages/moment-timezone/index.global.js"></script>

    <!-- FullCalendar => IT translator -->
    <script src="<?=$PATH->lib?>/fullcalendar/6.1.5/packages/core/locales/it.global.js"></script>

    <!-- AutoNumeric.js -->
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/autonumeric/autonumeric.js"></script>

    <!-- Swiper.js -->
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/swiperjs/swiper.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/swiperjs/swiper.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/swiperjs/swiper-plugin.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/swiperjs/swiper-plugin.css" rel="stylesheet">
    
    <!-- Fancyapps -->
    <script src="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/fancyapps/fancyapps.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/wonder-image@<?=$LIB_VERSION?>/dist/lib/fancyapps/fancyapps.css" rel="stylesheet">
    
    <!-- JsTree -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.2.1/themes/default/style.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.2.1/jstree.min.js"></script>

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