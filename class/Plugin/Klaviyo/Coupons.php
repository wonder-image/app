<?php

    namespace Wonder\Plugin\Klaviyo;

    use InvalidArgumentException;

    /**
     * @mixin \KlaviyoAPI\API\CouponsApi
     */
    class Coupons extends Klaviyo {

        protected const COUPON_TYPE = 'coupon';
        protected const COUPON_CODE_TYPE = 'coupon-code';

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Coupons;

        }

        public function all()
        {

            return $this->getCoupons();

        }

        public function get($couponId)
        {

            return $this->getCoupon($couponId);

        }

        public function create()
        {

            $this->couponType();

            return $this->createCoupon();

        }

        public function update($couponId)
        {

            $this->couponType();

            return $this->updateCoupon($couponId);

        }

        public function delete($couponId)
        {

            return $this->deleteCoupon($couponId);

        }

        public function allCodes(?string $filter = null)
        {

            $filter ??= $this->params['filter'] ?? null;

            if ($filter === null) {
                throw new InvalidArgumentException('Missing filter for coupon codes listing.');
            }

            return $this->object()->getCouponCodes(...array_merge([
                'filter' => $filter
            ], $this->onlyParams([
                'fields_coupon_code',
                'fields_coupon',
                'include',
                'page_cursor',
                'apiKey',
                'contentType',
            ])));

        }

        public function codes($couponId, ?string $filter = null)
        {

            if ($filter !== null) {
                $this->filter($filter);
            }

            return $this->getCouponCodesForCoupon($couponId);

        }

        public function code($codeId)
        {

            return $this->getCouponCode($codeId);

        }

        public function createCode($couponId = null)
        {

            if ($couponId !== null) {
                $this->couponId($couponId);
            }

            $this->couponCodeType();

            return $this->createCouponCode();

        }

        public function updateCode($codeId)
        {

            $this->couponCodeType();

            return $this->updateCouponCode($codeId);

        }

        public function deleteCode($codeId)
        {

            return $this->deleteCouponCode($codeId);

        }

        public function externalId($value): static
        {

            $this->couponType();

            return $this->dataAttribute('external_id', $value);

        }

        public function description($value): static
        {

            return $this->dataAttribute('description', $value);

        }

        public function monitorConfiguration(array $value): static
        {

            return $this->dataAttribute('monitor_configuration', $value);

        }

        public function uniqueCode($value): static
        {

            $this->couponCodeType();

            return $this->dataAttribute('unique_code', $value);

        }

        public function expiresAt($value): static
        {

            $this->couponCodeType();

            return $this->dataAttribute('expires_at', $value);

        }

        public function status($value): static
        {

            $this->couponCodeType();

            return $this->dataAttribute('status', $value);

        }

        public function couponId($value): static
        {

            $this->couponCodeType();

            return $this->relationshipId('coupon', self::COUPON_TYPE, $value);

        }

        protected function couponType(): static
        {

            return $this->dataType(self::COUPON_TYPE);

        }

        protected function couponCodeType(): static
        {

            return $this->dataType(self::COUPON_CODE_TYPE);

        }

    }
