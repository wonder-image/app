<?php

    namespace Wonder\Plugin\Nexi;
    
    class Order {

        public $CURRENCY = "EUR";
        public $LANG = "ITA";

        public $TOTAL = 0;

        public $REFERENCE_ID, $DESCRIPTION;
        public $CONTEXT, $CUSTOMER = [];

        function __construct($referenceId, $total) {

            $this->REFERENCE_ID = $referenceId;
            $this->DESCRIPTION = "Pagamento ordine ".$referenceId;
            $this->TOTAL = number_format($total, 2, '', '');

        }

        function Currency( string $currency ) { $this->CURRENCY = strtoupper($currency); }
        function Lang( string $lang ) { $this->LANG = strtoupper($lang); }

        public function Customer ( $id, $fullName, $email ) {

            $this->CUSTOMER['id'] = $id;
            $this->CUSTOMER['name'] = $fullName;
            $this->CUSTOMER['email'] = $email;

        }

        public function Description ( $description ) { $this->DESCRIPTION = $description; }

        public function Context( $resultUrl, $cancelUrl, $notificationUrl = "" ) {

            $this->CONTEXT['result_url'] = $resultUrl;
            $this->CONTEXT['cancel_url'] = $cancelUrl;
            $this->CONTEXT['notification_url'] = $notificationUrl;

        }

        public function Create() {

            $order = [];

            $order['orderId'] = $this->REFERENCE_ID;
            $order['amount'] = $this->TOTAL;
            $order['currency'] = $this->CURRENCY;

            if (!empty($this->DESCRIPTION)) {
                $order['description'] = $this->DESCRIPTION;    
            }

            if (isset($this->CUSTOMER['id']) && !empty($this->CUSTOMER['id'])) {
                $order['customerId'] = $this->CUSTOMER['id'];    
            }

            if ((isset($this->CUSTOMER['name']) && !empty($this->CUSTOMER['name'])) || (isset($this->CUSTOMER['email']) && !empty($this->CUSTOMER['email']))) {

                $order['customerInfo'] = [];

                if (isset($this->CUSTOMER['name']) && !empty($this->CUSTOMER['name'])) {
                    $order['customerInfo']['cardHolderName'] = $this->CUSTOMER['name'];    
                }

                if (isset($this->CUSTOMER['email']) && !empty($this->CUSTOMER['email'])) {
                    $order['customerInfo']['cardHolderEmail'] = $this->CUSTOMER['email'];    
                }
                
            }

            $payment = [];

            $payment['actionType'] = 'PAY';
            $payment['amount'] = $this->TOTAL;
            $payment['recurrence'] = [];
            $payment['recurrence']['action'] = 'NO_RECURRING';
            $payment['language'] = $this->LANG;

            $payment['resultUrl'] = $this->CONTEXT['result_url'];
            $payment['cancelUrl'] = $this->CONTEXT['cancel_url'];

            if (!empty($this->CONTEXT['notification_url'])) {
                $payment['notificationUrl'] = $this->CONTEXT['notification_url'];    
            }

            return [
                'order' => $order,
                'paymentSession' => $payment,
            ];

        }

    }
