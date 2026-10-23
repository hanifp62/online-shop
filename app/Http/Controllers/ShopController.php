<?php

namespace App\Http\Controllers;

use App\Support\FrontendPage;
use App\Support\ShopPageProps;
use Illuminate\View\View;
use Inertia\Response;

class ShopController extends Controller
{
    public function index(): Response|View
    {
        return FrontendPage::render('Shop/Index', 'shop.index', [], ShopPageProps::index());
    }
}
