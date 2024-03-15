<?php

    if (is_array($DB->database) && array_key_exists('stats', $DB->database)) {
        
        $TABLE->VISITORS_LOG = [
            "DATABASE" => 'stats',
            "visitor_id" => [],
            "session_id" => [],
            "registered_user" => [],
            "user_id" => [],
            "page_title" => [],
            "path" => [],
            "oracle_home" => [],
            "http_accept" => [],
            "http_accept_encoding" => [],
            "http_accept_language" => [],
            "http_cookie" => [],
            "http_host" => [],
            "http_user_agent" => [],
            "http_priority" => [],
            "http_dnt" => [],
            "http_upgrade_insicure_requests" => [],
            "http_sec_fetch_dest" => [],
            "http_sec_fetch_mode" => [],
            "http_sec_fetch_site" => [],
            "http_sec_fetch_user" => [],
            "document_root" => [],
            "remote_addr" => [],
            "remote_port" => [],
            "server_addr" => [],
            "server_name" => [],
            "server_admin" => [],
            "server_port" => [],
            "request_scheme" => [],
            "request_uri" => [],
            "https" => [],
            "x_spdy" => [],
            "ssl_protocol" => [],
            "ssl_cipher" => [],
            "ssl_cipher_usekeysize" => [],
            "ssl_cipher_algkeysize" => [],
            "script_filename" => [],
            "query_string" => [],
            "script_uri" => [],
            "script_url" => [],
            "script_name" => [],
            "server_protocol" => [],
            "server_software" => [],
            "request_method" => [],
            "x_lschache" => [],
            "php_self" => [],
            "request_time_float" => [],
            "request_time" => []
        ];

        // Creo tabelle per le statistiche HBH - DBD - MBM - YBY

            $table = [
                "views_recap_" => [
                    "DATABASE" => 'stats',
                    "visitors" => [
                        "sql" => [ 
                            "type" => "int" 
                        ]
                    ],
                    "sessions" => [
                        "sql" => [ 
                            "type" => "int" 
                        ]
                    ],
                    "visitors_unique" => [
                        "sql" => [ 
                            "type" => "int" 
                        ]
                    ],
                    "registered_users" => [
                        "sql" => [ 
                            "type" => "int" 
                        ]
                    ],
                    "https" => [
                        "sql" => [ 
                            "type" => "int" 
                        ]
                    ]
                ],
                "uri_VR_" => [
                    "DATABASE" => 'stats',
                    "uri" => [],
                    "visitors" => [
                        "sql" => [ 
                            "type" => "int" 
                        ]
                    ],
                    "sessions" => [
                        "sql" => [ 
                            "type" => "int" 
                        ]
                    ],
                    "visitors_unique" => [
                        "sql" => [ 
                            "type" => "int" 
                        ]
                    ],
                    "registered_users" => [
                        "sql" => [ 
                            "type" => "int" 
                        ]
                    ],
                    "https" => [
                        "sql" => [ 
                            "type" => "int" 
                        ]
                    ]
                ],
                "url_VR_" => [
                    "DATABASE" => 'stats',
                    "url" => [],
                    "visitors" => [
                        "sql" => [ 
                            "type" => "int" 
                        ]
                    ],
                    "sessions" => [
                        "sql" => [ 
                            "type" => "int" 
                        ]
                    ],
                    "visitors_unique" => [
                        "sql" => [ 
                            "type" => "int" 
                        ]
                    ],
                    "registered_users" => [
                        "sql" => [ 
                            "type" => "int" 
                        ]
                    ],
                    "https" => [
                        "sql" => [ 
                            "type" => "int" 
                        ]
                    ]
                ],
                "page_title_VR_" => [
                    "DATABASE" => 'stats',
                    "page_title" => [],
                    "visitors" => [
                        "sql" => [ 
                            "type" => "int" 
                        ]
                    ],
                    "sessions" => [
                        "sql" => [ 
                            "type" => "int" 
                        ]
                    ],
                    "visitors_unique" => [
                        "sql" => [ 
                            "type" => "int" 
                        ]
                    ],
                    "registered_users" => [
                        "sql" => [ 
                            "type" => "int" 
                        ]
                    ],
                    "https" => [
                        "sql" => [ 
                            "type" => "int" 
                        ]
                    ]
                ]
            ];

            $frequencies = ['HBH', 'DBD', 'MBM'];

            foreach ($table as $table_name => $column) {
                foreach ($frequencies as $frequency) {

                    $tb_name = strtoupper($table_name.$frequency);
                    $TABLE->$tb_name = $column;

                }
            }

        // 

    }

?>