<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogFilterRequest;
use App\Services\CatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(CatalogFilterRequest $request, CatalogService $service): View|RedirectResponse
    {
        $filters = $request->filters();
        $data = $service->data($filters);
        $page = (int) ($filters['page'] ?? 1);
        if ($page > $data['products']->lastPage()) {
            return redirect()->to($service->url([...$data['filters'], 'page' => $data['products']->lastPage()]));
        }

        return view('catalog.index', $data);
    }
}
