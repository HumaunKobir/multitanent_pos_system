<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ContraVoucherController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/accounts/contra-voucher/index');
    }
}
