<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Support\Services\HelpCenterService;
use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreHelpCenterController extends Controller
{
    public function __construct(
        protected HelpCenterService $helpCenterService
    ) {}

    public function index(Request $request): View
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        $category = $request->query('category');
        $search = $request->query('q');

        $categories = $this->helpCenterService->getCategories(HelpArticle::AUDIENCE_PUBLIC, $tenant);

        if ($search) {
            $articles = $this->helpCenterService->search($search, HelpArticle::AUDIENCE_PUBLIC, $tenant);
        } else {
            $articles = $this->helpCenterService->getArticles(HelpArticle::AUDIENCE_PUBLIC, $category, $tenant);
        }

        return view('storefront.help.index', [
            'tenant' => $tenant,
            'categories' => $categories,
            'articles' => $articles,
            'currentCategory' => $category,
            'searchQuery' => $search,
        ]);
    }

    public function show(string $slug): View
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        /** @var HelpArticle $article */
        $article = HelpArticle::where('slug', $slug)
            ->where('audience', HelpArticle::AUDIENCE_PUBLIC)
            ->published()
            ->firstOrFail();

        /** @var view-string $viewName */
        $viewName = 'storefront.help.show';

        return view($viewName, [
            'tenant' => $tenant,
            'article' => $article,
        ]);
    }
}
