<?php

    namespace Wonder\Plugin\Google\Business;

    class Info {

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

            $request = wiApi('/service/google/business/', [
                "place_id" => self::$placeId
            ]);

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

            $request = wiApi('/service/google/business/reviews/', [
                "place_id" => self::$placeId,
                "sort" => $sort,
                "translate" => $translate
            ]);

            $json = json_decode($request, true);

            $response = $json['response'];

            return $response;

        }
        
    }
