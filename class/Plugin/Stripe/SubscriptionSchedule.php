<?php

    namespace Wonder\Plugin\Stripe;

    use Wonder\Plugin\Stripe\Stripe;
    
    class SubscriptionSchedule extends Stripe {

        public function object()
        {
            
            return parent::connect()->subscriptionSchedules;

        }

        public function create() 
        {

            return $this->object()->create($this->params, $this->opts);

        }

        public function update($scheduleId) 
        {

            return $this->object()->update($scheduleId, $this->params, $this->opts);

        }

        public function get($scheduleId) 
        {
            
            return $this->object()->retrieve($scheduleId, $this->params, $this->opts);

        }

        public function cancel($scheduleId) 
        {

            return $this->object()->cancel($scheduleId, $this->params, $this->opts);

        }

        public function release($scheduleId) 
        {

            return $this->object()->release($scheduleId, $this->params, $this->opts);

        }

        public function fromSubscription($subscriptionId) 
        {

            return $this->addParams('from_subscription', $subscriptionId);

        }

        public function endBehavior($value) 
        {

            return $this->addParams('end_behavior', $value);

        }

        public function phases(array $phases) 
        {

            return $this->addParams('phases', $phases);

        }

        public function prorationBehavior($value) 
        {

            return $this->addParams('proration_behavior', $value);

        }

    }
