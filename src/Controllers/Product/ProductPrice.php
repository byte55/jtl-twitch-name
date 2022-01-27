<?php
/**
 * @author    Jan Weskamp <jan.weskamp@jtl-software.com>
 * @copyright 2010-2013 JTL-Software GmbH
 */

namespace JtlWooCommerceConnector\Controllers\Product;

use jtl\Connector\Model\CustomerGroup as CustomerGroupModel;
use jtl\Connector\Model\Identity;
use jtl\Connector\Model\Product as ProductModel;
use jtl\Connector\Model\ProductPrice as ProductPriceModel;
use jtl\Connector\Model\ProductPriceItem as ProductPriceItemModel;
use JtlWooCommerceConnector\Controllers\BaseController;
use JtlWooCommerceConnector\Controllers\GlobalData\CustomerGroup;
use JtlWooCommerceConnector\Utilities\Config;
use JtlWooCommerceConnector\Utilities\SupportedPlugins;
use JtlWooCommerceConnector\Utilities\Util;

class ProductPrice extends BaseController
{
    const GUEST_CUSTOMER_GROUP = 'wc_guest_customer_group';

    public function pullData(\WC_Product $product, ProductModel $model)
    {
        $prices = [];
        $groupController = new CustomerGroup();

        if (!SupportedPlugins::isActive(SupportedPlugins::PLUGIN_B2B_MARKET)) {
            $prices[] = (new ProductPriceModel())
                ->setId(new Identity($product->get_id()))
                ->setProductId(new Identity($product->get_id()))
                ->setCustomerGroupId(new Identity(CustomerGroup::DEFAULT_GROUP))
                ->addItem((new ProductPriceItemModel())
                    ->setProductPriceId(new Identity($product->get_id()))
                    ->setQuantity(1)
                    ->setNetPrice($this->netPrice($product)));
        } else {
            $customerGroups = $groupController->pullData();

            if (SupportedPlugins::comparePluginVersion(SupportedPlugins::PLUGIN_B2B_MARKET, '>', '1.0.3')) {
                $prices[] = (new ProductPriceModel())
                    ->setId(new Identity($product->get_id()))
                    ->setProductId(new Identity($product->get_id()))
                    ->setCustomerGroupId(new Identity(""))
                    ->addItem((new ProductPriceItemModel())
                        ->setProductPriceId(new Identity($product->get_id()))
                        ->setQuantity(1)
                        ->setNetPrice($this->netPrice($product)));
            }

            /** @var CustomerGroupModel $customerGroup */
            foreach ($customerGroups as $cKey => $customerGroup) {

                $items = [];

                $customerGroupEndpointId = $customerGroup->getId()->getEndpoint();

                if ($customerGroupEndpointId === CustomerGroup::DEFAULT_GROUP && !SupportedPlugins::isActive(SupportedPlugins::PLUGIN_B2B_MARKET) ||
                   ($customerGroupEndpointId === CustomerGroup::DEFAULT_GROUP && SupportedPlugins::comparePluginVersion(SupportedPlugins::PLUGIN_B2B_MARKET, '<=', '1.0.3'))
                ){
                    $items[] = (new ProductPriceItemModel())
                        ->setProductPriceId(new Identity($product->get_id()))
                        ->setQuantity(1)
                        ->setNetPrice($this->netPrice($product));
                } else {
                    $groupSlug = $groupController->getSlugById($customerGroupEndpointId);

                    $price = $this->getB2BMarketCustomerGroupPrice($model, $product, $groupSlug);

                    if ($price === null) {
                        $price = $this->netPrice($product);
                    }

                    $items[] = (new ProductPriceItemModel())
                        ->setProductPriceId(new Identity($product->get_id()))
                        ->setQuantity(1)
                        ->setNetPrice((float)$price);

                    $items = $this->getBulkPrices($items, $customerGroup, $groupSlug, $product, $model);
                }

                $prices[] = (new ProductPriceModel())
                    ->setId(new Identity($product->get_id()))
                    ->setProductId(new Identity($product->get_id()))
                    ->setCustomerGroupId($customerGroup->getId())
                    ->setItems($items);
            }
        }

        return $prices;
    }

    /**
     * @param ProductModel $model
     * @param \WC_Product $wcProduct
     * @param string $groupSlug
     * @return float|null
     */
    protected function getB2BMarketCustomerGroupPrice(ProductModel $model, \WC_Product $wcProduct, string $groupSlug): ?float
    {
        $price = null;

        if (SupportedPlugins::comparePluginVersion(SupportedPlugins::PLUGIN_B2B_MARKET, '>=', '1.0.8.0')) {

            $pricesMetaKey = sprintf('bm_%s_group_prices', $groupSlug);
            $priceGroupMeta = get_post_meta($wcProduct->get_id(), $pricesMetaKey, true);

            if (is_array($priceGroupMeta)) {
                foreach ($priceGroupMeta as $priceMeta) {
                    if (isset($priceMeta['group_price'], $priceMeta['group_price_type']) && $priceMeta['group_price_type'] === 'fix') {
                        $price = $priceMeta['group_price'];
                        break;
                    }
                }
            }


        } else {
            if ($model->getIsMasterProduct() || $wcProduct->is_type('simple')) {
                $productIdForMeta = $wcProduct->get_id();
                $priceKeyForMeta = sprintf('bm_%s_price', $groupSlug);
                $typeKeyForMeta = sprintf('bm_%s_price_type', $groupSlug);
            } else {
                $productIdForMeta = $wcProduct->get_parent_id();
                $priceKeyForMeta = sprintf('bm_%s_%s_price', $groupSlug, $wcProduct->get_id());
                $typeKeyForMeta = sprintf('bm_%s_%s_price_type', $groupSlug, $wcProduct->get_id());
            }

            $type = \get_post_meta($productIdForMeta, $typeKeyForMeta, true);
            if ($type === 'fix') {
                $price = \get_post_meta($productIdForMeta, $priceKeyForMeta, true);
            }
        }

        return !is_null($price) ? (float)$price : null;
    }


    private function getBulkPrices(
        $items,
        CustomerGroupModel $customerGroup,
        $groupSlug,
        \WC_Product $product,
        ProductModel $model
    ) {
        if (in_array($product->get_type(), ['simple', 'variable'])) {
            $metaKey = sprintf('bm_%s_bulk_prices', $groupSlug);
            $metaProductId = $product->get_id();
        } else {
            $metaKey = sprintf('bm_%s_%s_bulk_prices', $groupSlug, $product->get_id());
            $metaProductId = $product->get_parent_id();
        }

        $bulkPrices = \get_post_meta($metaProductId, $metaKey, true);

        if (!is_array($bulkPrices)) {
            $bulkPrices = [];
        }

        foreach ($bulkPrices as $bulkPrice) {
            if ($bulkPrice['bulk_price_type'] === 'fix') {
                $items[] = (new ProductPriceItemModel())
                    ->setProductPriceId(new Identity($product->get_id()))
                    ->setQuantity((int)$bulkPrice['bulk_price_from'])
                    ->setNetPrice((float)$bulkPrice['bulk_price']);
            }
        }

        return $items;
    }

    protected function netPrice(\WC_Product $product)
    {
        $taxRate = Util::getInstance()->getTaxRateByTaxClass($product->get_tax_class());
        $pd = Util::getPriceDecimals();

        $netPrice = (float)$product->get_regular_price();
        if (\wc_prices_include_tax() && $taxRate != 0) {
            $netPrice = round($netPrice / ($taxRate + 100), $pd) * 100;
        } else {
            $netPrice = round($netPrice, $pd);
        }

        return (float)$netPrice;
    }

    /**
     * @param ProductPriceModel ...$jtlProductPrices
     * @return array
     */
    protected function groupProductPrices(\jtl\Connector\Model\ProductPrice ...$jtlProductPrices): array
    {
        $groupedProductPrices = [];

        foreach ($jtlProductPrices as $price) {
            $endpoint = $price->getCustomerGroupId()->getEndpoint();

            if (SupportedPlugins::comparePluginVersion(SupportedPlugins::PLUGIN_B2B_MARKET, '>', '1.0.3')) {
                if ((string)$endpoint === Config::get('jtlconnector_default_customer_group')) {
                    $groupedProductPrices[CustomerGroup::DEFAULT_GROUP] = (new ProductPriceModel())
                        ->setCustomerGroupId(new Identity(CustomerGroup::DEFAULT_GROUP))
                        ->setProductId($price->getProductId())
                        ->setItems($price->getItems());
                }
            }

            if (Util::getInstance()->isValidCustomerGroup($endpoint)) {
                if ($endpoint === '') {
                    $endpoint = self::GUEST_CUSTOMER_GROUP;
                    if (SupportedPlugins::comparePluginVersion(SupportedPlugins::PLUGIN_B2B_MARKET, '>', '1.0.3')) {
                        $endpoint = CustomerGroup::DEFAULT_GROUP;
                    }
                }
                $groupedProductPrices[$endpoint] = $price;
            }
        }

        return $groupedProductPrices;
    }

    /**
     * @param \WC_Product $wcProduct
     * @param float $vat
     * @param string $productType
     * @param ProductPriceModel ...$productPrices
     */
    public function savePrices(\WC_Product $wcProduct, float $vat, string $productType, \jtl\Connector\Model\ProductPrice ...$productPrices)
    {
        Util::deleteB2Bcache();

        $groupedProductPrices = $this->groupProductPrices(...$productPrices);
        if (count($groupedProductPrices) > 0) {
            $this->updateProductPrices($wcProduct, $groupedProductPrices, $vat, $productType);
        }
    }

    /**
     * @param \WC_Product $wcProduct
     * @param array $groupedProductPrices
     * @param float $vat
     * @param string $productType
     */
    public function updateProductPrices(\WC_Product $wcProduct, array $groupedProductPrices, float $vat, string $productType)
    {
        $pd = Util::getPriceDecimals();

        $defaultCustomerGroup = Config::get('jtlconnector_default_customer_group');

        /** @var ProductPriceModel $productPrice */
        foreach ($groupedProductPrices as $customerGroupId => $productPrice) {
            if ((string)$customerGroupId === self::GUEST_CUSTOMER_GROUP || !Util::getInstance()->isValidCustomerGroup((string)$customerGroupId)) {
                continue;
            }

            $productId = $wcProduct->get_id();

            $customerGroupMeta = null;
            if (is_int($customerGroupId)) {
                $customerGroupMeta = \get_post_meta($customerGroupId);
            }

            if ($customerGroupId === CustomerGroup::DEFAULT_GROUP && is_null($customerGroupMeta) && !SupportedPlugins::isActive(SupportedPlugins::PLUGIN_B2B_MARKET)) {
                foreach ($productPrice->getItems() as $item) {
                    $this->updateDefaultProductPrice($item, $productId, $vat);
                }
            } elseif (!is_null($customerGroupMeta) && SupportedPlugins::isActive(SupportedPlugins::PLUGIN_B2B_MARKET)) {
                $customerGroup = get_post($customerGroupId);
                $bulkPrices = [];

                foreach ($productPrice->getItems() as $item) {

                    $regularPrice = $this->getRegularPrice($item, $vat, $pd);
                    if ($item->getQuantity() === 0) {

                        if ((string)$customerGroup->ID === $defaultCustomerGroup) {
                            $this->updateDefaultProductPrice($item, $productId, $vat);
                        }

                        $this->updateB2BMarketCustomerGroupPrice($customerGroup, $productType, $wcProduct, $regularPrice);
                    } else {
                        $bulkPrices[] = [
                            'bulk_price' => (string)$regularPrice,
                            'bulk_price_from' => (string)$item->getQuantity(),
                            'bulk_price_to' => '',
                            'bulk_price_type' => 'fix',
                        ];
                    }
                }

                if (count($bulkPrices) > 0) {

                    $metaKey = sprintf('bm_%s_bulk_prices', $customerGroup->post_name);
                    $bulkPrices = Util::setBulkPricesQuantityTo($bulkPrices);

                    \update_post_meta(
                        $productId,
                        $metaKey,
                        $bulkPrices,
                        \get_post_meta($productId, $metaKey, true)
                    );

                    if ($wcProduct->get_parent_id() !== 0) {
                        $metaKey = sprintf('bm_%s_%s_bulk_prices', $customerGroup->post_name, $productId);
                        $metaProductId = $wcProduct->get_parent_id();

                        \update_post_meta(
                            $metaProductId,
                            $metaKey,
                            $bulkPrices,
                            \get_post_meta($metaProductId, $metaKey, true)
                        );
                    }
                } else {
                    \delete_post_meta(
                        $productId,
                        sprintf('bm_%s_bulk_prices', $customerGroup->post_name)
                    );

                    if ($wcProduct->get_parent_id() !== 0) {
                        $metaKey = sprintf('bm_%s_%s_bulk_prices', $customerGroup->post_name, $productId);
                        $metaProductId = $wcProduct->get_parent_id();
                        \delete_post_meta(
                            $metaProductId,
                            $metaKey
                        );
                    }
                }
            }
        }
    }

    /**
     * @param \WP_Post $customerGroup
     * @param string $productType
     * @param \WC_Product $wcProduct
     * @param float $regularPrice
     */
    public function updateB2BMarketCustomerGroupPrice(\WP_Post $customerGroup, string $productType, \WC_Product $wcProduct, float $regularPrice): void
    {
        $pd = Util::getPriceDecimals();
        $productId = $wcProduct->get_id();

        if (SupportedPlugins::comparePluginVersion(SupportedPlugins::PLUGIN_B2B_MARKET, '>=', '1.0.8.0')) {

            $metaKey = sprintf('bm_%s_group_prices', $customerGroup->post_name);

            $price = [
                'group_price_type' => 'fix',
                'group_price' => \wc_format_decimal($regularPrice, $pd)
            ];

            \update_post_meta($productId,
                $metaKey,
                [$price],
                \get_post_meta($wcProduct->get_id(), $metaKey, true));

        } else {
            $metaKeyForCustomerGroupPrice = sprintf(
                'bm_%s_price',
                $customerGroup->post_name);

            if ($productType !== Product::TYPE_PARENT) {
                $metaKeyForCustomerGroupRegularPrice = sprintf(
                    '_jtlwcc_bm_%s_regular_price',
                    $customerGroup->post_name
                );

                if ($productType === Product::TYPE_CHILD) {
                    $parentProduct = \wc_get_product($wcProduct->get_parent_id());
                    if ($parentProduct instanceof \WC_Product) {
                        $childParentPrice = sprintf(
                            'bm_%s_%s_price',
                            $customerGroup->post_name,
                            $productId
                        );
                        \update_post_meta($parentProduct->get_id(),
                            $childParentPrice,
                            \wc_format_decimal($regularPrice, $pd),
                            \get_post_meta($parentProduct->get_id(), $childParentPrice, true));

                        $childParentKey = sprintf(
                            'bm_%s_%s_price_type',
                            $customerGroup->post_name,
                            $productId
                        );
                        \update_post_meta($parentProduct->get_id(),
                            $childParentKey,
                            'fix',
                            \get_post_meta($parentProduct->get_id(), $childParentKey, true));
                    }
                }
            }
            $metaKeyForCustomerGroupPriceType = $metaKeyForCustomerGroupPrice . '_type';

            \update_post_meta(
                $productId,
                $metaKeyForCustomerGroupPrice,
                \wc_format_decimal($regularPrice, $pd),
                \get_post_meta($productId, $metaKeyForCustomerGroupPrice, true)
            );

            if ($productType !== Product::TYPE_PARENT && isset($metaKeyForCustomerGroupRegularPrice)) {
                \update_post_meta($productId, $metaKeyForCustomerGroupRegularPrice,
                    \wc_format_decimal($regularPrice, $pd),
                    \get_post_meta($productId, $metaKeyForCustomerGroupRegularPrice, true));
            }

            \update_post_meta(
                $productId,
                $metaKeyForCustomerGroupPriceType,
                'fix',
                \get_post_meta($productId, $metaKeyForCustomerGroupPriceType, true)
            );
        }
    }

    /**
     * @param ProductPriceItemModel $item
     * @param int $productId
     * @param float $vat
     */
    protected function updateDefaultProductPrice(ProductPriceItemModel $item, int $productId, float $vat)
    {
        $regularPrice = $this->getRegularPrice($item, $vat);

        if ($item->getQuantity() === 0) {
            $salePrice = \get_post_meta($productId, '_sale_price', true);

            if (empty($salePrice) || $salePrice !== \get_post_meta($productId, '_price', true)) {
                \update_post_meta($productId, '_price', \wc_format_decimal($regularPrice),
                    \get_post_meta($productId, '_price', true));
            }

            \update_post_meta($productId, '_regular_price', \wc_format_decimal($regularPrice),
                \get_post_meta($productId, '_regular_price', true));
        }
    }

    /**
     * @param ProductPriceItemModel $item
     * @param float $vat
     * @param int|null $pd
     * @return float
     */
    protected function getRegularPrice(ProductPriceItemModel $item, float $vat, int $pd = null): float
    {
        if ($this->isWcPricesIncludeTax()) {
            $regularPrice = $item->getNetPrice() * (1 + $vat / 100);
        } else {
            $regularPrice = $item->getNetPrice();
        }

        if (!is_null($pd)) {
            $regularPrice = round($regularPrice, $pd);
        }

        return $regularPrice;
    }

    /**
     * @return bool
     */
    protected function isWcPricesIncludeTax(): bool
    {
        return \wc_prices_include_tax();
    }
}
