<?php

    namespace Wonder\Plugin\Google\Business;

    use Wonder\App\Api;

    class Info {

        static $endpoint = '/service/google/business/';
        static $placeId;

        /**
         * 
         * @param string $placeId = https://developers.google.com/maps/documentation/geocoding/overview?hl=it#how-the-geocoding-api-works
         * 
         */
        function __construct( $placeId ) {

            self::$placeId = $placeId;

        }

        public function Info():array {

            $request = Api::Call(self::$endpoint, [ "place_id" => self::$placeId ]);

            $json = json_decode($request, true);

            $response = $json['response'];

            return $response;

        }

        /**
         * 
         * @param string $sort = 'newest' || 'most_relevant
         * @param bool $translate
         * @return array
         * 
         */
        public function Reviews( string $sort = 'newest', bool $translate = false ):array {

            $request = Api::Call(self::$endpoint."reviews/", [
                "place_id" => self::$placeId,
                "sort" => $sort,
                "translate" => $translate
            ]);

            $json = json_decode($request, true);

            $response = $json['response'];

            return $response;

        }
        
    }
