<?php

    namespace Wonder\Plugin\FattureInCloud;

    use Wonder\Plugin\FattureInCloud\Api as FattureInCloudApi;
    use FattureInCloud\Model\VatType as FattureInCloudVatType;
    
    use FattureInCloud\Model\{ CreateVatTypeRequest, CreateVatTypeResponse, ModifyVatTypeRequest, ModifyVatTypeResponse, GetVatTypeResponse, ListVatTypesResponse };

    class Vat extends FattureInCloudVatType {
        
        use HandlesApiErrors;

        public function create(): CreateVatTypeResponse 
        {

            return $this->guard('vat.create', function () {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->settings();
                $request = (new CreateVatTypeRequest())->setData($this);

                return $instance->createVatType( $connect::$companyId, $request );
            });

        }

        public function get($vatTypeId): GetVatTypeResponse
        {

            return $this->guard('vat.get', function () use ($vatTypeId) {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->settings();

                return $instance->getVatType( $connect::$companyId, $vatTypeId );
            });

        }

        public function list( ?string $fieldset = null ): ListVatTypesResponse
        {

            return $this->guard('vat.list', function () use ($fieldset) {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->info();

                return $instance->listVatTypes( $connect::$companyId, $fieldset );
            });

        } 

        public function update($vatTypeId): ModifyVatTypeResponse
        {

            return $this->guard('vat.update', function () use ($vatTypeId) {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->settings();
                $request = (new ModifyVatTypeRequest())->setData($this);

                return $instance->modifyVatType( $connect::$companyId, $vatTypeId, $request );
            });

        }

        public function delete($vatTypeId): void
        {

            $this->guard('vat.delete', function () use ($vatTypeId) {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->settings();
                $instance->deleteVatType( $connect::$companyId, $vatTypeId );
            });

        }

    }
