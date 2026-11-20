<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicContentController extends Controller
{
    public function show(string $slug): View|Response
    {
        $page = config("public-pages.pages.{$slug}");

        abort_unless(is_array($page), 404);

        return view('public.content-page', [
            'page' => $page,
            'seoPage' => [
                'title' => $page['title'],
                'description' => $page['description'],
                'canonicalRoute' => $page['route'],
                'index' => $page['index'],
                'breadcrumbs' => [
                    ['name' => 'Beranda', 'route' => 'home'],
                    ['name' => $page['heading'], 'route' => $page['route']],
                ],
            ],
        ]);
    }
}
