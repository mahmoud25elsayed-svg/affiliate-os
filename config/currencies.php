<?php

declare(strict_types=1);

/*
 * Currencies a store can sell in.
 *
 * Amounts are always stored as integer hundredths of the currency unit (see App\Support\Money),
 * whatever the ISO minor unit is. `display_decimals` only controls formatting, so a 3-decimal
 * dinar shows as "12.500" and a 0-decimal currency as "12,500".
 */
return [
    // Arab League
    'EGP' => ['name_ar' => 'جنيه مصري', 'name_en' => 'Egyptian Pound', 'symbol_ar' => 'ج.م', 'symbol_en' => 'EGP', 'display_decimals' => 2, 'shipping' => [50, 15, 1000]],
    'SAR' => ['name_ar' => 'ريال سعودي', 'name_en' => 'Saudi Riyal', 'symbol_ar' => 'ر.س', 'symbol_en' => 'SAR', 'display_decimals' => 2, 'shipping' => [25, 10, 300]],
    'AED' => ['name_ar' => 'درهم إماراتي', 'name_en' => 'UAE Dirham', 'symbol_ar' => 'د.إ', 'symbol_en' => 'AED', 'display_decimals' => 2, 'shipping' => [25, 10, 300]],
    'KWD' => ['name_ar' => 'دينار كويتي', 'name_en' => 'Kuwaiti Dinar', 'symbol_ar' => 'د.ك', 'symbol_en' => 'KWD', 'display_decimals' => 3, 'shipping' => [2, 1, 30]],
    'QAR' => ['name_ar' => 'ريال قطري', 'name_en' => 'Qatari Riyal', 'symbol_ar' => 'ر.ق', 'symbol_en' => 'QAR', 'display_decimals' => 2, 'shipping' => [25, 10, 300]],
    'BHD' => ['name_ar' => 'دينار بحريني', 'name_en' => 'Bahraini Dinar', 'symbol_ar' => 'د.ب', 'symbol_en' => 'BHD', 'display_decimals' => 3, 'shipping' => [2, 1, 30]],
    'OMR' => ['name_ar' => 'ريال عماني', 'name_en' => 'Omani Rial', 'symbol_ar' => 'ر.ع', 'symbol_en' => 'OMR', 'display_decimals' => 3, 'shipping' => [2, 1, 30]],
    'JOD' => ['name_ar' => 'دينار أردني', 'name_en' => 'Jordanian Dinar', 'symbol_ar' => 'د.أ', 'symbol_en' => 'JOD', 'display_decimals' => 3, 'shipping' => [3, 1, 50]],
    'IQD' => ['name_ar' => 'دينار عراقي', 'name_en' => 'Iraqi Dinar', 'symbol_ar' => 'د.ع', 'symbol_en' => 'IQD', 'display_decimals' => 0, 'shipping' => [5000, 2000, 100000]],
    'LBP' => ['name_ar' => 'ليرة لبنانية', 'name_en' => 'Lebanese Pound', 'symbol_ar' => 'ل.ل', 'symbol_en' => 'LBP', 'display_decimals' => 0, 'shipping' => [300000, 100000, 5000000]],
    'SYP' => ['name_ar' => 'ليرة سورية', 'name_en' => 'Syrian Pound', 'symbol_ar' => 'ل.س', 'symbol_en' => 'SYP', 'display_decimals' => 0, 'shipping' => [25000, 10000, 1000000]],
    'YER' => ['name_ar' => 'ريال يمني', 'name_en' => 'Yemeni Rial', 'symbol_ar' => 'ر.ي', 'symbol_en' => 'YER', 'display_decimals' => 0, 'shipping' => [3000, 1000, 100000]],
    'ILS' => ['name_ar' => 'شيكل', 'name_en' => 'Israeli New Shekel', 'symbol_ar' => '₪', 'symbol_en' => '₪', 'display_decimals' => 2, 'shipping' => [25, 10, 300]],
    'LYD' => ['name_ar' => 'دينار ليبي', 'name_en' => 'Libyan Dinar', 'symbol_ar' => 'د.ل', 'symbol_en' => 'LYD', 'display_decimals' => 3, 'shipping' => [15, 5, 300]],
    'TND' => ['name_ar' => 'دينار تونسي', 'name_en' => 'Tunisian Dinar', 'symbol_ar' => 'د.ت', 'symbol_en' => 'TND', 'display_decimals' => 3, 'shipping' => [8, 3, 150]],
    'DZD' => ['name_ar' => 'دينار جزائري', 'name_en' => 'Algerian Dinar', 'symbol_ar' => 'د.ج', 'symbol_en' => 'DZD', 'display_decimals' => 2, 'shipping' => [600, 200, 10000]],
    'MAD' => ['name_ar' => 'درهم مغربي', 'name_en' => 'Moroccan Dirham', 'symbol_ar' => 'د.م', 'symbol_en' => 'MAD', 'display_decimals' => 2, 'shipping' => [35, 10, 500]],
    'SDG' => ['name_ar' => 'جنيه سوداني', 'name_en' => 'Sudanese Pound', 'symbol_ar' => 'ج.س', 'symbol_en' => 'SDG', 'display_decimals' => 2, 'shipping' => [3000, 1000, 100000]],
    'SOS' => ['name_ar' => 'شلن صومالي', 'name_en' => 'Somali Shilling', 'symbol_ar' => 'ش.ص', 'symbol_en' => 'SOS', 'display_decimals' => 0, 'shipping' => [3000, 1000, 150000]],
    'DJF' => ['name_ar' => 'فرنك جيبوتي', 'name_en' => 'Djiboutian Franc', 'symbol_ar' => 'ف.ج', 'symbol_en' => 'DJF', 'display_decimals' => 0, 'shipping' => [1000, 300, 20000]],
    'KMF' => ['name_ar' => 'فرنك قمري', 'name_en' => 'Comorian Franc', 'symbol_ar' => 'ف.ق', 'symbol_en' => 'KMF', 'display_decimals' => 0, 'shipping' => [2000, 500, 50000]],
    'MRU' => ['name_ar' => 'أوقية موريتانية', 'name_en' => 'Mauritanian Ouguiya', 'symbol_ar' => 'أ.م', 'symbol_en' => 'MRU', 'display_decimals' => 2, 'shipping' => [200, 50, 5000]],

    // International
    'USD' => ['name_ar' => 'دولار أمريكي', 'name_en' => 'US Dollar', 'symbol_ar' => '$', 'symbol_en' => '$', 'display_decimals' => 2, 'shipping' => [8, 3, 100]],
    'GBP' => ['name_ar' => 'جنيه إسترليني', 'name_en' => 'British Pound', 'symbol_ar' => '£', 'symbol_en' => '£', 'display_decimals' => 2, 'shipping' => [5, 2, 75]],
    'EUR' => ['name_ar' => 'يورو', 'name_en' => 'Euro', 'symbol_ar' => '€', 'symbol_en' => '€', 'display_decimals' => 2, 'shipping' => [6, 2, 80]],
    'CAD' => ['name_ar' => 'دولار كندي', 'name_en' => 'Canadian Dollar', 'symbol_ar' => 'CA$', 'symbol_en' => 'CA$', 'display_decimals' => 2, 'shipping' => [10, 3, 120]],
    'TRY' => ['name_ar' => 'ليرة تركية', 'name_en' => 'Turkish Lira', 'symbol_ar' => '₺', 'symbol_en' => '₺', 'display_decimals' => 2, 'shipping' => [150, 50, 2000]],
    'AUD' => ['name_ar' => 'دولار أسترالي', 'name_en' => 'Australian Dollar', 'symbol_ar' => 'A$', 'symbol_en' => 'A$', 'display_decimals' => 2, 'shipping' => [10, 3, 120]],
];
