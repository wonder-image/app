<?php

    namespace Wonder\Plugin\FattureInCloud;

    use Wonder\Plugin\FattureInCloud\Api as FattureInCloudApi;
    use FattureInCloud\Model\VatType as FattureInCloudVatType;
    
    use FattureInCloud\Model\{ CreateVatTypeRequest, CreateVatTypeResponse, ModifyVatTypeRequest, ModifyVatTypeResponse, GetVatTypeResponse, ListVatTypesResponse };

    use Exception;

    class Vat extends FattureInCloudVatType {

        public function create(): CreateVatTypeResponse 
        {

            $connect = new FattureInCloudApi()::connect();
            $instance = $connect->settings();
            $request = (new CreateVatTypeRequest)->setData($this);

            try {

                return $instance->createVatType( $connect::$companyId, $request );

            } catch (Exception $e) {

                echo 'Exception when calling Vat->create: ', $e->getMessage(), PHP_EOL;

            }
            
        }

        public function get($vatTypeId): GetVatTypeResponse
        {

            $connect = new FattureInCloudApi()::connect();
            $instance = $connect->settings();

            try {

                return $instance->getVatType( $connect::$companyId, $vatTypeId );

            } catch (Exception $e) {

                echo 'Exception when calling Vat->get: ', $e->getMessage(), PHP_EOL;

            }

        }

        public function list( ?string $fieldset = null ): ListVatTypesResponse
        {

            $connect = new FattureInCloudApi()::connect();
            $instance = $connect->info();

            try {

                return $instance->listVatTypes( $connect::$companyId, $fieldset );

            } catch (Exception $e) {

                echo 'Exception when calling Vat->list: ', $e->getMessage(), PHP_EOL;

            }

        } 

        public function update($vatTypeId): ModifyVatTypeResponse
        {

            $connect = new FattureInCloudApi()::connect();
            $instance = $connect->settings();
            $request = (new ModifyVatTypeRequest)->setData($this);
            
            try {

                return $instance->modifyVatType( $connect::$companyId, $vatTypeId, $request );

            } catch (Exception $e) {

                echo 'Exception when calling Vat->update: ', $e->getMessage(), PHP_EOL;

            }

        }

        public function delete($vatTypeId): void
        {

            $connect = new FattureInCloudApi()::connect();
            $instance = $connect->settings();
            
            try {

                $instance->deleteVatType( $connect::$companyId, $vatTypeId );

            } catch (Exception $e) {

                echo 'Exception when calling Vat->delete: ', $e->getMessage(), PHP_EOL;

            }

        }

    }