<?php

    namespace Wonder\Plugin\FattureInCloud;

    use Wonder\Plugin\FattureInCloud\Api as FattureInCloudApi;
    use FattureInCloud\Model\Client as FattureInCloudClient;
    
    use FattureInCloud\Model\{ CreateClientRequest, CreateClientResponse, ModifyClientRequest, ModifyClientResponse, ListClientsResponse };

    use Exception;

    class Client extends FattureInCloudClient {

        # https://developers.fattureincloud.it/api-reference/#get-/c/-company_id-/entities/clients
        public function create(): CreateClientResponse 
        {

            $connect = new FattureInCloudApi()::connect();
            $instance = $connect->client();
            $request = (new CreateClientRequest)->setData($this);

            try {

                return $instance->createClient( $connect::$companyId, $request );

            } catch (Exception $e) {

                echo 'Exception when calling Client->create: ', $e->getMessage(), PHP_EOL;

            }
            
        }

        public function get($clientId): ListClientsResponse
        {

            $connect = new FattureInCloudApi()::connect();
            $instance = $connect->client();

            try {

                return $instance->listClients( $connect::$companyId, 'id', $clientId );

            } catch (Exception $e) {

                echo 'Exception when calling Client->get: ', $e->getMessage(), PHP_EOL;

            }

        }

        # https://developers.fattureincloud.it/api-reference/#put-/c/-company_id-/entities/clients/-client_id-
        public function update($clientId): ModifyClientResponse
        {

            $connect = new FattureInCloudApi()::connect();
            $instance = $connect->client();
            $request = (new ModifyClientRequest)->setData($this);
            
            try {

                return $instance->modifyClient( $connect::$companyId, $clientId, $request );

            } catch (Exception $e) {

                echo 'Exception when calling Client->update: ', $e->getMessage(), PHP_EOL;

            }

        }

    }