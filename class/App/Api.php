<?php

    namespace Wonder\App;

    use Wonder\Api\Call;
    use Wonder\App\Credentials;

    class Api {

        static function Call( string $endpoint, array $values = [] ) {
        
            $url = Credentials::api()->endpoint.$endpoint;

            $CALL = new Call($url, $values);
            
            $CALL->method('POST');
            $CALL->contentType('application/json');
            $CALL->authBearer(Credentials::api()->key);
    
            return $CALL->result();

        }

    }