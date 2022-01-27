<?php
/**
 * Created by PhpStorm.
 * User: Jan Weskamp <jan.weskamp@jtl-software.com>
 * Date: 07.11.2018
 * Time: 10:55
 */

namespace JtlWooCommerceConnector\Utilities\SqlTraits;


use jtl\Connector\Model\TaxRate;

trait TaxesTrait {
	public static function taxClassByRate( $rate ) {
		global $wpdb;
		$wtr = $wpdb->prefix . 'woocommerce_tax_rates';
		
		return sprintf( "
            SELECT tax_rate_class
            FROM {$wtr}
            WHERE tax_rate = '%s'",
			number_format( $rate, 4 )
		);
	}

    /**
     * @param TaxRate ...$taxRates
     * @return string
     */
	public static function getTaxClassByTaxRates(TaxRate ...$taxRates): string
    {
        global $wpdb;

        $conditions = [];
        foreach($taxRates as $taxRate){
            $conditions[] = sprintf("(tax_rate_country = '%s' AND tax_rate='%s')", $taxRate->getCountryIso(), number_format($taxRate->getRate(), 4));
        }

        return sprintf(
            'SELECT DISTINCT tax_rate_class AS taxClassName, COUNT(tax_rate_class) AS hits 
                FROM %swoocommerce_tax_rates 
                WHERE %s 
                GROUP BY tax_rate_class 
                ORDER BY hits DESC', $wpdb->prefix, join(' OR ', $conditions)
        );
    }

	public static function taxRateById( $taxRateId ) {
		global $wpdb;
		$wtr = $wpdb->prefix . 'woocommerce_tax_rates';
		
		return "SELECT tax_rate FROM {$wtr} WHERE tax_rate_id = {$taxRateId}";
	}
	
	public static function getAllTaxRates() {
		global $wpdb;
		$wtr = $wpdb->prefix . 'woocommerce_tax_rates';
		
		return "SELECT tax_rate_country, tax_rate, tax_rate_class FROM {$wtr} ORDER BY tax_rate DESC";
	}
}