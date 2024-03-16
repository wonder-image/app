<?php

    function curl($url, $action = null, $values = null, $username = null, $password = null) {

        $action = ($action == null) ? null : strtoupper($action);

        if ($action == 'GET' && is_array($values)) {
            
            $url .= "?".http_build_query($values);

        }

        # Open cURL
            $ch = curl_init();

        # Connection
            curl_setopt($ch, CURLOPT_URL, $url); 

        # Get response
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        # Post
            if ($action == 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $values);
            }
        
        # Authorise
            if ($username != null && $password != null) {
                curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
            }

        # Result
            $result = curl_exec($ch);

        # Control Error
            if (curl_errno($ch)) {

                $errno = curl_errno($ch); 
                return curl_strerror($errno);
                
            } else {

                return $result;

            }

    }

    function wiApi($endpoint = null, array $values = []) {

        global $API;
        
        $url = $API->endpoint.$endpoint;

        # Codifico i valori in JSON
            $values = json_encode($values);

        # Open cURL
            $ch = curl_init();

        # Connection
            curl_setopt($ch, CURLOPT_URL, $url); 
        
        # Header
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: '.strlen($values),
                'Authorization: Bearer '.$API->key
            ]);

        # Get response
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        # Post
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $values);

        # Result
            $result = curl_exec($ch);

        # Control Error
            if (curl_errno($ch)) {

                $errno = curl_errno($ch); 
                return curl_strerror($errno);
                
            } else {

                return $result;

            }

    }

?>