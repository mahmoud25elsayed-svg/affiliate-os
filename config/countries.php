<?php

declare(strict_types=1);

/*
 * Countries a store can be based in or ship to.
 *
 * Phone rules work on the national significant number (NSN): the digits after the country
 * calling code, without the domestic trunk prefix. `trunk` is the prefix people type locally
 * ("0" in most countries, "1" for NANP, "" where none is used). `mobile` must match the whole
 * NSN of a mobile number, because cash-on-delivery couriers call and message mobiles.
 */
return [
    // ---- Arab League ----
    'EG' => ['name_ar' => 'مصر', 'name_en' => 'Egypt', 'dial' => '20', 'currency' => 'EGP', 'trunk' => '0', 'mobile' => '1[0125]\d{8}', 'example' => '01012345678', 'arab' => true],
    'SA' => ['name_ar' => 'السعودية', 'name_en' => 'Saudi Arabia', 'dial' => '966', 'currency' => 'SAR', 'trunk' => '0', 'mobile' => '5\d{8}', 'example' => '0512345678', 'arab' => true],
    'AE' => ['name_ar' => 'الإمارات', 'name_en' => 'United Arab Emirates', 'dial' => '971', 'currency' => 'AED', 'trunk' => '0', 'mobile' => '5[024568]\d{7}', 'example' => '0501234567', 'arab' => true],
    'KW' => ['name_ar' => 'الكويت', 'name_en' => 'Kuwait', 'dial' => '965', 'currency' => 'KWD', 'trunk' => '', 'mobile' => '[4569]\d{7}', 'example' => '50012345', 'arab' => true],
    'QA' => ['name_ar' => 'قطر', 'name_en' => 'Qatar', 'dial' => '974', 'currency' => 'QAR', 'trunk' => '', 'mobile' => '[3567]\d{7}', 'example' => '33123456', 'arab' => true],
    'BH' => ['name_ar' => 'البحرين', 'name_en' => 'Bahrain', 'dial' => '973', 'currency' => 'BHD', 'trunk' => '', 'mobile' => '[36]\d{7}', 'example' => '36001234', 'arab' => true],
    'OM' => ['name_ar' => 'عُمان', 'name_en' => 'Oman', 'dial' => '968', 'currency' => 'OMR', 'trunk' => '', 'mobile' => '[79]\d{7}', 'example' => '92123456', 'arab' => true],
    'JO' => ['name_ar' => 'الأردن', 'name_en' => 'Jordan', 'dial' => '962', 'currency' => 'JOD', 'trunk' => '0', 'mobile' => '7[789]\d{7}', 'example' => '0790123456', 'arab' => true],
    'IQ' => ['name_ar' => 'العراق', 'name_en' => 'Iraq', 'dial' => '964', 'currency' => 'IQD', 'trunk' => '0', 'mobile' => '7[3-9]\d{8}', 'example' => '07901234567', 'arab' => true],
    'LB' => ['name_ar' => 'لبنان', 'name_en' => 'Lebanon', 'dial' => '961', 'currency' => 'LBP', 'trunk' => '0', 'mobile' => '(3\d{6}|7[01689]\d{6}|81\d{6})', 'example' => '03123456', 'arab' => true],
    'SY' => ['name_ar' => 'سوريا', 'name_en' => 'Syria', 'dial' => '963', 'currency' => 'SYP', 'trunk' => '0', 'mobile' => '9\d{8}', 'example' => '0944123456', 'arab' => true],
    'YE' => ['name_ar' => 'اليمن', 'name_en' => 'Yemen', 'dial' => '967', 'currency' => 'YER', 'trunk' => '0', 'mobile' => '7[01378]\d{7}', 'example' => '0712345678', 'arab' => true],
    'PS' => ['name_ar' => 'فلسطين', 'name_en' => 'Palestine', 'dial' => '970', 'currency' => 'ILS', 'trunk' => '0', 'mobile' => '5[69]\d{7}', 'example' => '0599123456', 'arab' => true],
    'LY' => ['name_ar' => 'ليبيا', 'name_en' => 'Libya', 'dial' => '218', 'currency' => 'LYD', 'trunk' => '0', 'mobile' => '9[1-6]\d{7}', 'example' => '0912345678', 'arab' => true],
    'TN' => ['name_ar' => 'تونس', 'name_en' => 'Tunisia', 'dial' => '216', 'currency' => 'TND', 'trunk' => '', 'mobile' => '[2459]\d{7}', 'example' => '20123456', 'arab' => true],
    'DZ' => ['name_ar' => 'الجزائر', 'name_en' => 'Algeria', 'dial' => '213', 'currency' => 'DZD', 'trunk' => '0', 'mobile' => '[567]\d{8}', 'example' => '0551234567', 'arab' => true],
    'MA' => ['name_ar' => 'المغرب', 'name_en' => 'Morocco', 'dial' => '212', 'currency' => 'MAD', 'trunk' => '0', 'mobile' => '[67]\d{8}', 'example' => '0612345678', 'arab' => true],
    'SD' => ['name_ar' => 'السودان', 'name_en' => 'Sudan', 'dial' => '249', 'currency' => 'SDG', 'trunk' => '0', 'mobile' => '[19]\d{8}', 'example' => '0912345678', 'arab' => true],
    'SO' => ['name_ar' => 'الصومال', 'name_en' => 'Somalia', 'dial' => '252', 'currency' => 'SOS', 'trunk' => '0', 'mobile' => '([67]\d{7,8}|9\d{8})', 'example' => '0612345678', 'arab' => true],
    'DJ' => ['name_ar' => 'جيبوتي', 'name_en' => 'Djibouti', 'dial' => '253', 'currency' => 'DJF', 'trunk' => '', 'mobile' => '77\d{6}', 'example' => '77831001', 'arab' => true],
    'KM' => ['name_ar' => 'جزر القمر', 'name_en' => 'Comoros', 'dial' => '269', 'currency' => 'KMF', 'trunk' => '', 'mobile' => '[34]\d{6}', 'example' => '3212345', 'arab' => true],
    'MR' => ['name_ar' => 'موريتانيا', 'name_en' => 'Mauritania', 'dial' => '222', 'currency' => 'MRU', 'trunk' => '', 'mobile' => '[234]\d{7}', 'example' => '22123456', 'arab' => true],

    // ---- International ----
    'US' => ['name_ar' => 'الولايات المتحدة', 'name_en' => 'United States', 'dial' => '1', 'currency' => 'USD', 'trunk' => '1', 'mobile' => '[2-9]\d{2}[2-9]\d{6}', 'example' => '2015550123', 'arab' => false],
    'GB' => ['name_ar' => 'المملكة المتحدة', 'name_en' => 'United Kingdom', 'dial' => '44', 'currency' => 'GBP', 'trunk' => '0', 'mobile' => '7\d{9}', 'example' => '07400123456', 'arab' => false],
    'CA' => ['name_ar' => 'كندا', 'name_en' => 'Canada', 'dial' => '1', 'currency' => 'CAD', 'trunk' => '1', 'mobile' => '[2-9]\d{2}[2-9]\d{6}', 'example' => '5062345678', 'arab' => false],
    'DE' => ['name_ar' => 'ألمانيا', 'name_en' => 'Germany', 'dial' => '49', 'currency' => 'EUR', 'trunk' => '0', 'mobile' => '1[5-7]\d{8,9}', 'example' => '015123456789', 'arab' => false],
    'FR' => ['name_ar' => 'فرنسا', 'name_en' => 'France', 'dial' => '33', 'currency' => 'EUR', 'trunk' => '0', 'mobile' => '[67]\d{8}', 'example' => '0612345678', 'arab' => false],
    'IT' => ['name_ar' => 'إيطاليا', 'name_en' => 'Italy', 'dial' => '39', 'currency' => 'EUR', 'trunk' => '', 'mobile' => '3\d{8,9}', 'example' => '3123456789', 'arab' => false],
    'ES' => ['name_ar' => 'إسبانيا', 'name_en' => 'Spain', 'dial' => '34', 'currency' => 'EUR', 'trunk' => '', 'mobile' => '[67]\d{8}', 'example' => '612345678', 'arab' => false],
    'NL' => ['name_ar' => 'هولندا', 'name_en' => 'Netherlands', 'dial' => '31', 'currency' => 'EUR', 'trunk' => '0', 'mobile' => '6\d{8}', 'example' => '0612345678', 'arab' => false],
    'TR' => ['name_ar' => 'تركيا', 'name_en' => 'Türkiye', 'dial' => '90', 'currency' => 'TRY', 'trunk' => '0', 'mobile' => '5\d{9}', 'example' => '05012345678', 'arab' => false],
    'AU' => ['name_ar' => 'أستراليا', 'name_en' => 'Australia', 'dial' => '61', 'currency' => 'AUD', 'trunk' => '0', 'mobile' => '4\d{8}', 'example' => '0412345678', 'arab' => false],
];
