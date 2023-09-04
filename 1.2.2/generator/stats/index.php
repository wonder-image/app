<?php

    if (is_array($DB->database) && array_key_exists('stats', $DB->database)) {
            
        $mysqli = $MYSQLI_CONNECTION['stats'];

        if ($FREQUENCY == 'hourly') {
            
            $TB_VR = "views_recap_hbh";
            $TB_URI_VR = "uri_vr_hbh";
            $TB_URL_VR = "url_vr_hbh";
            $TB_PAGE_TITLE_VR = "page_title_vr_hbh";

            $FROM = date('Y-m-d H', strtotime('-1 hours')).':00:00';
            $TO = date('Y-m-d H').':00:00';

        } elseif ($FREQUENCY == 'daily') {
            
            $TB_VR = "views_recap_dbd";
            $TB_URI_VR = "uri_vr_dbd";
            $TB_URL_VR = "url_vr_dbd";
            $TB_PAGE_TITLE_VR = "page_title_vr_dbd";

            $FROM = date('Y-m-d', strtotime('-1 day')).' 00:00:00';
            $TO = date('Y-m-d', strtotime('-1 day')).' 23:59:59';

        } elseif ($FREQUENCY == 'monthly') {
            
            $TB_VR = "views_recap_mbm";
            $TB_URI_VR = "uri_vr_mbm";
            $TB_URL_VR = "url_vr_mbm";
            $TB_PAGE_TITLE_VR = "page_title_vr_mbm";

            $FROM = date('Y-m-', strtotime('-1 day')).'01 00:00:00';
            $TO = date('Y-m-d', strtotime('-1 day')).' 23:59:59';

        }

        $VISITORS_RECAP = [
            "visitors" => sqlSelect('visitors_log', "`creation` BETWEEN '$FROM' AND '$TO'")->Nrow,
            "sessions" => sqlSelect('visitors_log', "`creation` BETWEEN '$FROM' AND '$TO'", null, null, null, "DISTINCT session_id")->Nrow,
            "visitors_unique" => sqlSelect('visitors_log', "`creation` BETWEEN '$FROM' AND '$TO'", null, null, null, "DISTINCT visitor_id")->Nrow,
            "registered_users" => sqlSelect('visitors_log', "`creation` BETWEEN '$FROM' AND '$TO' AND `registered_user` = 'true'")->Nrow,
            "https" => sqlSelect('visitors_log', "`creation` BETWEEN '$FROM' AND '$TO' AND `https` = 'on'")->Nrow
        ];

        sqlInsert($TB_VR, $VISITORS_RECAP);

        foreach (sqlSelect('visitors_log', "`creation` BETWEEN '$FROM' AND '$TO'", null, null, null, "DISTINCT request_uri")->row as $key => $row) {

            $request_uri = $row['request_uri'];

            $URI_VR = [
                "uri" => $request_uri,
                "visitors" => sqlSelect('visitors_log', "`request_uri` = '$request_uri' AND `creation` BETWEEN '$FROM' AND '$TO'")->Nrow,
                "sessions" => sqlSelect('visitors_log', "`request_uri` = '$request_uri' AND `creation` BETWEEN '$FROM' AND '$TO'", null, null, null, "DISTINCT session_id")->Nrow,
                "visitors_unique" => sqlSelect('visitors_log', "`request_uri` = '$request_uri' AND `creation` BETWEEN '$FROM' AND '$TO'", null, null, null, "DISTINCT visitor_id")->Nrow,
                "registered_users" => sqlSelect('visitors_log', "`request_uri` = '$request_uri' AND `creation` BETWEEN '$FROM' AND '$TO' AND `registered_user` = 'true'")->Nrow,
                "https" => sqlSelect('visitors_log', "`request_uri` = '$request_uri' AND `creation` BETWEEN '$FROM' AND '$TO' AND `https` = 'on'")->Nrow
            ];

            sqlInsert($TB_URI_VR, $URI_VR);

        }

        foreach (sqlSelect('visitors_log', "`creation` BETWEEN '$FROM' AND '$TO'", null, null, null, "DISTINCT script_url")->row as $key => $row) {

            $script_url = $row['script_url'];

            $URL_VR = [
                "url" => $script_url,
                "visitors" => sqlSelect('visitors_log', "`script_url` = '$script_url' AND `creation` BETWEEN '$FROM' AND '$TO'")->Nrow,
                "sessions" => sqlSelect('visitors_log', "`script_url` = '$script_url' AND `creation` BETWEEN '$FROM' AND '$TO'", null, null, null, "DISTINCT session_id")->Nrow,
                "visitors_unique" => sqlSelect('visitors_log', "`script_url` = '$script_url' AND `creation` BETWEEN '$FROM' AND '$TO'", null, null, null, "DISTINCT visitor_id")->Nrow,
                "registered_users" => sqlSelect('visitors_log', "`script_url` = '$script_url' AND `creation` BETWEEN '$FROM' AND '$TO' AND `registered_user` = 'true'")->Nrow,
                "https" => sqlSelect('visitors_log', "`script_url` = '$script_url' AND `creation` BETWEEN '$FROM' AND '$TO' AND `https` = 'on'")->Nrow
            ];

            sqlInsert($TB_URL_VR, $URL_VR);

        }

        foreach (sqlSelect('visitors_log', "`creation` BETWEEN '$FROM' AND '$TO'", null, null, null, "DISTINCT page_title")->row as $key => $row) {

            $page_title = $row['page_title'];

            $PAGE_TITLE_VR = [
                "page_title" => $page_title,
                "visitors" => sqlSelect('visitors_log', "`page_title` = '$page_title' AND `creation` BETWEEN '$FROM' AND '$TO'")->Nrow,
                "sessions" => sqlSelect('visitors_log', "`page_title` = '$page_title' AND `creation` BETWEEN '$FROM' AND '$TO'", null, null, null, "DISTINCT session_id")->Nrow,
                "visitors_unique" => sqlSelect('visitors_log', "`page_title` = '$page_title' AND `creation` BETWEEN '$FROM' AND '$TO'", null, null, null, "DISTINCT visitor_id")->Nrow,
                "registered_users" => sqlSelect('visitors_log', "`page_title` = '$page_title' AND `creation` BETWEEN '$FROM' AND '$TO' AND `registered_user` = 'true'")->Nrow,
                "https" => sqlSelect('visitors_log', "`page_title` = '$page_title' AND `creation` BETWEEN '$FROM' AND '$TO' AND `https` = 'on'")->Nrow
            ];

            sqlInsert($TB_PAGE_TITLE_VR, $PAGE_TITLE_VR);

        }

    }

?>