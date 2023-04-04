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
            $icon = "<i class='bi bi-exclamation-triangle me-2 text-danger'></i>";
        }elseif ($type == "success") {
            $icon = "<i class='bi bi-check2-circle me-2 text-success'></i>";
        }

        return "<div id='$id' class='toast' role='alert' aria-live='assertive' aria-atomic='true'>
                <div class='toast-header'>
                    $icon
                    <strong class='me-auto'>$title</strong>
                    <button type='button' class='btn-close' data-bs-dismiss='toast' aria-label='Close'></button>
                </div>
                <div class='toast-body'>
                    $text
                </div>
            </div>";

    }


?>