<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Domain\Tenancy\Services\CustomDomainService;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Tenant;
use App\Support\Billing\Exceptions\FeatureNotAvailableException;
use App\Support\Billing\Features;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class StoreDomainController extends Controller
{
    public function __construct(
        protected CustomDomainService $customDomainService
    ) {}

    public function index(): View
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail((string) tenant('id'));

        $isFeatureAvailable = Features::enabled('custom_domain', $tenant);

        $domains = Domain::query()
            ->where('tenant_id', (string) $tenant->id)
            ->orderByDesc('is_primary')
            ->orderBy('is_custom')
            ->get();

        $primaryDomain = $domains->firstWhere('is_primary', true) ?? $domains->first();
        $subdomain = $domains->firstWhere('is_custom', false);
        $redirectSubdomain = $subdomain ? (bool) $subdomain->redirect_to_primary : false;

        return view('store.domains.index', [
            'tenant' => $tenant,
            'isFeatureAvailable' => $isFeatureAvailable,
            'domains' => $domains,
            'primaryDomain' => $primaryDomain,
            'subdomain' => $subdomain,
            'redirectSubdomain' => $redirectSubdomain,
            'cnameTarget' => (string) config('tenancy.cname_target', 'cname.affiliate-os.com'),
            'serverIp' => (string) config('tenancy.server_ip', '127.0.0.1'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail((string) tenant('id'));

        if (! Features::enabled('custom_domain', $tenant)) {
            throw new FeatureNotAvailableException('custom_domain');
        }

        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
        ], [
            'domain.required' => __('يرجى إدخال اسم النطاق.'),
        ]);

        try {
            $this->customDomainService->addCustomDomain($tenant, $validated['domain']);

            return redirect()
                ->route('store.domains.index')
                ->with('success', __('تمت إضافة النطاق المخصص بنجاح. يرجى إضافة سجلات DNS الموضحة أدناه ثم الضغط على زر التحقق.'));
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['domain' => $e->getMessage()]);
        }
    }

    public function verify(Domain $domain): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail((string) tenant('id'));

        if ((string) $domain->tenant_id !== (string) $tenant->id) {
            abort(403, __('غير مصرح لك بإدارة هذا النطاق.'));
        }

        $verified = $this->customDomainService->verifyDomain($domain);

        if ($verified) {
            return redirect()
                ->route('store.domains.index')
                ->with('success', __('تم التحقق من النطاق :domain بنجاح! تم تفعيل النطاق وجاري إصدار شهادة الأمان SSL.', ['domain' => $domain->domain]));
        }

        return redirect()
            ->route('store.domains.index')
            ->with('error', __('تعذر التحقق من سجلات DNS للنطاق :domain. يرجى التأكد من حفظ سجل TXT أو CNAME والانتظار حتى يكتمل انتشار DNS عالمياً.', ['domain' => $domain->domain]));
    }

    public function setPrimary(Domain $domain): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail((string) tenant('id'));

        try {
            $this->customDomainService->setPrimaryDomain($tenant, $domain);

            return redirect()
                ->route('store.domains.index')
                ->with('success', __('تم تعيين النطاق :domain كنطاق أساسي للمتجر بنجاح.', ['domain' => $domain->domain]));
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('store.domains.index')
                ->with('error', $e->getMessage());
        }
    }

    public function toggleRedirect(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail((string) tenant('id'));

        $enable = $request->boolean('redirect');
        $this->customDomainService->setSubdomainRedirection($tenant, $enable);

        $msg = $enable
            ? __('تم تفعيل إعادة التوجيه التلقائي من النطاق الفرعي إلى النطاق الأساسي المخصص.')
            : __('تم إيقاف إعادة التوجيه التلقائي من النطاق الفرعي.');

        return redirect()
            ->route('store.domains.index')
            ->with('success', $msg);
    }

    public function destroy(Domain $domain): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail((string) tenant('id'));

        try {
            $this->customDomainService->removeCustomDomain($tenant, $domain);

            return redirect()
                ->route('store.domains.index')
                ->with('success', __('تم حذف النطاق المخصص بنجاح.'));
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('store.domains.index')
                ->with('error', $e->getMessage());
        }
    }
}
