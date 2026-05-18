<?php

\Wonder\App\Theme::set('bootstrap');

Wonder\App\Dependencies::jquery()
    ::jqueryPlugin()
    ::moment()
    ::bootstrap()
    ::bootstrapIcons()
    ::bootstrapDatepicker()
    ::jszip()
    ::datatables()
    ::quilljs()
    ::editorjs()
    ::filepond()
    ::autonumeric()
    ::jstree()
    ::select2()
    ::wiBackend();
