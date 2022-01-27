<?php
/**
 * @author    Jan Weskamp <jan.weskamp@jtl-software.com>
 * @copyright 2010-2013 JTL-Software GmbH
 */

namespace JtlWooCommerceConnector\Utilities;

final class SupportedPlugins
{
    //THEMESPECIALS
    
    //Compatible
    const PLUGIN_B2B_MARKET = 'B2B Market';
    const PLUGIN_GERMAN_MARKET = 'German Market';
    const PLUGIN_PERFECT_WOO_BRANDS = 'Perfect WooCommerce Brands';
    const PLUGIN_PERFECT_BRANDS_FOR_WOOCOMMERCE = 'Perfect Brands for WooCommerce';
    const PLUGIN_FB_FOR_WOO = 'Facebook for WooCommerce';
    const PLUGIN_WOOCOMMERCE = 'WooCommerce';
    const PLUGIN_WOOCOMMERCE_GERMANIZED = 'WooCommerce Germanized';
    const PLUGIN_WOOCOMMERCE_GERMANIZED2 = 'Germanized for WooCommerce';
    const PLUGIN_WOOCOMMERCE_GERMANIZEDPRO = 'Germanized for WooCommerce Pro'; //TODO: CHECK THAT
    const PLUGIN_WOOCOMMERCE_BLOCKS = 'WooCommerce Blocks';
    const PLUGIN_ATOMION_WOOCOMMERCE_BLOCKS = 'Atomion WooCommerce Blocks';
    const PLUGIN_WOOF_WC_PRODUCT_FILTER = 'WOOF - WooCommerce Products Filter';
    const PLUGIN_YOAST_SEO = 'Yoast SEO';
    const PLUGIN_YOAST_SEO_PREMIUM = 'Yoast SEO Premium';
    const PLUGIN_ADVANCED_SHIPMENT_TRACKING_FOR_WOOCOMMERCE = 'Advanced Shipment Tracking for WooCommerce';
    const PLUGIN_ADVANCED_SHIPMENT_TRACKING_PRO = 'Advanced Shipment Tracking Pro';
    const PLUGIN_DHL_FOR_WOOCOMMERCE = 'DHL for WooCommerce';
    const PLUGIN_BACKUPBUDDY = 'BackupBuddy';
    const PLUGIN_UPDRAFTPLUS_BACKUP_RESTORE = 'UpdraftPlus - Backup/Restore';
    const PLUGIN_VR_PAY_ECOMMERCE_WOOCOMMERCE = 'VR pay eCommerce - WooCommerce';
    const PLUGIN_WPC_PRODUCT_QUANTITY_FOR_WOOCOMMERCE = 'WPC Product Quantity for WooCommerce';
    const PLUGIN_WPC_PRODUCT_QUANTITY_FOR_WOOCOMMERCE_PREMIUM = 'WPC Product Quantity for WooCommerce (Premium)';
    const PLUGIN_ADDITIONAL_VARIATION_IMAGES_GALLERY_FOR_WOOCOMMERCE = 'Additional Variation Images Gallery for WooCommerce';
    const PLUGIN_RANK_MATH_SEO = 'Rank Math SEO';

    //Incompatible
    const PLUGIN_ANTISPAM_BEE = 'Antispam Bee';
    const PLUGIN_CERBER_SECURITY = 'Cerber Security, Antispam & Malware Scan';
//    const PLUGIN_SMUSH = 'Smush Image Compression and Optimization'; removed from incompatible list in 1.28.0
    const PLUGIN_WORDFENCE = 'Wordfence Security – Firewall & Malware Scan';
    const PLUGIN_THEME_WOODMART_CORE = 'Woodmart Core';
    const PLUGIN_WP_FASTEST_CACHE = 'WP Fastest Cache';
    const PLUGIN_WP_MULTILANG = 'WP Multilang';
    const PLUGIN_WOODY_AD_SNIPPET = 'Woody ad snippets (PHP snippets | Insert PHP)';
    CONST PLUGIN_SCHEMA_ALL_IN_ONE_SNIPPET = 'Schema - All In One Schema Rich Snippets';
    CONST PLUGIN_BACKWPUP = 'BackWPup';

    //arrays
    const SUPPORTED_PLUGINS = [
        self::PLUGIN_PERFECT_WOO_BRANDS,
        self::PLUGIN_PERFECT_BRANDS_FOR_WOOCOMMERCE,
        self::PLUGIN_B2B_MARKET,
        self::PLUGIN_GERMAN_MARKET,
        self::PLUGIN_FB_FOR_WOO,
        self::PLUGIN_WOOCOMMERCE,
        self::PLUGIN_WOOCOMMERCE_GERMANIZED,
        self::PLUGIN_WOOCOMMERCE_GERMANIZED2,
        self::PLUGIN_WOOCOMMERCE_GERMANIZEDPRO,
        self::PLUGIN_WOOCOMMERCE_BLOCKS,
        self::PLUGIN_ATOMION_WOOCOMMERCE_BLOCKS,
        self::PLUGIN_WOOF_WC_PRODUCT_FILTER,
        self::PLUGIN_YOAST_SEO,
        self::PLUGIN_YOAST_SEO_PREMIUM,
        self::PLUGIN_ADVANCED_SHIPMENT_TRACKING_FOR_WOOCOMMERCE,
        self::PLUGIN_ADVANCED_SHIPMENT_TRACKING_PRO,
        self::PLUGIN_DHL_FOR_WOOCOMMERCE,
        self::PLUGIN_UPDRAFTPLUS_BACKUP_RESTORE,
        self::PLUGIN_BACKUPBUDDY,
        self::PLUGIN_VR_PAY_ECOMMERCE_WOOCOMMERCE,
        self::PLUGIN_WPC_PRODUCT_QUANTITY_FOR_WOOCOMMERCE,
        self::PLUGIN_WPC_PRODUCT_QUANTITY_FOR_WOOCOMMERCE_PREMIUM,
        self::PLUGIN_ADDITIONAL_VARIATION_IMAGES_GALLERY_FOR_WOOCOMMERCE,
        self::PLUGIN_RANK_MATH_SEO,
    ];

    const INCOMPATIBLE_PLUGINS = [
        self::PLUGIN_ANTISPAM_BEE,
        self::PLUGIN_CERBER_SECURITY,
//        self::PLUGIN_SMUSH,
        self::PLUGIN_WORDFENCE,
        self::PLUGIN_WP_FASTEST_CACHE,
        self::PLUGIN_WP_MULTILANG,
        self::PLUGIN_THEME_WOODMART_CORE,
        self::PLUGIN_WOODY_AD_SNIPPET,
        self::PLUGIN_SCHEMA_ALL_IN_ONE_SNIPPET,
        self::PLUGIN_BACKWPUP,
    ];

    /**
     * Returns all active and validated plugins
     *
     * @return array
     */
    public static function getInstalledAndActivated()
    {
        $plugins = get_plugins();
        $plArr = [];

        foreach (wp_get_active_and_valid_plugins() as $activePl) {
            $tmp = explode('/', $activePl);
            $count = count($tmp) - 1;

            $string = '';

            if (strcmp('plugins', $tmp[$count - 1]) !== 0) {
                $string .= (string)$tmp[$count - 1];
                $string .= '/';
            }

            $string .= (string)$tmp[$count];

            if (array_key_exists($string, $plugins)) {
                $plArr[] = $plugins[$string];
            }

        }

        return $plArr;
    }

    /**
     * Returns all supported active and validated plugins
     *
     * @param bool $asString
     *
     * @return array|string
     */
    public static function getSupported($asString = false)
    {
        $plArray = self::getInstalledAndActivated();
        $plugins = [];
        $tmp = [];
        foreach ($plArray as $plugin) {
            if (array_search($plugin['Name'], self::SUPPORTED_PLUGINS) !== false) {
                $plugins[] = $plugin;
                $tmp[] = $plugin['Name'];
            }
        }

        if ($asString) {
            return implode(', ', $tmp);
        } else {
            return $plugins;
        }
    }

    /**
     * Returns all not supported active plugins or all not supported plugins
     *
     * @param bool $asString
     * @param bool $all
     *
     * @return array|string
     */
    public static function getNotSupportedButActive($asString = false, $all = false, $asArray = false)
    {
        $plArray = self::getInstalledAndActivated();
        $plugins = [];
        $tmp = [];
        foreach ($plArray as $plugin) {
            if (array_search($plugin['Name'], self::INCOMPATIBLE_PLUGINS) !== false) {
                $plugins[] = $plugin;
                $tmp[] = $plugin['Name'];
            }
        }

        if ($asString) {
            if ($all) {
                return implode(', ', self::INCOMPATIBLE_PLUGINS);
            } else {
                return implode(', ', $tmp);
            }
        } else {
            if ($all && $asArray) {
                return self::INCOMPATIBLE_PLUGINS;
            }

            return $plugins;
        }
    }

    /**
     * Check if Special PLugin is Installed
     *
     * @param string $pluginName
     *
     * @return bool
     */
    public static function isActive($pluginName = 'WooCommerce')
    {
        $plArray = self::getInstalledAndActivated();
        $active = false;

        foreach ($plArray as $plugin) {
            if (strcmp($pluginName, $plugin['Name']) === 0) {
                $active = true;
            }
        }

        return $active;
    }

    /**
     * @param string $pluginName
     * @param string $operator
     * @param string $version
     * @return bool
     */
    public static function comparePluginVersion(string $pluginName, string $operator, string $version): bool
    {
        return self::isActive($pluginName) && version_compare(self::getVersionOf($pluginName), $version, $operator);
    }

    /**
     * @return bool
     */
    public static function isPerfectWooCommerceBrandsActive()
    {
        return (
            self::isActive(self::PLUGIN_PERFECT_WOO_BRANDS) ||
            self::isActive(self::PLUGIN_PERFECT_BRANDS_FOR_WOOCOMMERCE)
        );
    }

    /**
     * @param string $pluginName
     * @return string|null
     */
    public static function getVersionOf($pluginName = 'WooCommerce'): ?string
    {
        $plArray = self::getInstalledAndActivated();

        $version = null;
        foreach ($plArray as $plugin) {
            if (strcmp($pluginName, $plugin['Name']) === 0) {
                $version = $plugin['Version'];
                break;
            }
        }

        return $version;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public static function themeIsInstalled($name = '')
    {
        $installed = false;
        $themes = wp_get_themes();

        if (is_array($themes)) {
            $installed = array_key_exists((string)$name, $themes);
        }

        return $installed;
    }
}
