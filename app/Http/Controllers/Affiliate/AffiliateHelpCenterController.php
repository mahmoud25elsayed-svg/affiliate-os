<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate;

use App\Domain\Support\Services\HelpCenterService;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\HelpArticle;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AffiliateHelpCenterController extends Controller
{
    public function __construct(
        protected HelpCenterService $helpCenterService
    ) {}

    public function index(Request $request): View
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $category = $request->query('category');
        $search = $request->query('q');

        $categories = $this->helpCenterService->getCategories(HelpArticle::AUDIENCE_AFFILIATES, $tenant);

        if ($search) {
            $articles = $this->helpCenterService->search($search, HelpArticle::AUDIENCE_AFFILIATES, $tenant);
        } else {
            $articles = $this->helpCenterService->getArticles(HelpArticle::AUDIENCE_AFFILIATES, $category, $tenant);
        }

        return view('affiliate.help.index', [
            'tenant' => $tenant,
            'affiliate' => $affiliate,
            'categories' => $categories,
            'articles' => $articles,
            'currentCategory' => $category,
            'searchQuery' => $search,
        ]);
    }
}
