<?php

    function alert() {

        global $ALERT;

        if (empty($ALERT) && !empty($_GET['alert'])) {
            $ALERT = $_GET['alert'];
        }

        if (!empty($ALERT)) {
            echo "alertToast($ALERT);";
        }

    }

    function alertTheme($code, $type = null, $title = null, $text = null) {

        $id = code(10, 'letters', 'alert_');
        
        if ($code != 'custom') {

            $type = __t("notifications.{$code}.type");
            $title = __t("notifications.{$code}.title");
            $text = __t("notifications.{$code}.text");

        }

        if ($type == "danger") {
            $icon = "<i class='bi bi-exclamation-triangle me-2'></i>";
        } elseif ($type == "success") {
            $icon = "<i class='bi bi-check2-circle me-2'></i>";
        }

        return "<div id='$id' class='toast border-$type overflow-hidden' role='alert' aria-live='assertive' aria-atomic='true'> <div class='toast-header text-bg-$type border-bottom border-$type'> $icon <strong class='me-auto'>$title</strong> <button type='button' class='btn-close' data-bs-dismiss='toast' aria-label='Close'></button> </div> <div class='toast-body bg-light'> $text </div> </div>";

    }