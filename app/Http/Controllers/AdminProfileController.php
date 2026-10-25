<?php

namespace App\Http\Controllers;

use App\Support\AdminProfilePageProps;
use App\Support\FrontendPage;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Response;

final class AdminProfileController extends Controller
{
    public function show(Request $request): View|Response
    {
        $profilePage = AdminProfilePageProps::show($request, $request->user('admin'));

        return FrontendPage::render('Admin/Profile', 'admin.profile', compact('profilePage'), $profilePage, route('admin.profile.show'));
    }
}
