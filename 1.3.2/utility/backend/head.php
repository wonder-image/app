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

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<!-- JQuery -->
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

<!-- DataTables -->
<script type="text/javascript" src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>

<!-- Summernote css/js -->
<link href="<?=$PATH->lib?>/summernote/0.8.18/summernote-bs4.min.css" rel="stylesheet">
<script src="<?=$PATH->lib?>/summernote/0.8.18/summernote-bs4.min.js"></script>
<script src="<?=$PATH->lib?>/summernote/0.8.18/lang/summernote-it-IT.min.js"></script>

<!-- Quill.js -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

<!-- Editor.js -->
<script src="https://cdn.jsdelivr.net/npm/@editorjs/editorjs@2.28.2/dist/editorjs.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/header@2.8.1/dist/header.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/image@2.9.0/dist/image.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/nested-list@1.4.2/dist/nested-list.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/quote@2.6.0/dist/quote.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/marker@1.4.0/dist/marker.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/delimiter@1.4.0/dist/delimiter.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/code@2.9.0/dist/code.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/embed@2.7.0/dist/embed.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/inline-code@1.5.0/dist/inline-code.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/table@2.3.0/dist/table.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/editorjs-text-alignment-blocktune@1.0.3/dist/bundle.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/attaches@1.3.0/dist/bundle.js"></script>
<script src="<?=$PATH->lib?>/editorjs/hyperlink.js"></script>
<script src="<?=$PATH->lib?>/editorjs/gallery.js"></script>

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
<script src="<?=$PATH->lib?>/fullcalendar/6.1.5/packages/core/index.global.js"></script>
<script src="<?=$PATH->lib?>/fullcalendar/6.1.5/packages/daygrid/index.global.js"></script>
<script src="<?=$PATH->lib?>/fullcalendar/6.1.5/packages/timegrid/index.global.js"></script>
<script src="<?=$PATH->lib?>/fullcalendar/6.1.5/packages/list/index.global.js"></script>
<script src="<?=$PATH->lib?>/fullcalendar/6.1.5/packages/web-component/index.global.js"></script>
<script src="<?=$PATH->lib?>/fullcalendar/6.1.5/packages/interaction/index.global.js"></script>

<!-- FullCalendar => Bootstrap 5 translator -->
<script src="<?=$PATH->lib?>/fullcalendar/6.1.5/packages/bootstrap5/index.global.js"></script>

<!-- FullCalendar => Moment -->
<script src="<?=$PATH->lib?>/fullcalendar/6.1.5/packages/moment/index.global.js"></script>
<script src="<?=$PATH->lib?>/fullcalendar/6.1.5/packages/moment-timezone/index.global.js"></script>

<!-- FullCalendar => IT translator -->
<script src="<?=$PATH->lib?>/fullcalendar/6.1.5/packages/core/locales/it.global.js"></script>

<!-- Autonumeric -->
<script src="https://cdn.jsdelivr.net/npm/autonumeric@4.10.0/dist/autoNumeric.min.js"></script>

<!-- Swiper.js -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<link rel="stylesheet" href="<?=$PATH->lib?>/swiper/effect-shutters.min.css" />
<link rel="stylesheet" href="<?=$PATH->lib?>/swiper/effect-slicer.min.css" />
<link rel="stylesheet" href="<?=$PATH->lib?>/swiper/swiper-gl.min.css" />

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script src="<?=$PATH->lib?>/swiper/effect-shutters.min.js"></script>
<script src="<?=$PATH->lib?>/swiper/effect-slicer.min.js"></script>
<script src="<?=$PATH->lib?>/swiper/swiper-gl.min.js"></script>

<!-- Fancybox -->
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css">

<script>

    const pathSite = '<?=$PATH->site?>';
    const pathApp = '<?=$PATH->app?>';

</script>

<!-- Default .css - .js -->
<script src="<?=$PATH->appJs?>/global/form/autonumeric.js"></script>
<script src="<?=$PATH->appJs?>/global/utility.js"></script>
<script src="<?=$PATH->appJs?>/global/canvas.js"></script>

<script src="<?=$PATH->appJs?>/backend/utility.js"></script>
<script src="<?=$PATH->appJs?>/backend/ajax.js"></script>
<script src="<?=$PATH->appJs?>/backend/form/input.js"></script>
<script src="<?=$PATH->appJs?>/backend/form/file.js"></script>
<script src="<?=$PATH->appJs?>/backend/form/set.js"></script>
<script src="<?=$PATH->appJs?>/backend/alert.js"></script>
<script src="<?=$PATH->appJs?>/backend/modal.js"></script>
<script src="<?=$PATH->appJs?>/backend/bootstrap.js"></script>
<script src="<?=$PATH->appJs?>/backend/jquery.js"></script>
<script src="<?=$PATH->appJs?>/backend/form/editor.js"></script>

<script>

    var NO_INTERNET_ALERT = null;
    
    $.ajax({
        type: "POST",
        url: pathApp+'/api/alert.php',
        data: { 
            post: 'true',
            backend: 'true',
            alert: 801
        }, 
        success: function (data) {
            NO_INTERNET_ALERT = data;
        }
    });

</script>

<!-- Input Row -->
<script src="<?=$PATH->appJs?>/backend/form/inputRow.js"></script>

<link rel="stylesheet" href="<?=$PATH->appCss?>/backend/header.css">
<link rel="stylesheet" href="<?=$PATH->appCss?>/backend/input.css">
<link rel="stylesheet" href="<?=$PATH->appCss?>/backend/list.css">
<link rel="stylesheet" href="<?=$PATH->appCss?>/backend/order.css">

<!-- Custom .css - .js  -->
<?php include $ROOT.'/custom/utility/backend/head.php';?>