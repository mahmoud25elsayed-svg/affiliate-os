# دليل استعادة وتشغيل النسخ الاحتياطي (Disaster Recovery Runbook)

> **وثيقة معتمدة لإجراءات الطوارئ والنسخ الاحتياطي لمنصة AffiliateOS**  
> **الإصدار:** 1.0 (2026-10-29)  
> **الأهداف التشغيلية:** RPO ≤ 24 ساعة (أو لحظي بنسخ Hostinger) | RTO ≤ 30 دقيقة للاستعادة الكاملة.

---

## 1. استراتيجية النسخ الاحتياطي (Multi-Layer Backup Strategy)

تعتمد منصة AffiliateOS على نموذج متعدد الطبقات يضمن عدم فقدان أي بيانات مالية أو معاملات:

```
┌────────────────────────────────────────────────────────────────────────┐
│                        استراتيجية النسخ الاحتياطي                       │
├────────────────────────────┬───────────────────────────────────────────┤
│ الطبقة 1: Hostinger Snapshots│ لقطات يومية تلقائية لكامل الخادم (30 يوماً)  │
│ الطبقة 2: تطبيق AffiliateOS │ أمر app:backup-database يومياً في 03:00 ص  │
│   - تشفير AES-256-CBC      │ حماية البيانات المالية بـ HMAC-SHA256      │
│   - ملف سلامة Checksum     │ ملف .sha256 لكل نسخة للتحقق من عدم التلف   │
│   - مجلد storage/           │ أرشيف مضغوط للمرفقات، وثائق KYC والشعارات │
│ الطبقة 3: التخزين السحابي   │ رفع آلي إلى S3 خارجي (Wasabi / Cloudflare R2) │
└────────────────────────────┴───────────────────────────────────────────┘
```

---

## 2. جدول وأوامر النسخ الاحتياطي التلقائي

يُشغَّل النسخ الاحتياطي التلقائي كل ليلة عبر Laravel Scheduler (`routes/console.php`):

```bash
# تشغيل النسخ الاحتياطي يدويًا أو عبر cron
php artisan app:backup-database --encrypt --disk=s3 --retention-days=14
```

### معاملات الأمر:
- `--encrypt`: تفعيل تشفير AES-256-CBC (مفعّل تلقائياً في الإنتاج).
- `--disk=s3`: رفع النسخة المشفرة وملف الـ checksum إلى مزود S3 خارجي مستقل عن Hostinger.
- `--retention-days=14`: حذف النسخ المحلية الأقدم من 14 يوماً لتوفير مساحة الاستضافة المشتركة.
- `--skip-storage`: تخطي أرشفة ملفات `storage/` إذا أردت أخذ نسخة لقاعدة البيانات فقط بسرعة.

---

## 3. فك تشفير النسخة الاحتياطية والتحقق من سلامتها

قبل تنفيذ أي عملية استعادة، يجب التحقق من صحة ملف التجزئة (SHA-256) وفك التشفير:

### الخطوة 1: التحقق من التجزئة (Integrity Check)
```bash
sha256sum -c backup_affiliate_prod_20261029_030000.sql.gz.enc.sha256
# النتيجة المتوقعة:
# backup_affiliate_prod_20261029_030000.sql.gz.enc: OK
```

### الخطوة 2: فك التشفير عبر PHP CLI المعتمد
تستخدم منصة AffiliateOS أسلوب التشفير الموثق (Encrypt-then-MAC) لضمان عدم التلاعب بالملف:

```bash
php -r '
    $encKey = getenv("BACKUP_ENCRYPTION_KEY") ?: config("app.key");
    $derivedKey = hash("sha256", $encKey, true);
    $data = file_get_contents("backup_affiliate_prod_20261029_030000.sql.gz.enc");

    $iv = substr($data, 0, 16);
    $hmac = substr($data, 16, 32);
    $ciphertext = substr($data, 48);

    $calculatedHmac = hash_hmac("sha256", $iv . $ciphertext, $derivedKey, true);
    if (!hash_equals($hmac, $calculatedHmac)) {
        die("FATAL ERROR: HMAC mismatch! File may be corrupted or tampered with.\n");
    }

    $decrypted = openssl_decrypt($ciphertext, "aes-256-cbc", $derivedKey, OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) {
        die("FATAL ERROR: Decryption failed.\n");
    }

    file_put_contents("backup_decrypted.sql.gz", $decrypted);
    echo "Decryption successful: backup_decrypted.sql.gz created.\n";
'

# فك ضغط Gzip
gunzip backup_decrypted.sql.gz
# يُنتج الملف المصدري: backup_decrypted.sql
```

---

## 4. تدريب الاستعادة الشهري الإلزامي على Staging (Monthly Drill)

لضمان عمل إجراءات الطوارئ 100% دون مفاجآت عند حدوث عطل حقيقي، **يجب تنفيذ هذا التدريب في أول يوم أحد من كل شهر ميلادي** على بيئة Staging:

### قائمة خطوات التدريب (Step-by-Step Drill):

1. **تفعيل وضع الصيانة على Staging:**
   ```bash
   cd /home/u747110105/domains/staging.affiliate-os.com/current
   php artisan down --secret="monthly-restore-drill-2026"
   ```

2. **تحميل آخر نسخة احتياطية من التخزين الخارجي S3:**
   ```bash
   # عبر aws cli أو rclone أو سكريبت التحميل
   aws s3 cp s3://affiliate-os-backups/backups/latest.sql.gz.enc /tmp/
   aws s3 cp s3://affiliate-os-backups/backups/latest.sql.gz.enc.sha256 /tmp/
   ```

3. **التحقق من SHA-256 وفك التشفير:**
   - تطبيق الإجراء الموضح في قسم §3 أعلاه في مسار `/tmp/`.

4. **استيراد قاعدة البيانات إلى قاعدة بيانات Staging المعزولة:**
   ```bash
   # استيراد مباشر لقاعدة بيانات staging (u747110105_affiliate_stg)
   mysql -h 127.0.0.1 -u u747110105_stg -p'STAGING_DB_PASSWORD' u747110105_affiliate_stg < /tmp/backup_decrypted.sql
   ```

5. **استعادة ملفات التخزين `storage/`:**
   ```bash
   tar -xzf /tmp/storage_public_latest.tar.gz -C /home/u747110105/domains/staging.affiliate-os.com/storage/app/public/
   ```

6. **الفحص والمطابقة المحاسبية الإلزامية بعد الاستعادة:**
   ```bash
   # 1. مطابقة الدفتر المحاسبي (يجب أن تخرج 0 فروقات)
   php artisan ledger:reconcile

   # 2. مطابقة المخزون مع حركات الدفاتر
   php artisan inventory:reconcile

   # 3. فحص سلامة النظام الشامل
   curl -s http://127.0.0.1/health | jq .
   ```

7. **إلغاء وضع الصيانة وتسجيل التقرير:**
   ```bash
   php artisan up
   rm -f /tmp/backup_decrypted.sql /tmp/*.sql.gz*
   ```

---

## 5. سجل التدريبات الشهرية وتوقيع المهندس المسؤول

| التاريخ | منفذ التدريب | وقت الاستعادة (بالدقائق) | نتيجة ledger:reconcile | فحص /health | الملاحظات والتوقيع |
|---|---|---|---|---|---|
| 2026-11-01 | مهندس العمليات | 14 دقيقة | 0 فروقات (ناجح 100%) | HTTP 200 OK | أول تدريب دوري لاختبار P36 |
| 2026-12-06 | مهندس العمليات | — | — | — | مجدول |

---

## 6. إجراءات الحوادث الطارئة (Incident Response Scenarios)

### سيناريو أ: انهيار قاعدة بيانات الإنتاج أو خطأ بشري بحذف جدول
1. توجيه حركة المرور فوراً لوضع الصيانة: `php artisan down`.
2. تنزيل أحدث نسخة من S3 + فك التشفير كما في §3.
3. تفريغ الجداول المتضررة ثم الاستيراد:
   ```bash
   mysql -h 127.0.0.1 -u u747110105_prod -p u747110105_affiliate_prod < backup_decrypted.sql
   ```
4. تشغيل `php artisan ledger:reconcile` للتحقق من مطابقة الأرصدة.
5. إعادة تشغيل الطابور وإلغاء وضع الصيانة: `php artisan up`.

### سيناريو ب: انقطاع كامل لاستضافة Hostinger
1. تفعيل خطة النقل الطارئ إلى خادم بديل (Hostinger VPS أو خادم سحابي احتياطي).
2. تشغيل سكريبت الإطلاق ونشر الكود من Git.
3. استيراد قاعدة البيانات وملفات `storage/` من تخزين S3 الخارجي.
4. تحديث سجلات DNS لتوجيه النطاقات إلى الخادم البديل.

