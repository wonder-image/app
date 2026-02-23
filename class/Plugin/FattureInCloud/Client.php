<?php

    namespace Wonder\Plugin\FattureInCloud;

    use Wonder\Plugin\FattureInCloud\Api as FattureInCloudApi;
    use FattureInCloud\Model\Client as FattureInCloudClient;
    
    use FattureInCloud\Model\{ CreateClientRequest, CreateClientResponse, ModifyClientRequest, ModifyClientResponse, ListClientsResponse };

    class Client extends FattureInCloudClient {
        
        use HandlesApiErrors;

        # https://developers.fattureincloud.it/api-reference/#get-/c/-company_id-/entities/clients
        public function create(): CreateClientResponse 
        {

            return $this->guard('client.create', function () {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->client();
                $request = (new CreateClientRequest())->setData($this);

                return $instance->createClient( $connect::$companyId, $request );
            });

        }

        public function get($clientId): ListClientsResponse
        {

            return $this->guard('client.get', function () use ($clientId) {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->client();

                return $instance->listClients( $connect::$companyId, 'id', $clientId );
            });

        }

        # https://developers.fattureincloud.it/api-reference/#put-/c/-company_id-/entities/clients/-client_id-
        public function update($clientId): ModifyClientResponse
        {

            return $this->guard('client.update', function () use ($clientId) {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->client();
                $request = (new ModifyClientRequest())->setData($this);

                return $instance->modifyClient( $connect::$companyId, $clientId, $request );
            });

        }

    }
