<?php

    namespace Wonder\Plugin\FattureInCloud;

    use Wonder\Plugin\FattureInCloud\Api as FattureInCloudApi;
    use FattureInCloud\Model\PaymentAccount as FattureInCloudPaymentAccount;
    use FattureInCloud\Model\{
        CreatePaymentAccountRequest,
        CreatePaymentAccountResponse,
        ModifyPaymentAccountRequest,
        ModifyPaymentAccountResponse,
        GetPaymentAccountResponse,
        ListPaymentAccountsResponse
    };

    class PaymentAccount extends FattureInCloudPaymentAccount
    {

        use HandlesApiErrors;

        public function create(): CreatePaymentAccountResponse
        {

            return $this->guard('payment_account.create', function () {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->settings();
                $request = (new CreatePaymentAccountRequest())->setData($this);

                return $instance->createPaymentAccount($connect::$companyId, $request);
            });

        }

        public function get(int $paymentAccountId, ?string $fields = null, ?string $fieldset = null): GetPaymentAccountResponse
        {

            return $this->guard('payment_account.get', function () use ($paymentAccountId, $fields, $fieldset) {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->settings();

                return $instance->getPaymentAccount($connect::$companyId, $paymentAccountId, $fields, $fieldset);
            });

        }

        public function list(?string $fields = null, ?string $fieldset = null, ?string $sort = null): ListPaymentAccountsResponse
        {

            return $this->guard('payment_account.list', function () use ($fields, $fieldset, $sort) {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->info();

                return $instance->listPaymentAccounts($connect::$companyId, $fields, $fieldset, $sort);
            });

        }

        public function update(int $paymentAccountId): ModifyPaymentAccountResponse
        {

            return $this->guard('payment_account.update', function () use ($paymentAccountId) {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->settings();
                $request = (new ModifyPaymentAccountRequest())->setData($this);

                return $instance->modifyPaymentAccount($connect::$companyId, $paymentAccountId, $request);
            });

        }

        public function delete(int $paymentAccountId): void
        {

            $this->guard('payment_account.delete', function () use ($paymentAccountId) {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->settings();

                $instance->deletePaymentAccount($connect::$companyId, $paymentAccountId);
            });

        }

    }
