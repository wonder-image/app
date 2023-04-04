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

    function alertTheme($ALERT) {

        global $ALERT_CODE;

        $id = code(10, 'letters', 'alert_');
        
        $type = $ALERT_CODE[$ALERT]['type'];
        $title = $ALERT_CODE[$ALERT]['title'];
        $text = $ALERT_CODE[$ALERT]['text'];

        if ($type == "danger") {
            $icon = "<i class='wi-alert-icon bi bi-exclamation-triangle tx-danger'></i>";
        }elseif ($type == "success") {
            $icon = "<i class='wi-alert-icon bi bi-check2-circle tx-success'></i>";
        }

        return "<div id='$id' class='wi-alert wi-show'>
                <div class='wi-alert-header'>
                    $icon
                    <b>$title</b>
                    <i class='wi-alert-close bi bi-x-lg' onclick=\"this.parentElement.parentElement.classList.remove('wi-show')\"></i>
                </div>
                <div class='wi-alert-body'>
                    $text
                </div>
            </div>";

    }


?>