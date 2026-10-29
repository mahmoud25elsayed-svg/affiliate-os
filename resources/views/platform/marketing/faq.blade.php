@extends('platform.marketing.layout')

@section('content')
<section style="padding: 5rem 0 3rem; text-align: center;">
    <div class="container" style="max-width: 850px;">
        <span class="badge">مركز المعرفة والأسئلة الشائعة</span>
        <h1 style="font-size: 2.75rem; font-weight: 900; margin-bottom: 1.25rem;">إجابات واضحة عن كل ما يشغل بالك</h1>
        <p style="font-size: 1.15rem; color: var(--text-muted); line-height: 1.7;">
            جمعنا لك أهم الأسئلة حول آلية عمل المتجر، دورة تسوية أرباح المسوقين، تأكيد طلبات COD، والربط التقني.
        </p>
    </div>
</section>

<section style="padding-top: 1rem; padding-bottom: 5rem;">
    <div class="container" style="max-width: 850px; display: flex; flex-direction: column; gap: 1.25rem;">

        <!-- FAQ Item 1 -->
        <details class="card" style="cursor: pointer; padding: 1.5rem 2rem;">
            <summary style="font-size: 1.2rem; font-weight: 700; color: #fff; list-style: none; display: flex; justify-content: space-between; align-items: center;">
                <span>كيف أبدأ مع المنصة وكم تستغرق تهيئة المتجر؟</span>
                <span style="font-size: 1.5rem; color: #818cf8;">+</span>
            </summary>
            <p style="color: var(--text-muted); margin-top: 1rem; line-height: 1.8; font-size: 1rem;">
                تستغرق عملية إنشاء المتجر أقل من 60 ثانية بمجرد تسجيل اسم النطاق الفرعي ورقم هاتفك المصري وتأكيده برمز التحقق (OTP). يتم إعداد قاعدة البيانات الخاصة بمتجرك وتفعيل الكتالوج وبوابة الأفلييت فوراً.
            </p>
        </details>

        <!-- FAQ Item 2 -->
        <details class="card" style="cursor: pointer; padding: 1.5rem 2rem;">
            <summary style="font-size: 1.2rem; font-weight: 700; color: #fff; list-style: none; display: flex; justify-content: space-between; align-items: center;">
                <span>كيف تضمن المنصة عدم التلاعب بالعمولات عند إلغاء أو إرجاع الشحنات؟</span>
                <span style="font-size: 1.5rem; color: #818cf8;">+</span>
            </summary>
            <p style="color: var(--text-muted); margin-top: 1rem; line-height: 1.8; font-size: 1rem;">
                تعتمد المنصة على دفتر أستاذ محاسبي مزدوج (Double-Entry Ledger) ونموذج القفل الثلاثي: يتم احتجاز العمولة كـ "رصيد معلق" حتى يتم تسليم الشحنة للعميل، وتنقضي فترة الإرجاع القانونية (14 يوماً)، ويتم تأكيد توريد مبالغ الشحن COD، مما يمنع حدوث أي فوارق مالية.
            </p>
        </details>

        <!-- FAQ Item 3 -->
        <details class="card" style="cursor: pointer; padding: 1.5rem 2rem;">
            <summary style="font-size: 1.2rem; font-weight: 700; color: #fff; list-style: none; display: flex; justify-content: space-between; align-items: center;">
                <span>ما هي شركات الشحن المصرية المدعومة في الربط الآلي؟</span>
                <span style="font-size: 1.5rem; color: #818cf8;">+</span>
            </summary>
            <p style="color: var(--text-muted); margin-top: 1rem; line-height: 1.8; font-size: 1rem;">
                تدعم المنصة الربط البرمجي الفوري مع كبرى شركات الشحن والتوزيع في مصر (مثل بوسطة Bosta، أوتو Oto، وأرامكس Aramex). يمكنك إدخال مفتاح الـ API الخاص بحسابك لإصدار البوالص وتلقي تحديثات التسليم لحظة بلحظة.
            </p>
        </details>

        <!-- FAQ Item 4 -->
        <details class="card" style="cursor: pointer; padding: 1.5rem 2rem;">
            <summary style="font-size: 1.2rem; font-weight: 700; color: #fff; list-style: none; display: flex; justify-content: space-between; align-items: center;">
                <span>كيف يتم صرف أرباح المسوقين بعد اعتمادها؟</span>
                <span style="font-size: 1.5rem; color: #818cf8;">+</span>
            </summary>
            <p style="color: var(--text-muted); margin-top: 1rem; line-height: 1.8; font-size: 1rem;">
                يقدم المسوق طلب سحب عبر لوحته المخصصة عند بلوغ الحد الأدنى المسموح به في متجرك. يمكنك مراجعة وتدقيق مستندات KYC وصرف المبالغ عبر شبكة المدفوعات اللحظية (إنستاباي InstaPay)، المحافظ الإلكترونية (فودافون كاش، اتصالات، أورنج)، أو التحويلات البنكية بضغطة زر.
            </p>
        </details>

        <!-- FAQ Item 5 -->
        <details class="card" style="cursor: pointer; padding: 1.5rem 2rem;">
            <summary style="font-size: 1.2rem; font-weight: 700; color: #fff; list-style: none; display: flex; justify-content: space-between; align-items: center;">
                <span>هل يمكنني ربط اسم نطاق مخصص (دومين خاص) بمتجري؟</span>
                <span style="font-size: 1.5rem; color: #818cf8;">+</span>
            </summary>
            <p style="color: var(--text-muted); margin-top: 1rem; line-height: 1.8; font-size: 1rem;">
                نعم، تدعم خطط Pro و Enterprise ربط أي اسم نطاق خاص بك (مثل `mystore.com`) مع توفير وتجديد شهادة أمان SSL تلقائياً مجاناً بدون أي تكاليف إضافية.
            </p>
        </details>

        <!-- FAQ Item 6 -->
        <details class="card" style="cursor: pointer; padding: 1.5rem 2rem;">
            <summary style="font-size: 1.2rem; font-weight: 700; color: #fff; list-style: none; display: flex; justify-content: space-between; align-items: center;">
                <span>هل بيانات متجري وعملائي معزولة ومحمية؟</span>
                <span style="font-size: 1.5rem; color: #818cf8;">+</span>
            </summary>
            <p style="color: var(--text-muted); margin-top: 1rem; line-height: 1.8; font-size: 1rem;">
                بالتأكيد. تطبق المنصة معمارية عزل المستأجرين (Tenant Isolation) المشددة لضمان استحالة تسريب أو رؤية أي متجر لبيانات أو مبيعات متجر آخر، مع تشفير البيانات الحساسة وامتثال تام لقانون حماية البيانات الشخصية المصري رقم 151 لسنة 2020.
            </p>
        </details>

    </div>
</section>

<!-- JSON-LD FAQ Schema -->
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@@type": "FAQPage",
  "mainEntity": [
    {
      "@@type": "Question",
      "name": "كيف أبدأ مع المنصة وكم تستغرق تهيئة المتجر؟",
      "acceptedAnswer": {
        "@@type": "Answer",
        "text": "تستغرق عملية إنشاء المتجر أقل من 60 ثانية بمجرد تسجيل اسم النطاق ورقم الهاتف وتأكيده برمز OTP."
      }
    },
    {
      "@@type": "Question",
      "name": "كيف تضمن المنصة عدم التلاعب بالعمولات عند إلغاء أو إرجاع الشحنات؟",
      "acceptedAnswer": {
        "@@type": "Answer",
        "text": "تعتمد المنصة على دفتر أستاذ محاسبي مزدوج واحتجاز العمولة حتى تسليم الشحنة وانقضاء فترة الإرجاع القانونية."
      }
    }
  ]
}
</script>
@endsection
