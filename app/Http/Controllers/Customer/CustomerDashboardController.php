<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CustomerDashboardController extends Controller
{
    public function dashboard(): Response
    {
        $customer = auth('customer')->user();

        return Inertia::render('frontend/customer/dashboard', [
            'customer' => $customer,
            'orderCount' => $customer->orders()->count(),
            'pendingCount' => $customer->orders()->where('status', 1)->count(),
        ]);
    }

    public function orders(): Response
    {
        $orders = auth('customer')->user()
            ->orders()
            ->with('products')
            ->latest()
            ->paginate(10);

        return Inertia::render('frontend/customer/orders', [
            'orders' => $orders,
        ]);
    }

    public function orderDetails(int $id): Response
    {
        $order = auth('customer')->user()
            ->orders()
            ->with('products.product')
            ->findOrFail($id);

        return Inertia::render('frontend/customer/order-details', [
            'order' => $order,
        ]);
    }
}
