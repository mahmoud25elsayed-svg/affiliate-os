# ADR 18: استراتيجية ومعمارية تطبيقات الجوال لمنظومة AffiliateOS (ما بعد الإطلاق)
# Mobile Applications Architecture & Multi-Tenant White-Label Ecosystem

- **الحالة (Status):** مُعتمد (Accepted & Ready for Implementation)
- **المرحلة:** المرحلة 5 — الإنتاج والإطلاق (P40)
- **التاريخ:** أكتوبر 2026
- **المؤلفون:** فريق هندسة المنصة (Platform & Mobile Architecture Team)
- **الأنظمة المستهدفة:** iOS (App Store) و Android (Google Play)

---

## 1. الملخص التنفيذي وأهداف المعمارية (Executive Summary)

بعد إطلاق منصة **AffiliateOS** (الإصدار `v1.0.0`)، يمثل التوسع في تطبيقات الجوال الأصلية (Native Mobile Apps) القناة الأكثر تأثيرًا على معدلات الاحتفاظ (Retention) وسرعة العمليات اليومية وتجربة المستخدمين في أسواق التجارة الإلكترونية والتسويق بالعمولة في مصر والشرق الأوسط.

تهدف هذه الوثيقة إلى وضع الأساس المعماري والتقني المكتمل لثلاثة تطبيقات جوال متكاملة مع واجهة برمجة التطبيقات للمنصة (REST API v1):
1. **تطبيق العميل الأبيض (White-Label Customer Storefront App):** متجر جوال مُخصص لكل تاجر بهويته البصرية المستقلة يُبنى آليًا عبر خطوط CI.
2. **تطبيق الأفلييت (Affiliate Mobile App):** تطبيق المسوق المتكامل الذي يغطي كامل مزايا تطبيق الويب التقدمي (PWA) مع ميزات أصلية مثل الإشعارات الفورية (Push) والتحقق بالهوية (Camera KYC).
3. **تطبيق التاجر المختصر (Merchant Companion App):** لوحة تحكم سريعة وعملية لأصحاب المتاجر لمتابعة الأداء اللحظي (GMV) والبت في طلبات الكول سنتر وإدارة الشحنات أثناء التنقل.

---

## 2. تقييم التقنية والتوصية الفنية: Flutter أم React Native؟

### 2.1 المقارنة التحليلية الشاملة

| معيار التقييم | React Native (Expo SDK 52+ / TypeScript) | Flutter (Dart / Impeller) | الأفضل لـ AffiliateOS |
| :--- | :--- | :--- | :--- |
| **إعادة استخدام الكود (Code Reuse)** | **عالية جداً (85%+):** مشاركة أنواع TypeScript، وحزم التحقق (Zod)، ومنطق الحسابات، وعميل الـ API مع واجهات React 18 الحالية (P21). | **منخفضة:** كتابة كامل المنطق ونماذج البيانات مجددًا بلغة Dart بشكل منفصل عن كود الويب. | **React Native** |
| **دعم العربية والاتجاه (RTL Support)** | دعم ممتاز ومستقر عبر `I18nManager.forceRTL(true)` وتوافق كامل مع خط Cairo ورموز Tailwind (عبر NativeWind v4). | دعم ممتاز عبر ويدجت `Directionality` الأصلي في فلاتر وتوافق خطوط Google Fonts. | **تعادل** |
| **الأداء وسرعة الاستجابة** | أداء 60/120 إطار بالثانية بفضل المعمارية الجديدة (New Architecture: Hermes Engine + Fabric Renderer + TurboModules). | أداء فائق وتصيير فوري بالرسوميات عبر محرك Impeller و Skia بدون جسر (No Bridge). | **Flutter (بفارق طفيف)** |
| **البناء الآلي الأبيض (White-Label CI Matrix)** | **استثنائي:** يدعم Expo Application Services (EAS) ملف `app.config.ts` ديناميكي يحقن الألوان والشعارات و Bundle IDs برمجياً في زمن البناء. | يحتاج نصوص برمجية معقدة لتعديل ملفات `android/app/build.gradle` و `ios/Runner.xcodeproj` لكل تاجر. | **React Native** |
| **التحديثات الهوائية (OTA Updates)** | دعم مدمج كامل عبر **Expo EAS Update** لتحديث واجهات التطبيقات وإصلاح الأخطاء فوراً دون انتظار مراجعة المتاجر. | دعم محدود ومعقد يتطلب حلول طرف ثالث (Shorebird) بتكلفة وبنية إضافية. | **React Native** |
| **منظومة المكتبات والعتاد (MENA Ecosystem)** | توافر واسع لمكتبات الدفع، وإشعارات Firebase، وقارئات الباركود، وبصمة الوجه، والخرائط. | مكتبات مستقرة وممتازة مدعومة مباشرة من مجتمع Google. | **تعادل** |
| **تكلفة الصيانة وسرعة التطوير (TCO & Velocity)** | توظيف نفس مطوري الواجهات الأمامية (Full-stack TypeScript) دون الحاجة لفريق Dart متخصص منفصل. | تتطلب مهندسين متخصصين بلغة Dart وإطار Flutter حصراً. | **React Native** |

### 2.2 القرار المعماري النهائي (Architectural Decision)

> **القرار: اعتماد React Native (Expo Framework + TypeScript + NativeWind) كمنصة أساسية لتطبيقات منظومة AffiliateOS.**
>
> **الأسباب الجوهرية:**
> 1. **التجانس مع مكدس المنصة:** تمتلك المنصة واجهة PWA غنية ومكتوبة بـ React 18 و TypeScript. استخدام React Native يسمح بمشاركة عميل الـ API المُولد تلقائياً وحزم الحسابات والأنواع بنسبة 100%.
> 2. **كفاءة خط الإنتاج الأبيض (White-Label Multi-Tenant CI):** يوفر إطار Expo القدرة على بناء مئات النسخ من تطبيق المتاجر بملف تكوين واحد يتغذى من الـ API دون المساس بالكود الأصلي (Native iOS/Android Code).
> 3. **التحديثات الهوائية العاجلة (OTA):** إمكانية دفع التحديثات الطارئة لتعديل أخطاء الواجهة لآلاف العملاء والتجار في ثوانٍ معدودة عبر Expo Updates.
>
> *(ملاحظة معمارية: يُوثق Flutter كخيار بديل متاح للمتاجر الكبرى التي تطلب تطبيقاً حصرياً مخصصاً بالكامل Single-Brand وتملك فريق Dart مخصص).*

---

## 3. معمارية المستودع: Monorepo أم مستودعات منفصلة؟

### 3.1 المقترح المعتمد: مستودع موحد (Monorepo)

نوصي باعتماد **مستودع موحد (Monorepo)** يحمل اسم `affiliate-os-mobile` مبني بواسطة **Turborepo** و **pnpm Workspaces**.
يحتوي المستودع على ثلاث تطبيقات جوال رئيسية:
- `apps/customer-app`: تطبيق العميل الأبيض (White-Label Storefront).
- `apps/affiliate-app`: تطبيق المسوقين والأفلييت مع دعم الإشعارات وتوثيق الهوية بالكاميرا.
- `apps/merchant-app`: تطبيق التاجر المختصر للعمليات والمبيعات وطابور الكول سنتر ومسح الشحنات.

بالإضافة إلى حزم TypeScript المشتركة:
- `packages/api-client`: عميل الـ API المولد تلقائياً.
- `packages/design-system`: نظام التصميم المشترك والرموز البصرية.
- `packages/types`: النماذج والواجهات المشتركة.

### 3.2 الهيكل التنظيمي للمستودع (Monorepo Directory Layout)

```text
affiliate-os-mobile/
├── .github/
│   └── workflows/
│       ├── build-customer-app.yml       # خط بناء التطبيق الأبيض للمتاجر (Matrix CI)
│       ├── build-affiliate-app.yml      # خط بناء وتوزيع تطبيق الأفلييت
│       ├── build-merchant-app.yml       # خط بناء وتوزيع تطبيق التاجر المختصر
│       └── pr-verification.yml          # الفحص التلقائي للكود والنوعية (Typecheck & Lint)
├── apps/
│   ├── customer-app/                    # التطبيق الأبيض لعملاء المتاجر (White-Label Storefront)
│   │   ├── app.config.ts                # التكوين الديناميكي للهوية والألوان والمعرفات
│   │   ├── src/
│   │   │   ├── screens/                 # شاشات التصفح، السلة، وتتبع الطلب
│   │   │   └── navigation/
│   │   └── package.json
│   ├── affiliate-app/                   # تطبيق المسوقين والأفلييت
│   │   ├── src/
│   │   │   ├── screens/                 # الكتالوج، المحفظة، منشئ الروابط، وتوثيق KYC
│   │   │   └── modules/kyc/             # وحدة التقاط البطاقة القومية بالكاميرا
│   │   └── package.json
│   └── merchant-app/                    # تطبيق التاجر المختصر
│       ├── src/
│       │   ├── screens/                 # ملخص الـ GMV، طابور الكول سنتر، إدارة الشحنات
│       │   └── modules/scanner/         # قارئ الباركود السريع للشحنات
│       └── package.json
├── packages/
│   ├── api-client/                      # عميل API مبني آلياً من routes/api_v1.php و OpenAPI
│   │   ├── src/
│   │   │   ├── client.ts                # Axios/Fetch مغلف مع إدارة التوكن ومفتاح الهوية
│   │   │   └── endpoints/               # endpoints: products, orders, wallet, devices
│   │   └── package.json
│   ├── design-system/                   # الرموز البصرية المشتركة والتصاميم
│   │   ├── src/
│   │   │   ├── components/              # Button, Input, Card, Modal, Sheet (RTL/Cairo)
│   │   │   └── tokens/                  # ألوان، مسافات، وظلال متوافقة مع NativeWind
│   │   └── package.json
│   └── types/                           # نماذج البيانات المشتركة
│       ├── src/                         # Order, Product, Variant, Affiliate, Tenant
│       └── package.json
├── eas.json                             # إعدادات EAS Build Profiles (dev, preview, production)
├── turbo.json                           # إعدادات التخزين المؤقت لمهام البناء
├── package.json
└── tsconfig.base.json
```

---

## 4. مواصفات التطبيقات الثلاثة (Applications Specifications)

### 4.1 التطبيق الأول: تطبيق العميل الأبيض (White-Label Customer Storefront App)

- **الفئة المستهدفة:** عملاء المتاجر الراغبين في الشراء من متجر تاجر معين.
- **آلية التخصيص التلقائي (White-Label Build Pipeline):**
  - ملف `app.config.ts` يقرأ المتغير `EXPO_PUBLIC_TENANT_ID` أثناء البناء في CI.
  - يستعلم خط البناء من الـ API: `GET /api/v1/mobile/config` ليجلب:
    - اسم المتجر (`store_name`)
    - الشعار والأيقونة (`logo_url`, `icon_url`)
    - الألوان الأساسية والثانوية (`primary_color`, `secondary_color`)
    - معرف التطبيق الفريد: `com.affiliateos.store.<tenant_id>`
  - يتم استبدال الـ Assets وبناء حزمة أصلية مستقلة (APK / AAB / IPA).
- **الميزات الوظيفية الأساسية:**
  - **كتالوج المنتجات السريع:** تصفح الأقسام، وبحث فوري مع خاصية Debounce، وفلترة حسب السعر والمخزون.
  - **صفحة المنتج:** عرض خيارات المقاسات والألوان (Variants)، وجدول المقاسات، وتقييمات العملاء الموثقة.
  - **شراء بصفحة واحدة (COD 1-Page Checkout):**
    - مصمم خصيصاً لمحافظات مصر الـ 27 مع دعم التكملة التلقائية للعناوين.
    - التحقق الصارم من صحة رقم المحمول المصري (+20).
    - حفظ العناوين على الجهاز محلياً (AsyncStorage/MMKV) لسرعة إعادة الشراء.
  - **تتبع الطلبات اللحظي:** خط زمني تفاعلي لتغير حالات الطلب:
    `قيد المراجعة` -> `تم التأكيد` -> `في الكول سنتر` -> `خرج مع المندوب` -> `تم التوصيل`.
  - **إشعارات الوصول الفورية (Push):** تنبيه فوري: *"طلبك رقم #1234 في طريقه إليك مع شركة بوسطة، رقم المندوب: ..."*.

---

### 4.2 التطبيق الثاني: تطبيق الأفلييت (Affiliate Mobile App)

- **الفئة المستهدفة:** المسوقون بالعمولة وصناع المحتوى وتجار الدروب شيبينغ.
- **تغطية مزايا تطبيق الويب التقدمي (100% PWA Parity):**
  - استعراض الكتالوج الشامل لجميع المتاجر المصرح بها مع أسعار الجملة وهوامش الربح المقترحة.
  - منشئ الروابط الذكي (Instant Link Builder) مع دعم الوسوم (Sub-IDs) للمسوقين عبر TikTok و Facebook و Snapchat.
  - حجز الطلبات اليدوي (Custom Order Placement) نيابة عن العملاء مع تحصيل الدفع عند الاستلام.
  - لوحة الأرباح والمحفظة متعددة المتاجر مع عرض الأرباح المعلقة والمتاحة للسحب وطلب التحويل اللحظي (InstaPay / محفظة إلكترونية).
- **القوى الإضافية الأصلية (Native Superpowers):**
  - **إشعارات المبيعات اللحظية الصاخبة (Cash Chime Push):**
    تنبيه فوري مع نغمة نقدية مميزة عند تسجيل طلب جديد أو تسليم طلب بنجاح: *"🎉 مبروك! تم تسليم طلبك بنجاح وأضيفت عمولة 180 ج.م إلى محفظتك"*.
  - **توثيق الهوية الذكي بالكاميرا (Native Camera KYC):**
    - التقاط صورة بطاقة الرقم القومي المصرية (وجه وظهر) مع إطار توجيهي ذكي (Smart Bounding Box).
    - ضغط الصورة محلياً قبل الرفع لتجنب انقطاع الاتصال في شبكات المحمول الضعيفة.
    - دعم التقاط سيلفي حي للتحقق من هوية صاحب الحساب البنكي.
  - **الدخول البيومتري الآمن (Biometric Authentication):**
    - تسجيل الدخول والتحقق من سحب الأرباح ببصمة الإصبع أو FaceID لحماية المحفظة.
  - **المشاركة السريعة بلمسة واحدة (Native Share Sheet):**
    - توليد رسالة واتساب جاهزة بالصور والروابط ومشاركتها مباشرة بنقرة واحدة عبر تطبيق WhatsApp.

---

### 4.3 التطبيق الثالث: تطبيق التاجر المختصر (Merchant Companion App)

- **الفئة المستهدفة:** التاجر ومدير العمليات وفريق خدمة العملاء أثناء التنقل.
- **الشاشات والميزات التشغيلية:**
  - **لوحة الأداء التنفيذية اللحظية (Real-Time KPI Cards):**
    - إجمالي المبيعات (GMV) اليوم، وصافي الأرباح، وعدد الطلبات الجديدة، ونسبة التأكيد (Confirmation Rate).
  - **طابور تأكيد الطلبات السريع (Call Center Triage Queue):**
    - بطاقات طلبات مع إمكانية السحب السريع (Swipe Actions):
      - سحب يمين: تأكيد الطلب وتحويله للتجهيز (`confirmed`).
      - سحب يسار: جدولة إعادة اتصال بالعميل لاحقاً (`rescheduled`).
      - زر فوري: اتصال مباشر بالعميل هاتفياً عبر شريحة الهاتف دون كشف رقم التاجر الخاص إذا رغب.
  - **مراقبة الشحنات والمرتجعات (Logistics Monitor):**
    - متابعة حية لحالات شركات الشحن (بوسطة، مايلرز): عدد الطرود الخارجة للتوصيل، ومحاولات التسليم الفاشلة لتداركها مع العميل هاتفياً في نفس اليوم.
  - **ماسح الباركود السريع بالكاميرا (Barcode Scanner):**
    - مسح بوليصة الشحن بكاميرا الهاتف لنقل حالة الطلب فوراً إلى `جاهز للشحن` أو تسليمه لشركة الشحن دون الحاجة لجهاز كمبيوتر أو ماسح ليزري.
  - **تنبيهات الطوارئ الفورية:**
    - إشعار عند نفاد مخزون منتج عالي الطلب، أو تسجيل طلب بمبلغ غير معتاد (مكافحة الاحتيال).

---

## 5. خط النشر التلقائي للمتاجر (CI/CD Publishing Pipeline)

تعتمد المنصة على أداة **Fastlane** المدمجة مع **Expo Application Services (EAS)** و **GitHub Actions** لإتمام النشر التلقائي لاختبارات TestFlight و Google Play Internal Testing.

### 5.1 تدفق إدارة الشهادات والتوقيع الرقمي (Code Signing Architecture)

```mermaid
flowchart TD
    subgraph Apple iOS
        A1[Fastlane Match] -->|Encrypted Git Repo| A2[Distribution Certificate & Provisioning Profiles]
        A2 -->|EAS Credentials / GitHub Secrets| A3[Fastlane gym / ipa Build]
        A3 -->|App Store Connect API Key| A4[TestFlight & App Store Production]
    end

    subgraph Google Android
        B1[Google Play Console] -->|Service Account JSON| B2[Upload Keystore & Key Password]
        B2 -->|GitHub Secrets| B3[Fastlane supply / aab Build]
        B3 -->|Google Play Developer API| B4[Internal Track & Production Track]
    end
```

### 5.2 ملف التكوين Fastlane (`Fastfile`) لتطبيقات المنصة

```ruby
# fastlane/Fastfile
default_platform(:ios)

platform :ios do
  desc "Push a new beta build to TestFlight"
  lane :beta do |options|
    app_id = options[:app_id] || "com.affiliateos.merchant"
    
    match(type: "appstore", app_identifier: app_id, readonly: true)
    increment_build_number(build_number: ENV["GITHUB_RUN_NUMBER"])
    
    build_app(
      workspace: "ios/AffiliateOS.xcworkspace",
      scheme: options[:scheme] || "AffiliateOS",
      export_method: "app-store"
    )
    
    upload_to_testflight(
      api_key_path: "fastlane/app_store_key.json",
      skip_waiting_for_build_processing: true
    )
  end
end

platform :android do
  desc "Deploy a new version to the Google Play Internal track"
  lane :beta do |options|
    package_name = options[:package_name] || "com.affiliateos.merchant"
    
    gradle(
      task: "bundle",
      build_type: "Release",
      project_dir: "android/"
    )
    
    upload_to_play_store(
      track: "internal",
      package_name: package_name,
      json_key: "fastlane/play_store_key.json",
      aab: "android/app/build/outputs/bundle/release/app-release.aab"
    )
  end
end
```

### 5.3 استراتيجية التحديثات الهوائية (OTA Updates Strategy)

لتفادي تأخير مراجعة المتاجر (التي تستغرق من 24 إلى 72 ساعة في Apple App Store)، تُقسّم التحديثات إلى مسارين:
1. **تحديثات جافاسكريبت والواجهات (OTA via Expo Updates):**
   - إصلاحات الأخطاء البرمجية الخفيفة، تعديل نصوص، تحديث ألوان وهوامش، ترقية منطق الحسابات.
   - يتم إطلاقها خلال 60 ثانية وتصل للمستخدمين تلقائياً عند فتح التطبيق.
2. **تحديثات الحزم الأصلية (Store Releases):**
   - إضافة مكتبات أصلية جديدة (مثل كاميرا، بصمة، عتاد بلوتوث)، أو ترقية إصدار الـ SDK.
   - يتم رفعها بجدول دوري (Bi-weekly Release Cycle) عبر Fastlane.

---

## 6. البنية التحتية الخلفية ودعم الإشعارات (Backend Infrastructure)

### 6.1 مخطط جدول الأجهزة (`mobile_devices`)

يتم تسجيل وتتبع كافة الأجهزة الجوالة مع عزل صارم متعدد المستأجرين (Multi-Tenancy):

```sql
CREATE TABLE mobile_devices (
    id BIGSERIAL PRIMARY KEY,
    tenant_id VARCHAR(255) NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    user_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    affiliate_id BIGINT NULL REFERENCES affiliates(id) ON DELETE SET NULL,
    device_token VARCHAR(512) NOT NULL,
    platform VARCHAR(32) NOT NULL,       -- 'ios', 'android'
    app_type VARCHAR(32) NOT NULL,       -- 'customer', 'affiliate', 'merchant'
    device_name VARCHAR(255) NULL,       -- e.g. 'iPhone 15 Pro Max', 'Samsung Galaxy S24'
    app_version VARCHAR(32) NULL,        -- e.g. '1.0.0'
    locale VARCHAR(10) NOT NULL DEFAULT 'ar',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_active_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    CONSTRAINT mobile_devices_tenant_token_unique UNIQUE (tenant_id, device_token)
);

CREATE INDEX idx_mobile_devices_tenant_type_active ON mobile_devices (tenant_id, app_type, is_active);
CREATE INDEX idx_mobile_devices_tenant_affiliate ON mobile_devices (tenant_id, affiliate_id);
CREATE INDEX idx_mobile_devices_tenant_user ON mobile_devices (tenant_id, user_id);
```

### 6.2 نقاط نهاية REST API v1 المخصصة للجوال

تم دمج نقاط النهاية في المنظومة الآمنة `routes/api_v1.php`:

1. **تسجيل جهاز جديد أو تحديث التوكن:**
   - **الطلب:** `POST /api/v1/mobile/devices`
   - **المصادقة:** ترويسة `Authorization: Bearer <API_KEY>` مع صلاحية `mobile:devices:manage`.
   - **المدخلات:**
     ```json
     {
       "device_token": "fcm_token_string_here...",
       "platform": "ios",
       "app_type": "affiliate",
       "device_name": "iPhone 15 Pro",
       "app_version": "1.0.0",
       "locale": "ar"
     }
     ```
   - **الاستجابة:** `200 OK` مع كائن الجهاز المسجل.

2. **إلغاء تسجيل الجهاز (تسجيل الخروج):**
   - **الطلب:** `DELETE /api/v1/mobile/devices/{token}`
   - **الاستجابة:** `200 OK` وتعديل حالة الجهاز إلى `is_active = false`.

3. **جلب إعدادات الهوية الديناميكية (White-Label Dynamic Config):**
   - **الطلب:** `GET /api/v1/mobile/config`
   - **الاستجابة:**
     ```json
     {
       "tenant_id": "store-al-ahram",
       "store_name": "متجر الأهرام للملابس",
       "currency": "EGP",
       "locale": "ar",
       "branding": {
         "primary_color": "#10B981",
         "secondary_color": "#0F172A",
         "font_family": "Cairo",
         "logo_url": "https://storage.affiliate-os.com/store-al-ahram/logo.png"
       },
       "features": {
         "affiliate_portal": true,
         "cod_checkout": true,
         "order_tracking": true,
         "biometric_auth": true,
         "camera_kyc": true
       },
       "api_version": "v1"
     }
     ```

### 6.3 هيكل حزمة إشعارات Firebase Cloud Messaging (FCM HTTP v1)

```json
{
  "message": {
    "token": "dGhpcyBpcyBhIGZjbSBkZXZpY2UgdG9rZW4...",
    "notification": {
      "title": "🎉 مبروك! عمولة جديدة",
      "body": "تم تسجيل طلب جديد برقم #ORD-9821. عمولتك المقدرة: 150.00 ج.م"
    },
    "data": {
      "type": "order_commission",
      "order_id": "9821",
      "amount": "15000",
      "currency": "EGP",
      "click_action": "OPEN_WALLET_TAB"
    },
    "android": {
      "priority": "high",
      "notification": {
        "channel_id": "affiliateos_updates",
        "sound": "cash_register.wav"
      }
    },
    "apns": {
      "payload": {
        "aps": {
          "sound": "cash_register.aiff",
          "badge": 1
        }
      }
    }
  }
}
```

---

## 7. الخصوصية والامتثال القانوني (قانون 151 لسنة 2020)

تلتزم تطبيقات الجوال بمعايير حماية البيانات الشخصية:
1. **صلاحيات الأجهزة الحساسة (Permissions):**
   - لا يتم طلب صلاحية الكاميرا إلا لحظة بدء تصوير بطاقة الرقم القومي (KYC) أو مسح باركود الشحنة مع شرح واضح للسبب للمستخدم.
   - لا يتم تتبع الموقع الجغرافي بالخلفية مطلقاً (Background Location is strictly prohibited).
2. **معالجة توكنات الإشعارات:**
   - تشفير وحفظ توكنات FCM بأمان وعدم مشاركتها مع أي جهة خارجية.
   - الإلغاء الفوري للتوكنات عند قيام المسوق أو العميل بتسجيل الخروج من التطبيق.
3. **حذف الحساب والبيانات (App Store Account Deletion Requirement):**
   - تضمين زر واضح وسهل في إعدادات التطبيق لطلب حذف الحساب وجميع البيانات الشخصية امتثالاً للفقرة 5.1.1(v) من إرشادات متجر آبل وقانون 151 المصري.

---

## 8. الخلاصة وخطة التنفيذ (Next Steps)

1. تم إنشاء جداول قاعدة البيانات `mobile_devices` وخدمة الإشعارات الخلفية `MobilePushNotificationService` ونقاط النهاية في `routes/api_v1.php`.
2. يتم تدشين مستودع الجوال المستقل `affiliate-os-mobile` عبر المنظومة الموحدة (Monorepo) مع حزم TypeScript المشتركة.
3. إطلاق الإصدار التجريبي الداخلي (Alpha) لتطبيق التاجر وتطبيق الأفلييت وتوزيعهما عبر TestFlight و Google Play Internal Testing.
