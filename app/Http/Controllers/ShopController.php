<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ShopController extends Controller
{
    public function index(): View
    {
        return view('shop.index');
    }
}
