<?php
/**
 * @author    Jan Weskamp <jan.weskamp@jtl-software.com>
 * @copyright 2010-2013 JTL-Software GmbH
 */

namespace JtlWooCommerceConnector\Controllers\Product;

use jtl\Connector\Model\Identity;
use jtl\Connector\Model\Product as ProductModel;
use jtl\Connector\Model\ProductAttr as ProductAttrModel;
use jtl\Connector\Model\ProductAttrI18n as ProductAttrI18nModel;
use jtl\Connector\Model\ProductVariationI18n as ProductVariationI18nModel;
use jtl\Connector\Model\ProductVariationValue as ProductVariationValueModel;
use jtl\Connector\Model\ProductVariationValueI18n as ProductVariationValueI18nModel;
use jtl\Connector\Model\ProductSpecific as ProductSpecificModel;
use JtlWooCommerceConnector\Controllers\BaseController;
use JtlWooCommerceConnector\Utilities\SqlHelper;
use JtlWooCommerceConnector\Utilities\SupportedPlugins;
use JtlWooCommerceConnector\Utilities\SupportedPlugins as SupportedPluginsAlias;
use JtlWooCommerceConnector\Utilities\Util;
use WC_Product_Attribute;

if (!defined('WC_DELIMITER')) {
    define('WC_DELIMITER', '|');
}

class ProductVaSpeAttrHandler extends BaseController
{
    public const
        PRODUCT_TYPE_ATTR = 'wc_product_type',
        DELIVERY_TIME_ATTR = 'wc_dt_offset',
        DOWNLOADABLE_ATTR = 'wc_downloadable',
        FACEBOOK_VISIBILITY_ATTR = 'wc_fb_visibility',
        FACEBOOK_SYNC_STATUS_ATTR = 'wc_fb_sync_status',
        PAYABLE_ATTR = 'wc_payable',
        NOSEARCH_ATTR = 'wc_nosearch',
        VISIBILITY = 'wc_visibility',
        VIRTUAL_ATTR = 'wc_virtual',
        PURCHASE_NOTE_ATTR = 'wc_purchase_note',
        PURCHASE_ONLY_ONE_ATTR = 'wc_sold_individually',
        NOTIFY_CUSTOMER_ON_OVERSELLING = 'wc_notify_customer_on_overselling',

        //GERMAN MARKET
        GM_DIGITAL_ATTR = 'wc_gm_digital',
        GM_ALT_DELIVERY_NOTE_ATTR = 'wc_gm_alt_delivery_note',
        GM_SUPPRESS_SHIPPPING_NOTICE = 'wc_gm_suppress_shipping_notice',

        //GERMANIZED
        GZD_IS_SERVICE = 'wc_gzd_is_service',

        //MISC
        JTL_CURRENT_PRODUCT_SPECIFICS = 'jtl_current_specifics',
        VALUE_TRUE = 'true',
        VALUE_FALSE = 'false';

    private $productData = [
        'productVariation'  => [],
        'productAttributes' => [],
        'productSpecifics'  => [],
    ];
    
    private $values = [];
    
    public function pullData(\WC_Product $product, ProductModel $model)
    {
        $globCurrentAttr = $product->get_attributes();
        $isProductVariation = $product instanceof \WC_Product_Variation;
        $isProductVariationParent = $product instanceof \WC_Product_Variable;
        $languageIso = Util::getInstance()->getWooCommerceLanguage();
        
        if (!$isProductVariation) {
            /**
             * @var string                $slug
             * @var WC_Product_Attribute $attribute
             */
            foreach ($globCurrentAttr as $slug => $attribute) {
                
                $isVariation = $attribute->get_variation();
                $taxonomyExistsCurrentAttr = taxonomy_exists($slug);
                
                // <editor-fold defaultstate="collapsed" desc="Handling ATTR Pull">
                if (!$isVariation && !$taxonomyExistsCurrentAttr) {
                    $this->productData['productAttributes'][] = (new ProductAttr)
                        ->pullData(
                            $product,
                            $attribute,
                            $slug,
                            $languageIso
                        );
                }
                // </editor-fold>
                // <editor-fold defaultstate="collapsed" desc="Handling Specific Pull">
                if (!$isVariation && $taxonomyExistsCurrentAttr) {
                    $tmp = (new ProductSpecific)
                        ->pullData(
                            $model,
                            $product,
                            $attribute,
                            $slug
                        );
                    if (is_null($tmp)) {
                        continue;
                    }
                    foreach ($tmp as $productSpecific) {
                        $this->productData['productSpecifics'][] = $productSpecific;
                    }
                }
                // </editor-fold>
                // <editor-fold defaultstate="collapsed" desc="Handling Variation Parent Pull">
                
                if ($isVariation && $isProductVariationParent) {
                    $tmp = (new ProductVariation)
                        ->pullDataParent(
                            $model,
                            $attribute,
                            $languageIso
                        );
                    if (is_null($tmp)) {
                        continue;
                    }
                    $this->productData['productVariation'][] = $tmp;
                }
                
                // </editor-fold>
            }
        } else {
            // <editor-fold defaultstate="collapsed" desc="Handling Variation Child Pull">
            $tmp = (new ProductVariation)
                ->pullDataChild(
                    $product,
                    $model,
                    $languageIso
                );
            if (!is_null($tmp)) {
                $this->productData['productVariation'] = $tmp;
            }
            // </editor-fold>
        }
        
        // <editor-fold defaultstate="collapsed" desc="FUNC ATTR Pull">
        $this->handleCustomPropertyAttributes($product, $languageIso);
        $this->setProductFunctionAttributes($product, $languageIso);
        
        // </editor-fold>
        
        return $this->productData;
    }
    
    public function pushDataNew(ProductModel $product, \WC_Product $wcProduct)
    {
        if ($wcProduct === false) {
            return;
        }
        //Identify Master = parent/simple
        $isMaster = $product->getMasterProductId()->getHost() === 0;
        
        $productId = $product->getId()->getEndpoint();
        
        if ($isMaster) {
            $newWcProductAttributes = [];
            //Current Values
            $wcProductAttributes = $wcProduct->get_attributes();
            
            //Filtered
            $currentVariationsAndSpecifics = $this->getVariationAndSpecificAttributes($wcProductAttributes, $product->getVariations());

            $currentAttributes = $this->getVariationAttributes($wcProductAttributes, ...$product->getAttributes());
            
            //GENERATE DATA ARRAYS
            $jtlVariations = $this->generateVariationSpecificData($product->getVariations());
            $jtlSpecifics = $this->generateSpecificData($product->getSpecifics());
            
            //handleAttributes
            $productAttributes = (new ProductAttr)->pushData($productId, $product->getAttributes(), $currentVariationsAndSpecifics, $product);

            $this->mergeAttributes($newWcProductAttributes, $productAttributes);
            
            // handleSpecifics
            $productSpecifics = (new ProductSpecific)->pushData($productId, $wcProductAttributes, $jtlSpecifics, $product->getSpecifics(), $product->getAttributes());

            $this->mergeAttributes($newWcProductAttributes, $productSpecifics);

            // handleVarSpecifics
            $productVariations = (new ProductVariation)->pushMasterData($productId, $jtlVariations, $currentAttributes);
            
            if (!is_array($productVariations)) {
                $productVariations = [];
            }

            $this->mergeAttributes($newWcProductAttributes, $productVariations);

            $jtlNewProductSpecifics = array_filter(array_map(function (ProductSpecificModel $productSpecific) {
                return $productSpecific->getId()->getEndpoint();
            }, $product->getSpecifics()), function ($value) {
                return $value !== '';
            });

            $jtlOldProductSpecifics = get_post_meta($wcProduct->get_id(), self::JTL_CURRENT_PRODUCT_SPECIFICS);
            update_post_meta($wcProduct->get_id(), self::JTL_CURRENT_PRODUCT_SPECIFICS, $jtlNewProductSpecifics);

            if (!empty($jtlOldProductSpecifics)) {
                $jtlOldProductSpecifics = $jtlOldProductSpecifics[0];
                $removeSpecifics = array_diff($jtlOldProductSpecifics, $jtlNewProductSpecifics);

                foreach ($newWcProductAttributes as $index => $attribute) {
                    if (isset($attribute['id']) && in_array($attribute['id'], $removeSpecifics)) {
                        unset($newWcProductAttributes[$index]);
                    }
                }
            }

            $old = \get_post_meta($productId, '_product_attributes', true);
            \update_post_meta($productId, '_product_attributes', $newWcProductAttributes, $old);
            
        } else {
            (new ProductVariation)->pushChildData(
                $productId,
                $product->getVariations()
            );
        }
        // remove the transient to renew the cache
        delete_transient('wc_attribute_taxonomies');
    }
    
    // <editor-fold defaultstate="collapsed" desc="Filtered Methods">
    private function getVariationAndSpecificAttributes(array &$attributes = [], array $variations = [])
    {
        $filteredAttributes = [];
        /** @var \jtl\Connector\Model\ProductVariation $variation */
        $jtlVariations = [];
        foreach ($variations as $variation) {
            foreach ($variation->getI18ns() as $productVariationI18n) {
                if (Util::getInstance()->isWooCommerceLanguage($productVariationI18n->getLanguageISO())) {
                    $jtlVariations[] = $productVariationI18n->getName();
                }
            }
        }

        /**
         * @var string                $slug The attributes unique slug.
         * @var WC_Product_Attribute $attribute The attribute.
         */
        foreach ($attributes as $slug => $attribute) {
            if ($attribute->get_variation()) {
                if ($attribute->get_taxonomy() === '' && in_array($attribute->get_name(), $jtlVariations)) {
                    unset($attributes[$slug]);
                } else {
                    $filteredAttributes[$slug] = [
                        'id' => $attribute->get_id(),
                        'name' => $attribute->get_name(),
                        'value' => implode(' ' . WC_DELIMITER . ' ', $attribute->get_options()),
                        'position' => $attribute->get_position(),
                        'is_visible' => $attribute->get_visible(),
                        'is_variation' => $attribute->get_variation(),
                        'is_taxonomy' => $attribute->get_taxonomy(),
                    ];
                }
            } elseif (taxonomy_exists($slug)) {
                $filteredAttributes[$slug] =
                    [
                        'id'           => $attribute->get_id(),
                        'name'         => $attribute->get_name(),
                        'value'        => '',
                        'position'     => $attribute->get_position(),
                        'is_visible'   => $attribute->get_visible(),
                        'is_variation' => $attribute->get_variation(),
                        'is_taxonomy'  => $attribute->get_taxonomy(),
                    ];
            }
        }
        
        return $filteredAttributes;
    }
    
    private function getVariationAttributes($curAttributes, ProductAttrModel ...$jtlAttributes)
    {
        $filteredAttributes = [];
        
        /**
         * @var string                $slug
         * @var WC_Product_Attribute $curAttributes
         */
        foreach ($curAttributes as $slug => $wcProductAttribute) {
            if (!$wcProductAttribute->get_variation()) {
                $filteredAttributes[$slug] = [
                    'name'         => $wcProductAttribute->get_name(),
                    'value'        => Util::getInstance()->findAttributeValue($wcProductAttribute,  ...$jtlAttributes),
                    'position'     => $wcProductAttribute->get_position(),
                    'is_visible'   => $wcProductAttribute->get_visible(),
                    'is_variation' => $wcProductAttribute->get_variation(),
                    'is_taxonomy'  => $wcProductAttribute->get_taxonomy(),
                ];
            }
        }
        
        return $filteredAttributes;
    }
    // </editor-fold>
    
    // <editor-fold defaultstate="collapsed" desc="GenerateData Methods">
    private function generateSpecificData($pushedSpecifics = [])
    {
        $specificData = [];
        foreach ($pushedSpecifics as $specific) {
            $endpointId = $specific->getId()->getEndpoint();
            $specificValueEndpointId = $specific->getSpecificValueId()->getEndpoint();
            if (empty($endpointId) || empty($specificValueEndpointId)) {
                continue;
            }
            $specificData[(int)$endpointId]['options'][] = (int)$specificValueEndpointId;
        }
        
        return $specificData;
    }
    
    private function generateVariationSpecificData($pushedVariations = [])
    {
        $variationSpecificData = [];
        foreach ($pushedVariations as $variation) {
            /** @var ProductVariationI18nModel $variationI18n */
            foreach ($variation->getI18ns() as $variationI18n) {
                $taxonomyName = \wc_sanitize_taxonomy_name($variationI18n->getName());
                $customSort = false;
                
                if (!Util::getInstance()->isWooCommerceLanguage($variationI18n->getLanguageISO())) {
                    continue;
                }
                
                $values = [];
                
                $this->values = $variation->getValues();
                
                foreach ($this->values as $vv) {
                    if ($vv->getSort() !== 0) {
                        $customSort = true;
                    }
                }
                
                if ($customSort) {
                    usort($this->values, [
                        $this,
                        'sortI18nValues',
                    ]);
                }
                
                foreach ($this->values as $vv) {
                    /** @var ProductVariationValueI18nModel $valueI18n */
                    foreach ($vv->getI18ns() as $valueI18n) {
                        if (!Util::getInstance()->isWooCommerceLanguage($valueI18n->getLanguageISO())) {
                            continue;
                        }
                        
                        $values[] = $valueI18n->getName();
                    }
                }
                
                $variationSpecificData[$taxonomyName] = [
                    'name'         => $variationI18n->getName(),
                    'value'        => implode(' ' . WC_DELIMITER . ' ', $values),
                    'position'     => $variation->getSort(),
                    'is_visible'   => 0,
                    'is_variation' => 1,
                    'is_taxonomy'  => 0,
                ];
            }
        }

        if (!empty($variationSpecificData)) {
            uasort($variationSpecificData, function ($a, $b) {
                return $a['position'] <=> $b['position'];
            });
        }

        return $variationSpecificData;
    }
    // </editor-fold>
    
    // <editor-fold defaultstate="collapsed" desc="FuncAttr Methods">
    /**
     * @param \WC_Product $product
     * @param string      $languageIso
     */
    private function handleCustomPropertyAttributes(\WC_Product $product, $languageIso = '')
    {
        if (!$product->is_purchasable()) {
            $isPurchasable = false;
            
            if ($product->has_child()) {
                $isPurchasable = true;
                
                foreach ($product->get_children() as $childId) {
                    $child = \wc_get_product($childId);
                    if ($child instanceof \WC_Product) {
                        $isPurchasable &= $child->is_purchasable();
                    }
                }
            }
            
            if (!$isPurchasable) {
                $attrI18n = (new ProductAttrI18nModel)
                    ->setProductAttrId(new Identity(self::PAYABLE_ATTR))
                    ->setLanguageISO($languageIso)
                    ->setName(self::PAYABLE_ATTR)
                    ->setValue(self::VALUE_FALSE);
                
                $this->productData['productAttributes'][] = (new ProductAttrModel)
                    ->setId(new Identity(self::PAYABLE_ATTR))
                    ->setIsCustomProperty(false)
                    ->addI18n($attrI18n);
            }
        }
    }
    
    /**
     * @param \WC_Product $product
     * @param string      $languageIso
     */
    private function setProductFunctionAttributes(
        \WC_Product $product,
        $languageIso = ''
    ) {
        $functionAttributes = [
            $this->getDeliveryTimeFunctionAttribute(
                $product,
                $languageIso
            ),
            $this->getDownloadableFunctionAttribute(
                $product,
                $languageIso
            ),
            $this->getOnlyOneFunctionAttribute(
                $product,
                $languageIso
            ),
            $this->getPayableFunctionAttribute(
                $product,
                $languageIso
            ),
            $this->getVisibilityFunctionAttribute(
                $product,
                $languageIso
            ),
            $this->getVirtualFunctionAttribute(
                $product,
                $languageIso
            ),
            $this->getProductTypeFunctionAttribute(
                $product,
                $languageIso
            ),
            $this->getPurchaseNoteFunctionAttribute(
                $product,
                $languageIso
            ),
        ];
        
        if (SupportedPluginsAlias::isActive(SupportedPluginsAlias::PLUGIN_FB_FOR_WOO)) {
            /*  $functionAttributes[] = $this->getFacebookVisibilityFunctionAttribute($product);*/
            $functionAttributes[] = $this->getFacebookSyncStatusFunctionAttribute(
                $product,
                $languageIso
            );
        }
        if(SupportedPlugins::isActive(SupportedPlugins::PLUGIN_WOOCOMMERCE_GERMANIZED2)){
            $gzdProduct = wc_gzd_get_product($product);
            if($gzdProduct instanceof \WC_GZD_Product && $product->meta_exists('_service')) {
                $functionAttributes[] = $this->getIsServiceFunctionAttribute(
                    $gzdProduct,
                    $languageIso
                );
            }
        }
        
        if (SupportedPlugins::isActive(SupportedPlugins::PLUGIN_GERMAN_MARKET)) {
            $functionAttributes[] = $this->getDigitalFunctionAttribute(
                $product,
                $languageIso
            );
            
            $functionAttributes[] = $this->getSuppressShippingNoticeFunctionAttribute(
                $product,
                $languageIso
            );
            
            $functionAttributes[] = $this->getAltDeliveryNoteFunctionAttribute(
                $product,
                $languageIso
            );
        }
        
        foreach ($functionAttributes as $functionAttribute) {
            $this->productData['productAttributes'][] = $functionAttribute;
        }
    }
    
    private function getDeliveryTimeFunctionAttribute(\WC_Product $product, $languageIso = '')
    {
        $i18n = (new ProductAttrI18nModel)
            ->setProductAttrId(new Identity($product->get_id() . '_' . self::DELIVERY_TIME_ATTR))
            ->setName(self::DELIVERY_TIME_ATTR)
            ->setValue((string)0)
            ->setLanguageISO($languageIso);
        
        $attribute = (new ProductAttrModel)
            ->setId($i18n->getProductAttrId())
            ->setProductId(new Identity($product->get_id()))
            ->setIsCustomProperty(false)
            ->addI18n($i18n);
        
        return $attribute;
    }
    
    private function getDownloadableFunctionAttribute(\WC_Product $product, $languageIso = '')
    {
        $value = $product->is_downloadable() ? self::VALUE_TRUE : self::VALUE_FALSE;
        $i18n = (new ProductAttrI18nModel)
            ->setProductAttrId(new Identity($product->get_id() . '_' . self::DOWNLOADABLE_ATTR))
            ->setName(self::DOWNLOADABLE_ATTR)
            ->setValue((string)$value)
            ->setLanguageISO($languageIso);
        
        $attribute = (new ProductAttrModel)
            ->setId($i18n->getProductAttrId())
            ->setProductId(new Identity($product->get_id()))
            ->setIsCustomProperty(false)
            ->addI18n($i18n);
        
        return $attribute;
    }

    private function getIsServiceFunctionAttribute(\WC_GZD_Product $product, $languageIso = '')
    {
        $value = $product->get_service() === true ? self::VALUE_TRUE : self::VALUE_FALSE;

        $i18n = (new ProductAttrI18nModel)
            ->setProductAttrId(new Identity($product->get_id() .'_'. self::GZD_IS_SERVICE))
            ->setName(self::GZD_IS_SERVICE)
            ->setValue((string)$value)
            ->setLanguageISO($languageIso);

        $attribute = (new ProductAttrModel)
            ->setId($i18n->getProductAttrId())
            ->setProductId(new Identity($product->get_id()))
            ->setIsCustomProperty(false)
            ->addI18n($i18n);

        return $attribute;
    }

    private function getOnlyOneFunctionAttribute(\WC_Product $product, $languageIso = '')
    {
        $value = $product->is_sold_individually() ? self::VALUE_TRUE : self::VALUE_FALSE;
        $i18n = (new ProductAttrI18nModel)
            ->setProductAttrId(new Identity($product->get_id() . '_' . self::PURCHASE_ONLY_ONE_ATTR))
            ->setName(self::PURCHASE_ONLY_ONE_ATTR)
            ->setValue((string)$value)
            ->setLanguageISO($languageIso);
        
        $attribute = (new ProductAttrModel)
            ->setId($i18n->getProductAttrId())
            ->setProductId(new Identity($product->get_id()))
            ->setIsCustomProperty(false)
            ->addI18n($i18n);
        
        return $attribute;
    }
    
    private function getDigitalFunctionAttribute(\WC_Product $product, $languageIso = '')
    {
        $digital = get_post_meta($product->get_id(), '_digital');
        
        if (count($digital) > 0 && strcmp($digital[0], 'yes') === 0) {
            $value = self::VALUE_TRUE;
        } else {
            $value = self::VALUE_FALSE;
        }
        
        $i18n = (new ProductAttrI18nModel)
            ->setProductAttrId(new Identity($product->get_id() . '_' . self::GM_DIGITAL_ATTR))
            ->setName(self::GM_DIGITAL_ATTR)
            ->setValue((string)$value)
            ->setLanguageISO($languageIso);
        
        $attribute = (new ProductAttrModel)
            ->setId($i18n->getProductAttrId())
            ->setProductId(new Identity($product->get_id()))
            ->setIsCustomProperty(false)
            ->addI18n($i18n);
        
        return $attribute;
    }
    
    private function getSuppressShippingNoticeFunctionAttribute(\WC_Product $product, $languageIso = '')
    {
        $value = \get_post_meta($product->get_id(), '_suppress_shipping_notice', true);
        
        if (strcmp($value, 'on') === 0) {
            $value = self::VALUE_TRUE;
        } else {
            $value = self::VALUE_FALSE;
        }
        
        $i18n = (new ProductAttrI18nModel)
            ->setProductAttrId(new Identity($product->get_id() . '_' . self::GM_SUPPRESS_SHIPPPING_NOTICE))
            ->setName(self::GM_SUPPRESS_SHIPPPING_NOTICE)
            ->setValue((string)$value)
            ->setLanguageISO($languageIso);
        
        $attribute = (new ProductAttrModel)
            ->setId($i18n->getProductAttrId())
            ->setProductId(new Identity($product->get_id()))
            ->setIsCustomProperty(false)
            ->addI18n($i18n);
        
        return $attribute;
    }
    
    private function getAltDeliveryNoteFunctionAttribute(\WC_Product $product, $languageIso = '')
    {
        $info = \get_post_meta($product->get_id(), '_alternative_shipping_information', true);
        
        $i18n = (new ProductAttrI18nModel)
            ->setProductAttrId(new Identity($product->get_id() . '_' . self::GM_ALT_DELIVERY_NOTE_ATTR))
            ->setName(self::GM_ALT_DELIVERY_NOTE_ATTR)
            ->setValue((string)$info)
            ->setLanguageISO($languageIso);
        
        $attribute = (new ProductAttrModel)
            ->setId($i18n->getProductAttrId())
            ->setProductId(new Identity($product->get_id()))
            ->setIsCustomProperty(false)
            ->addI18n($i18n);
        
        return $attribute;
    }
    
    private function getPurchaseNoteFunctionAttribute(\WC_Product $product, $languageIso = '')
    {
        $info = \get_post_meta($product->get_id(), '_purchase_note', true);
        
        $i18n = (new ProductAttrI18nModel)
            ->setProductAttrId(new Identity($product->get_id() . '_' . self::PURCHASE_NOTE_ATTR))
            ->setName(self::PURCHASE_NOTE_ATTR)
            ->setValue((string)$info)
            ->setLanguageISO($languageIso);
        
        $attribute = (new ProductAttrModel)
            ->setId($i18n->getProductAttrId())
            ->setProductId(new Identity($product->get_id()))
            ->setIsCustomProperty(false)
            ->addI18n($i18n);
        
        return $attribute;
    }
    
    private function getPayableFunctionAttribute(\WC_Product $product, $languageIso = '')
    {
        $value = strcmp(get_post_status($product->get_id()), 'private') !== 0 ? self::VALUE_TRUE : self::VALUE_FALSE;
        
        $i18n = (new ProductAttrI18nModel)
            ->setProductAttrId(new Identity($product->get_id() . '_' . self::PAYABLE_ATTR))
            ->setName(self::PAYABLE_ATTR)
            ->setValue((string)$value)
            ->setLanguageISO($languageIso);
        
        $attribute = (new ProductAttrModel)
            ->setId($i18n->getProductAttrId())
            ->setProductId(new Identity($product->get_id()))
            ->setIsCustomProperty(false)
            ->addI18n($i18n);
        
        return $attribute;
    }

    private function getVisibilityFunctionAttribute(\WC_Product $product, $languageIso = '')
    {
        $terms = get_the_terms($product->get_id(), 'product_visibility');
        $termNames = is_array($terms) ? wp_list_pluck($terms, 'name') : [];
        $excludeFromSearch = in_array('exclude-from-search', $termNames, true);
        $excludeFromCatalog = in_array('exclude-from-catalog', $termNames, true);

        $visibility = 'visible';
        if ( $excludeFromSearch && $excludeFromCatalog ) {
            $visibility = 'hidden';
        } elseif ( $excludeFromSearch ) {
            $visibility = 'catalog';
        } elseif ( $excludeFromCatalog ) {
            $visibility = 'search';
        }

        $i18n = (new ProductAttrI18nModel)
            ->setProductAttrId(new Identity($product->get_id() . '_' . self::VISIBILITY))
            ->setName(self::VISIBILITY)
            ->setValue($visibility)
            ->setLanguageISO($languageIso);

        return (new ProductAttrModel)
            ->setId($i18n->getProductAttrId())
            ->setProductId(new Identity($product->get_id()))
            ->setIsCustomProperty(false)
            ->addI18n($i18n);
    }
    
    private function getVirtualFunctionAttribute(\WC_Product $product, $languageIso = '')
    {
        $value = $product->is_virtual() ? self::VALUE_TRUE : self::VALUE_FALSE;
        $i18n = (new ProductAttrI18nModel)
            ->setProductAttrId(new Identity($product->get_id() . '_' . self::VIRTUAL_ATTR))
            ->setName(self::VIRTUAL_ATTR)
            ->setValue((string)$value)
            ->setLanguageISO($languageIso);
        
        $attribute = (new ProductAttrModel)
            ->setId($i18n->getProductAttrId())
            ->setProductId(new Identity($product->get_id()))
            ->setIsCustomProperty(false)
            ->addI18n($i18n);
        
        return $attribute;
    }

    private function getProductTypeFunctionAttribute(\WC_Product $product, $languageIso = '')
    {
        $value = $product->get_type();

        $i18n = (new ProductAttrI18nModel)
            ->setProductAttrId(new Identity($product->get_id() . '_' . self::PRODUCT_TYPE_ATTR))
            ->setName(self::PRODUCT_TYPE_ATTR)
            ->setValue((string)$value)
            ->setLanguageISO($languageIso);

        return (new ProductAttrModel)
            ->setId($i18n->getProductAttrId())
            ->setProductId(new Identity($product->get_id()))
            ->setIsCustomProperty(false)
            ->addI18n($i18n);
    }

    private function getFacebookSyncStatusFunctionAttribute(\WC_Product $product, $languageIso = '')
    {
        $value = self::VALUE_FALSE;
        $status = get_post_meta($product->get_id(), 'fb_sync_status');
        
        if (count($status) > 0 && strcmp($status[0], '1') === 0) {
            $value = self::VALUE_TRUE;
        }
        
        $i18n = (new ProductAttrI18nModel)
            ->setProductAttrId(new Identity($product->get_id() . '_' . self::FACEBOOK_SYNC_STATUS_ATTR))
            ->setName(self::FACEBOOK_SYNC_STATUS_ATTR)
            ->setValue((string)$value)
            ->setLanguageISO($languageIso);
        
        $attribute = (new ProductAttrModel)
            ->setId($i18n->getProductAttrId())
            ->setProductId(new Identity($product->get_id()))
            ->setIsCustomProperty(false)
            ->addI18n($i18n);
        
        return $attribute;
    }
    
    // </editor-fold>
    
    //ALL
    public function getSpecificValueId(string $slug, string $value) {
        $val = $this->database->query(SqlHelper::getSpecificValueId($slug, $value));
        
        if (count($val) === 0) {
            $val = $this->database->query(SqlHelper::getSpecificValueIdBySlug($slug, $value));
        }
        
        if (count($val) === 0) {
            $result = (new Identity);
        } else {
            $result = isset($val[0]['endpoint_id'])
            && isset($val[0]['host_id'])
            && !is_null($val[0]['endpoint_id'])
            && !is_null($val[0]['host_id'])
                ? (new Identity)->setEndpoint($val[0]['endpoint_id'])->setHost($val[0]['host_id'])
                : (new Identity)->setEndpoint($val[0]['term_id']);
        }
        
        return $result;
    }
    
    //VARIATIONSPECIFIC && SPECIFIC
    private function sortI18nValues(
        ProductVariationValueModel $a,
        ProductVariationValueModel $b
    ) {
        return ($a->getSort() - $b->getSort());
    }

    /**
     * @param array $newProductAttributes
     * @param array $attributes
     * @param bool $sort
     */
    private function mergeAttributes(array &$newProductAttributes, array $attributes, bool $sort = false)
    {
        foreach ($attributes as $slug => $attr) {
            if (array_key_exists($slug, $newProductAttributes)) {
                if ($attr['name'] === $slug && $attr['name'] === $newProductAttributes[$slug]['name']) {
                    $isVariation = $attr['is_variation'] || $newProductAttributes[$slug]['is_variation'] ? true : false;
                    $attrValues = explode(' ' . WC_DELIMITER . ' ', $attr['value']);
                    $oldValues = explode(' ' . WC_DELIMITER . ' ', $newProductAttributes[$slug]['value']);
                    
                    $values = array_merge($attrValues, $oldValues);
                    
                    $values = array_map("unserialize", array_unique(array_map("serialize", $values)));
                    $valuesString = implode(' ' . WC_DELIMITER . ' ', $values);
                    $newProductAttributes[$slug]['value'] = $valuesString;
                    $newProductAttributes[$slug]['is_variation'] = $isVariation;

                    if ($sort) {
                        $newProductAttributes[$slug]['position'] = $attributes[$slug]['position'];
                    }
                }
            } else {
                $newProductAttributes[$slug] = $attr;
            }
        }
    }
    
}