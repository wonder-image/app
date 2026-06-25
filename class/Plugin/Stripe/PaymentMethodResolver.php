<?php

    namespace Wonder\Plugin\Stripe;

    use Stripe\Exception\ApiErrorException;

    class PaymentMethodResolver extends Stripe {

        private array $cache = [];

        private function normalize(mixed $value): string
        {

            return strtolower(trim((string)$value));

        }

        private function remember(string $invoiceId, string $paymentMethodType): string
        {

            if ($invoiceId !== '') {
                $this->cache[$invoiceId] = $paymentMethodType;
            }

            return $paymentMethodType;

        }

        private function logError(string $context, \Throwable $e): void
        {

            if (function_exists('logStripeError') && $e instanceof ApiErrorException) {
                logStripeError($context, $e);
                return;
            }

            if (function_exists('__log')) {
                __log($e, 'stripe', $context, 'ERROR', 'error/stripe');
            }

        }

        private function typeFromPaymentMethodObject(object $paymentMethod): string
        {

            return $this->normalize($paymentMethod->type ?? '');

        }

        private function typeFromPaymentMethod(mixed $paymentMethod): string
        {

            if (is_object($paymentMethod)) {
                return $this->typeFromPaymentMethodObject($paymentMethod);
            }

            $paymentMethodId = trim((string)$paymentMethod);
            if ($paymentMethodId === '') {
                return '';
            }

            try {

                $paymentMethodObject = $this->paymentMethods->retrieve($paymentMethodId, [], $this->opts);
                return $this->typeFromPaymentMethodObject($paymentMethodObject);

            } catch (ApiErrorException $e) {

                $this->logError('payment_method.detect.payment_method', $e);

            } catch (\Throwable $e) {

                $this->logError('payment_method.detect.payment_method', $e);

            }

            return '';

        }

        private function typeFromChargeObject(object $charge): string
        {

            return $this->normalize($charge->payment_method_details->type ?? '');

        }

        private function typeFromCharge(mixed $charge): string
        {

            if (is_object($charge)) {
                $type = $this->typeFromChargeObject($charge);
                if ($type !== '') {
                    return $type;
                }

                $charge = (string)($charge->id ?? '');
            }

            $chargeId = trim((string)$charge);
            if ($chargeId === '') {
                return '';
            }

            try {

                $chargeObject = $this->charges->retrieve($chargeId, [], $this->opts);
                return $this->typeFromChargeObject($chargeObject);

            } catch (ApiErrorException $e) {

                $this->logError('payment_method.detect.charge', $e);

            } catch (\Throwable $e) {

                $this->logError('payment_method.detect.charge', $e);

            }

            return '';

        }

        private function typeFromPaymentIntentObject(object $paymentIntent): string
        {

            $type = $this->typeFromPaymentMethod($paymentIntent->payment_method ?? null);
            if ($type !== '') {
                return $type;
            }

            $type = $this->typeFromCharge($paymentIntent->latest_charge ?? null);
            if ($type !== '') {
                return $type;
            }

            if (!empty($paymentIntent->payment_method_types) && is_array($paymentIntent->payment_method_types)) {
                return $this->normalize($paymentIntent->payment_method_types[0] ?? '');
            }

            return '';

        }

        private function typeFromPaymentIntent(mixed $paymentIntent): string
        {

            if (is_object($paymentIntent)) {
                return $this->typeFromPaymentIntentObject($paymentIntent);
            }

            $paymentIntentId = trim((string)$paymentIntent);
            if ($paymentIntentId === '') {
                return '';
            }

            try {

                $paymentIntentObject = $this->paymentIntents->retrieve($paymentIntentId, [
                    'expand' => [ 'payment_method', 'latest_charge' ]
                ], $this->opts);

                return $this->typeFromPaymentIntentObject($paymentIntentObject);

            } catch (ApiErrorException $e) {

                $this->logError('payment_method.detect.payment_intent', $e);

            } catch (\Throwable $e) {

                $this->logError('payment_method.detect.payment_intent', $e);

            }

            return '';

        }

        private function typeFromInvoicePaymentObject(object $invoicePayment): string
        {

            $payment = $invoicePayment->payment ?? null;
            if (!is_object($payment)) {
                return '';
            }

            $paymentType = $this->normalize($payment->type ?? '');
            if ($paymentType === 'payment_intent') {
                return $this->typeFromPaymentIntent($payment->payment_intent ?? null);
            }

            if ($paymentType === 'charge') {
                return $this->typeFromCharge($payment->charge ?? null);
            }

            return '';

        }

        public function fromInvoice(?object $invoice): string
        {

            if (!is_object($invoice)) {
                return '';
            }

            $invoiceId = trim((string)($invoice->id ?? ''));
            if ($invoiceId !== '' && array_key_exists($invoiceId, $this->cache)) {
                return (string)$this->cache[$invoiceId];
            }

            $directType = $this->typeFromPaymentIntent($invoice->payment_intent ?? null);
            if ($directType !== '') {
                return $this->remember($invoiceId, $directType);
            }

            $directType = $this->typeFromCharge($invoice->charge ?? null);
            if ($directType !== '') {
                return $this->remember($invoiceId, $directType);
            }

            if (
                !empty($invoice->payments) &&
                is_object($invoice->payments) &&
                !empty($invoice->payments->data) &&
                is_iterable($invoice->payments->data)
            ) {
                foreach ($invoice->payments->data as $invoicePayment) {
                    if (!is_object($invoicePayment)) {
                        continue;
                    }

                    $type = $this->typeFromInvoicePaymentObject($invoicePayment);
                    if ($type !== '') {
                        return $this->remember($invoiceId, $type);
                    }
                }
            }

            if ($invoiceId !== '') {
                try {

                    $invoicePayments = $this->invoicePayments->all([
                        'invoice' => $invoiceId,
                        'status' => 'paid',
                        'limit' => 10,
                        'expand' => [
                            'data.payment.payment_intent.payment_method',
                            'data.payment.payment_intent.latest_charge',
                            'data.payment.charge'
                        ]
                    ], $this->opts);

                    if (!empty($invoicePayments->data) && is_iterable($invoicePayments->data)) {
                        foreach ($invoicePayments->data as $invoicePayment) {
                            if (!is_object($invoicePayment)) {
                                continue;
                            }

                            $type = $this->typeFromInvoicePaymentObject($invoicePayment);
                            if ($type !== '') {
                                return $this->remember($invoiceId, $type);
                            }
                        }
                    }

                } catch (ApiErrorException $e) {

                    $this->logError('payment_method.detect.invoice_payments', $e);

                } catch (\Throwable $e) {

                    $this->logError('payment_method.detect.invoice_payments', $e);

                }
            }

            $directType = $this->typeFromPaymentMethod($invoice->default_payment_method ?? null);
            if ($directType !== '') {
                return $this->remember($invoiceId, $directType);
            }

            $directType = $this->normalize($invoice->payment_method_type ?? '');
            if ($directType !== '') {
                return $this->remember($invoiceId, $directType);
            }

            if (
                !empty($invoice->payment_settings) &&
                is_object($invoice->payment_settings) &&
                !empty($invoice->payment_settings->payment_method_types) &&
                is_array($invoice->payment_settings->payment_method_types)
            ) {
                $directType = $this->normalize($invoice->payment_settings->payment_method_types[0] ?? '');
                if ($directType !== '') {
                    return $this->remember($invoiceId, $directType);
                }
            }

            return $this->remember($invoiceId, '');

        }

        public function fromInvoiceId(?string $invoiceId): string
        {

            $invoiceId = trim((string)$invoiceId);
            if ($invoiceId === '') {
                return '';
            }

            if (array_key_exists($invoiceId, $this->cache)) {
                return (string)$this->cache[$invoiceId];
            }

            try {

                $invoice = $this->invoices->retrieve($invoiceId, [], $this->opts);
                return $this->fromInvoice($invoice);

            } catch (ApiErrorException $e) {

                $this->logError('payment_method.detect.invoice', $e);

            } catch (\Throwable $e) {

                $this->logError('payment_method.detect.invoice', $e);

            }

            return $this->remember($invoiceId, '');

        }

    }
