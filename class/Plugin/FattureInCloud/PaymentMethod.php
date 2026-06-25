<?php

    namespace Wonder\Plugin\FattureInCloud;

    use Wonder\Plugin\FattureInCloud\Api as FattureInCloudApi;
    use FattureInCloud\Model\PaymentMethod as FattureInCloudPaymentMethod;
    use FattureInCloud\Model\{
        CreatePaymentMethodRequest,
        CreatePaymentMethodResponse,
        ModifyPaymentMethodRequest,
        ModifyPaymentMethodResponse,
        GetPaymentMethodResponse,
        ListPaymentMethodsResponse
    };

    class PaymentMethod extends FattureInCloudPaymentMethod
    {

        use HandlesApiErrors;

        public function create(): CreatePaymentMethodResponse
        {

            return $this->guard('payment_method.create', function () {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->settings();
                $request = (new CreatePaymentMethodRequest())->setData($this);

                return $instance->createPaymentMethod($connect::$companyId, $request);
            });

        }

        public function get(int $paymentMethodId, ?string $fields = null, ?string $fieldset = null): GetPaymentMethodResponse
        {

            return $this->guard('payment_method.get', function () use ($paymentMethodId, $fields, $fieldset) {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->settings();

                return $instance->getPaymentMethod($connect::$companyId, $paymentMethodId, $fields, $fieldset);
            });

        }

        public function list(?string $fields = null, ?string $fieldset = null, ?string $sort = null): ListPaymentMethodsResponse
        {

            return $this->guard('payment_method.list', function () use ($fields, $fieldset, $sort) {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->info();

                return $instance->listPaymentMethods($connect::$companyId, $fields, $fieldset, $sort);
            });

        }

        public function update(int $paymentMethodId): ModifyPaymentMethodResponse
        {

            return $this->guard('payment_method.update', function () use ($paymentMethodId) {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->settings();
                $request = (new ModifyPaymentMethodRequest())->setData($this);

                return $instance->modifyPaymentMethod($connect::$companyId, $paymentMethodId, $request);
            });

        }

        public function delete(int $paymentMethodId): void
        {

            $this->guard('payment_method.delete', function () use ($paymentMethodId) {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->settings();

                $instance->deletePaymentMethod($connect::$companyId, $paymentMethodId);
            });

        }

    }
