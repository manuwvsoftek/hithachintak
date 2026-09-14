<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PrantModel;

/**
 * Small public, unauthenticated informational pages linked from the login
 * screen. No auth guard and nothing sensitive here — the Trust identity
 * details on the Contact Us page are meant to be public: they're what a
 * donor needs to verify who they're giving to.
 */
class PagesController extends BaseController
{
    public function about()
    {
        return view('pages/about', ['title' => 'About this platform']);
    }
     public function privacyPolicy()
    {
        return view('pages/privacypolicy', ['title' => 'Privacy Policy']);
    }

    public function termsConditions()
    {
        return view('pages/termsconditions', ['title' => 'Terms & Conditions']);
    }

    public function services()
    {
        return view('pages/services', ['title' => 'Our Services']);
    }
    public function contactUs()
    {
        $prantModel = new PrantModel();
        $selectedId = (int) ($this->request->getGet('prant_id') ?: 0);

        return view('pages/contact', [
            'title'      => 'Contact Us',
            'prants'     => $prantModel->orderBy('name', 'ASC')->findAll(),
            'selectedId' => $selectedId,
            'selected'   => $selectedId ? $prantModel->find($selectedId) : null,
        ]);
    }
}
