<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\CatalogsApi
     */
    class Catalogs extends Klaviyo {

        protected const CATEGORY_TYPE = 'catalog-category';
        protected const ITEM_TYPE = 'catalog-item';
        protected const VARIANT_TYPE = 'catalog-variant';

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Catalogs;

        }

        public function allCategories()
        {

            return $this->getCatalogCategories();

        }

        public function category($categoryId)
        {

            return $this->getCatalogCategory($categoryId);

        }

        public function createCategory()
        {

            $this->categoryType();

            return $this->createCatalogCategory();

        }

        public function updateCategory($categoryId)
        {

            $this->categoryType();

            return $this->updateCatalogCategory($categoryId);

        }

        public function deleteCategory($categoryId)
        {

            return $this->deleteCatalogCategory($categoryId);

        }

        public function allItems()
        {

            return $this->getCatalogItems();

        }

        public function item($itemId)
        {

            return $this->getCatalogItem($itemId);

        }

        public function createItem()
        {

            $this->itemType();

            return $this->createCatalogItem();

        }

        public function updateItem($itemId)
        {

            $this->itemType();

            return $this->updateCatalogItem($itemId);

        }

        public function deleteItem($itemId)
        {

            return $this->deleteCatalogItem($itemId);

        }

        public function allVariants()
        {

            return $this->getCatalogVariants();

        }

        public function variant($variantId)
        {

            return $this->getCatalogVariant($variantId);

        }

        public function createVariant()
        {

            $this->variantType();

            return $this->createCatalogVariant();

        }

        public function updateVariant($variantId)
        {

            $this->variantType();

            return $this->updateCatalogVariant($variantId);

        }

        public function deleteVariant($variantId)
        {

            return $this->deleteCatalogVariant($variantId);

        }

        public function addCategory($itemId, $categoryId)
        {

            return $this->addCategories($itemId, [$categoryId]);

        }

        public function addCategories($itemId, ?array $categoryIds = null)
        {

            if ($categoryIds !== null) {
                $this->categoryIds($categoryIds);
            }

            return $this->addCategoriesToCatalogItem($itemId);

        }

        public function removeCategories($itemId, ?array $categoryIds = null)
        {

            if ($categoryIds !== null) {
                $this->categoryIds($categoryIds);
            }

            return $this->removeCategoriesFromCatalogItem($itemId);

        }

        public function setCategories($itemId, ?array $categoryIds = null)
        {

            if ($categoryIds !== null) {
                $this->categoryIds($categoryIds);
            }

            return $this->updateCategoriesForCatalogItem($itemId);

        }

        public function addItem($categoryId, $itemId)
        {

            return $this->addItems($categoryId, [$itemId]);

        }

        public function addItems($categoryId, ?array $itemIds = null)
        {

            if ($itemIds !== null) {
                $this->itemIds($itemIds);
            }

            return $this->addItemsToCatalogCategory($categoryId);

        }

        public function removeItems($categoryId, ?array $itemIds = null)
        {

            if ($itemIds !== null) {
                $this->itemIds($itemIds);
            }

            return $this->removeItemsFromCatalogCategory($categoryId);

        }

        public function setItems($categoryId, ?array $itemIds = null)
        {

            if ($itemIds !== null) {
                $this->itemIds($itemIds);
            }

            return $this->updateItemsForCatalogCategory($categoryId);

        }

        public function externalId($value): static
        {

            return $this->dataAttribute('external_id', $value);

        }

        public function integrationType($value = '$custom'): static
        {

            return $this->dataAttribute('integration_type', $value);

        }

        public function catalogType($value): static
        {

            return $this->dataAttribute('catalog_type', $value);

        }

        public function name($value): static
        {

            $this->categoryType();

            return $this->dataAttribute('name', $value);

        }

        public function title($value): static
        {

            return $this->dataAttribute('title', $value);

        }

        public function description($value): static
        {

            return $this->dataAttribute('description', $value);

        }

        public function url($value): static
        {

            return $this->dataAttribute('url', $value);

        }

        public function price($value): static
        {

            return $this->dataAttribute('price', $value);

        }

        public function imageFullUrl($value): static
        {

            return $this->dataAttribute('image_full_url', $value);

        }

        public function imageThumbnailUrl($value): static
        {

            return $this->dataAttribute('image_thumbnail_url', $value);

        }

        public function images(array $value): static
        {

            return $this->dataAttribute('images', $value);

        }

        public function image($value): static
        {

            return $this->pushParams('data.attributes.images', $value);

        }

        public function customMetadata(array $value): static
        {

            return $this->dataAttribute('custom_metadata', $value);

        }

        public function customMeta(string $key, $value): static
        {

            return $this->addParams("data.attributes.custom_metadata.$key", $value);

        }

        public function published(bool $value = true): static
        {

            return $this->dataAttribute('published', $value);

        }

        public function sku($value): static
        {

            $this->variantType();

            return $this->dataAttribute('sku', $value);

        }

        public function inventoryPolicy($value): static
        {

            $this->variantType();

            return $this->dataAttribute('inventory_policy', $value);

        }

        public function inventoryQuantity($value): static
        {

            $this->variantType();

            return $this->dataAttribute('inventory_quantity', $value);

        }

        public function parentItemId($value): static
        {

            $this->variantType();

            return $this->relationshipId('item', self::ITEM_TYPE, $value);

        }

        public function categoryRelationshipIds(array $values): static
        {

            $this->itemType();

            return $this->relationshipIds('categories', self::CATEGORY_TYPE, $values);

        }

        public function categoryIds(array $values): static
        {

            return $this->addParams('data', array_map(
                static fn ($id) => [
                    'type' => self::CATEGORY_TYPE,
                    'id' => $id,
                ],
                $values
            ));

        }

        public function itemIds(array $values): static
        {

            return $this->addParams('data', array_map(
                static fn ($id) => [
                    'type' => self::ITEM_TYPE,
                    'id' => $id,
                ],
                $values
            ));

        }

        protected function categoryType(): static
        {

            return $this->dataType(self::CATEGORY_TYPE);

        }

        protected function itemType(): static
        {

            return $this->dataType(self::ITEM_TYPE);

        }

        protected function variantType(): static
        {

            return $this->dataType(self::VARIANT_TYPE);

        }

    }
