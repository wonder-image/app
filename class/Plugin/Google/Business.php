<?php

    namespace Wonder\Plugin\Google;

    use Wonder\Api\Call;

    /**
     * 
     * Guida API Place [ https://developers.google.com/maps/documentation/places/web-service/details?hl=it ]
     * Per trovare il place_id [ https://developers.google.com/maps/documentation/geocoding/overview?hl=it#how-the-geocoding-api-works ] cerca la località nella mappa
     * 
     */

     class Business {

        private $API_KEY;
        private $PLACE_ID;

        function __construct($apiKey, $placeId) {

            $this->PLACE_ID = $placeId;
            $this->API_KEY = $apiKey;

        }

        public function Info() {

            $values = [
                "place_id" => $this->PLACE_ID,
                "key" => $this->API_KEY
            ];

            $CALL = new Call("https://maps.googleapis.com/maps/api/place/details/json", $values);

            $CALL->method('GET');

            return json_decode($CALL->result(), true);

        }

        /**
         * 
         * $sort = newest || most_relevant
         * 
         */

        public function Reviews($sort = 'newest', $translate = false) {

            $noTranslate = $translate ? false : true;

            $values = [
                "fields" => "reviews",
                "place_id" => $this->PLACE_ID,
                "reviews_no_translations" => $noTranslate,
                "reviews_sort" => $sort,
                "key" => $this->API_KEY
            ];

            $CALL = new Call("https://maps.googleapis.com/maps/api/place/details/json", $values);

            $CALL->method('GET');

            $result = json_decode($CALL->result(), true);

            if ($result['status'] == 'OK') {
                return $result['result']['reviews'];
            } else {
                return $result;
            }

        }

    }
