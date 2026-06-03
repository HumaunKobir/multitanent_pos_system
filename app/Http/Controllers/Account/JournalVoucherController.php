<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class JournalVoucherController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/accounts/journal-voucher/index');
    }
}
