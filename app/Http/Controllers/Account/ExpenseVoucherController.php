<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseVoucherController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/accounts/expense-voucher/index');
    }
}
