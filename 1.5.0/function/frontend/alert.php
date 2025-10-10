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

        $icon = match ($type) {
            "danger" => "<i class='wi-alert-icon bi bi-exclamation-triangle tx-danger'></i>",
            "success" => "<i class='wi-alert-icon bi bi-check2-circle tx-success'></i>",
        };

        return "<div id='$id' class='wi-alert wi-show'>
                <div class='wi-alert-header'>
                    $icon <b>$title</b>
                    <i class='wi-alert-close bi bi-x-lg' onclick=\"this.parentElement.parentElement.classList.remove('wi-show')\"></i>
                </div>
                <div class='wi-alert-body'>
                    $text
                </div>
            </div>";
            
    }