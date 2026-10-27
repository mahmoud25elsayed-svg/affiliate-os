<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Location\City;
use App\Support\Location\Governorate;
use Illuminate\Database\Seeder;

class EgyptGovernoratesSeeder extends Seeder
{
    public function run(): void
    {
        $governorates = [
            ['code' => 'CAI', 'name_ar' => 'القاهرة', 'name_en' => 'Cairo', 'cities' => [
                ['name_ar' => 'مدينة نصر', 'name_en' => 'Nasr City'],
                ['name_ar' => 'مصر الجديدة', 'name_en' => 'Heliopolis'],
                ['name_ar' => 'المعادي', 'name_en' => 'Maadi'],
                ['name_ar' => 'التجمع الخامس', 'name_en' => 'Fifth Settlement'],
                ['name_ar' => 'وسط البلد', 'name_en' => 'Downtown'],
                ['name_ar' => 'شبرا', 'name_en' => 'Shubra'],
                ['name_ar' => 'حلوان', 'name_en' => 'Helwan'],
            ]],
            ['code' => 'GIZ', 'name_ar' => 'الجيزة', 'name_en' => 'Giza', 'cities' => [
                ['name_ar' => 'الدقي', 'name_en' => 'Dokki'],
                ['name_ar' => 'المهندسين', 'name_en' => 'Mohandessin'],
                ['name_ar' => 'الهرم', 'name_en' => 'Haram'],
                ['name_ar' => 'فيصل', 'name_en' => 'Faisal'],
                ['name_ar' => 'مدينة 6 أكتوبر', 'name_en' => '6th of October'],
                ['name_ar' => 'الشيخ زايد', 'name_en' => 'Sheikh Zayed'],
            ]],
            ['code' => 'ALX', 'name_ar' => 'الإسكندرية', 'name_en' => 'Alexandria', 'cities' => [
                ['name_ar' => 'سموحة', 'name_en' => 'Smouha'],
                ['name_ar' => 'سيدي جابر', 'name_en' => 'Sidi Gaber'],
                ['name_ar' => 'ميامي', 'name_en' => 'Miami'],
                ['name_ar' => 'محرم بك', 'name_en' => 'Moharam Bek'],
                ['name_ar' => 'العجمي', 'name_en' => 'Agami'],
                ['name_ar' => 'برج العرب', 'name_en' => 'Borg El Arab'],
            ]],
            ['code' => 'QAL', 'name_ar' => 'القليوبية', 'name_en' => 'Qalyubia', 'cities' => [
                ['name_ar' => 'بنها', 'name_en' => 'Banha'],
                ['name_ar' => 'شبرا الخيمة', 'name_en' => 'Shubra El Kheima'],
                ['name_ar' => 'قليوب', 'name_en' => 'Qalyub'],
                ['name_ar' => 'العبور', 'name_en' => 'Obour'],
            ]],
            ['code' => 'SHQ', 'name_ar' => 'الشرقية', 'name_en' => 'Sharqia', 'cities' => [
                ['name_ar' => 'الزقازيق', 'name_en' => 'Zagazig'],
                ['name_ar' => 'العاشر من رمضان', 'name_en' => '10th of Ramadan'],
                ['name_ar' => 'بلبيس', 'name_en' => 'Belbeis'],
            ]],
            ['code' => 'DAK', 'name_ar' => 'الدقهلية', 'name_en' => 'Dakahlia', 'cities' => [
                ['name_ar' => 'المنصورة', 'name_en' => 'Mansoura'],
                ['name_ar' => 'ميت غمر', 'name_en' => 'Mit Ghamr'],
                ['name_ar' => 'طلخا', 'name_en' => 'Talkha'],
            ]],
            ['code' => 'BEH', 'name_ar' => 'البحيرة', 'name_en' => 'Beheira', 'cities' => [
                ['name_ar' => 'دمنهور', 'name_en' => 'Damanhur'],
                ['name_ar' => 'كفر الدوار', 'name_en' => 'Kafr El Dawwar'],
                ['name_ar' => 'إيتاي البارود', 'name_en' => 'Itay El Baroud'],
            ]],
            ['code' => 'GHR', 'name_ar' => 'الغربية', 'name_en' => 'Gharbia', 'cities' => [
                ['name_ar' => 'طنطا', 'name_en' => 'Tanta'],
                ['name_ar' => 'المحلة الكبرى', 'name_en' => 'El Mahalla El Kubra'],
                ['name_ar' => 'زفتى', 'name_en' => 'Zefta'],
            ]],
            ['code' => 'MNF', 'name_ar' => 'المنوفية', 'name_en' => 'Menoufia', 'cities' => [
                ['name_ar' => 'شبين الكوم', 'name_en' => 'Shibin El Kom'],
                ['name_ar' => 'مدينة السادات', 'name_en' => 'Sadat City'],
                ['name_ar' => 'منوف', 'name_en' => 'Menouf'],
            ]],
            ['code' => 'KFS', 'name_ar' => 'كفر الشيخ', 'name_en' => 'Kafr El Sheikh', 'cities' => [
                ['name_ar' => 'كفر الشيخ', 'name_en' => 'Kafr El Sheikh'],
                ['name_ar' => 'دسوق', 'name_en' => 'Desouk'],
            ]],
            ['code' => 'DAM', 'name_ar' => 'دمياط', 'name_en' => 'Damietta', 'cities' => [
                ['name_ar' => 'دمياط', 'name_en' => 'Damietta'],
                ['name_ar' => 'دمياط الجديدة', 'name_en' => 'New Damietta'],
                ['name_ar' => 'رأس البر', 'name_en' => 'Ras El Bar'],
            ]],
            ['code' => 'PTS', 'name_ar' => 'بورسعيد', 'name_en' => 'Port Said', 'cities' => [
                ['name_ar' => 'بورسعيد', 'name_en' => 'Port Said'],
                ['name_ar' => 'بورفؤاد', 'name_en' => 'Port Fouad'],
            ]],
            ['code' => 'ISM', 'name_ar' => 'الإسماعيلية', 'name_en' => 'Ismailia', 'cities' => [
                ['name_ar' => 'الإسماعيلية', 'name_en' => 'Ismailia'],
                ['name_ar' => 'فايد', 'name_en' => 'Fayed'],
            ]],
            ['code' => 'SUZ', 'name_ar' => 'السويس', 'name_en' => 'Suez', 'cities' => [
                ['name_ar' => 'السويس', 'name_en' => 'Suez'],
                ['name_ar' => 'العين السخنة', 'name_en' => 'Ain Sokhna'],
            ]],
            ['code' => 'SIN', 'name_ar' => 'شمال سيناء', 'name_en' => 'North Sinai', 'cities' => [
                ['name_ar' => 'العريش', 'name_en' => 'Arish'],
                ['name_ar' => 'بئر العبد', 'name_en' => 'Bir al-Abed'],
            ]],
            ['code' => 'SIS', 'name_ar' => 'جنوب سيناء', 'name_en' => 'South Sinai', 'cities' => [
                ['name_ar' => 'شرم الشيخ', 'name_en' => 'Sharm El Sheikh'],
                ['name_ar' => 'دهب', 'name_en' => 'Dahab'],
                ['name_ar' => 'نويبع', 'name_en' => 'Nuweiba'],
                ['name_ar' => 'طور سيناء', 'name_en' => 'El Tor'],
            ]],
            ['code' => 'RED', 'name_ar' => 'البحر الأحمر', 'name_en' => 'Red Sea', 'cities' => [
                ['name_ar' => 'الغردقة', 'name_en' => 'Hurghada'],
                ['name_ar' => 'الجونة', 'name_en' => 'El Gouna'],
                ['name_ar' => 'سفاجا', 'name_en' => 'Safaga'],
                ['name_ar' => 'مرسى علم', 'name_en' => 'Marsa Alam'],
            ]],
            ['code' => 'MTR', 'name_ar' => 'مطروح', 'name_en' => 'Matrouh', 'cities' => [
                ['name_ar' => 'مرسى مطروح', 'name_en' => 'Marsa Matrouh'],
                ['name_ar' => 'الساحل الشمالي', 'name_en' => 'North Coast'],
                ['name_ar' => 'العلمين', 'name_en' => 'El Alamein'],
                ['name_ar' => 'سيوة', 'name_en' => 'Siwa'],
            ]],
            ['code' => 'WAD', 'name_ar' => 'الوادي الجديد', 'name_en' => 'New Valley', 'cities' => [
                ['name_ar' => 'الخارجة', 'name_en' => 'Kharga'],
                ['name_ar' => 'الداخلة', 'name_en' => 'Dakhla'],
            ]],
            ['code' => 'FYM', 'name_ar' => 'الفيوم', 'name_en' => 'Fayoum', 'cities' => [
                ['name_ar' => 'الفيوم', 'name_en' => 'Fayoum'],
                ['name_ar' => 'إبشواي', 'name_en' => 'Ibshaway'],
            ]],
            ['code' => 'BNS', 'name_ar' => 'بني سويف', 'name_en' => 'Beni Suef', 'cities' => [
                ['name_ar' => 'بني سويف', 'name_en' => 'Beni Suef'],
                ['name_ar' => 'الواسطى', 'name_en' => 'El Wasta'],
            ]],
            ['code' => 'MIN', 'name_ar' => 'المنيا', 'name_en' => 'Minya', 'cities' => [
                ['name_ar' => 'المنيا', 'name_en' => 'Minya'],
                ['name_ar' => 'ملوي', 'name_en' => 'Mallawi'],
                ['name_ar' => 'مغاغة', 'name_en' => 'Maghagha'],
            ]],
            ['code' => 'AST', 'name_ar' => 'أسيوط', 'name_en' => 'Asyut', 'cities' => [
                ['name_ar' => 'أسيوط', 'name_en' => 'Asyut'],
                ['name_ar' => 'ديروط', 'name_en' => 'Dairut'],
            ]],
            ['code' => 'SHG', 'name_ar' => 'سوهاج', 'name_en' => 'Sohag', 'cities' => [
                ['name_ar' => 'سوهاج', 'name_en' => 'Sohag'],
                ['name_ar' => 'جرجا', 'name_en' => 'Girga'],
                ['name_ar' => 'طما', 'name_en' => 'Tema'],
            ]],
            ['code' => 'QNA', 'name_ar' => 'قنا', 'name_en' => 'Qena', 'cities' => [
                ['name_ar' => 'قنا', 'name_en' => 'Qena'],
                ['name_ar' => 'نجع حمادي', 'name_en' => 'Nag Hammadi'],
            ]],
            ['code' => 'LXR', 'name_ar' => 'الأقصر', 'name_en' => 'Luxor', 'cities' => [
                ['name_ar' => 'الأقصر', 'name_en' => 'Luxor'],
                ['name_ar' => 'إسنا', 'name_en' => 'Esna'],
            ]],
            ['code' => 'ASW', 'name_ar' => 'أسوان', 'name_en' => 'Aswan', 'cities' => [
                ['name_ar' => 'أسوان', 'name_en' => 'Aswan'],
                ['name_ar' => 'كوم أمبو', 'name_en' => 'Kom Ombo'],
                ['name_ar' => 'إدفو', 'name_en' => 'Edfu'],
            ]],
        ];

        foreach ($governorates as $data) {
            $cities = $data['cities'];
            unset($data['cities']);

            /** @var Governorate $gov */
            $gov = Governorate::updateOrCreate(
                ['code' => $data['code']],
                $data
            );

            foreach ($cities as $cityData) {
                City::updateOrCreate(
                    [
                        'governorate_id' => $gov->id,
                        'name_en' => $cityData['name_en'],
                    ],
                    $cityData
                );
            }
        }
    }
}
