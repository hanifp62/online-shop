<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogFilterRequest;
use App\Services\CatalogService;
use App\Support\CatalogPageProps;
use App\Support\FrontendPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(CatalogFilterRequest $request, CatalogService $service): View|RedirectResponse|Response
    {
        $filters = $request->filters();
        $data = $service->data($filters);
        $page = (int) ($filters['page'] ?? 1);
        if ($page > $data['products']->lastPage()) {
            return redirect()->to($service->url([...$data['filters'], 'page' => $data['products']->lastPage()]));
        }

        return FrontendPage::render('Catalog/Index', 'catalog.index', $data, CatalogPageProps::from($data, $service), $data['canonical']);
    }
}
