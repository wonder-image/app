<?php

    namespace Wonder\Plugin\FattureInCloud;

    use FattureInCloud\Model\IssuedDocumentItemsListItem as FattureInCloudDocumentItem;
    use FattureInCloud\Model\VatType;

    class DocumentItem extends FattureInCloudDocumentItem
    {

        public static function service(
            string $name,
            string $description,
            float $netPrice,
            float $qty = 1.0
        ): static {

            return (new static())
                ->setName($name)
                ->setDescription($description)
                ->setQty($qty)
                ->setNetPrice($netPrice);

        }

        public function setVatId($id, $value = null): static
        {

            $vat = (new VatType())
                ->setId((int)$id);

            if ($value !== null) {
                $vat->setValue((float)$value);
            }

            return parent::setVat($vat);

        }

    }
