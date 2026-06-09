<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\OnlineOrderInvoicePdfService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CustomerDashboardController extends Controller
{
    public function __construct(
        private OnlineOrderInvoicePdfService $invoicePdfService,
    ) {}

    public function dashboard(): Response
    {
        $customer = auth('customer')->user();

        return Inertia::render('frontend/customer/dashboard', [
            'customer' => $customer->only([
                'id', 'name', 'email', 'phone', 'address', 'point', 'registration_type',
            ]),
            'orderCount' => $customer->orders()->count(),
            'pendingCount' => $customer->orders()->where('status', 1)->count(),
        ]);
    }

    public function orders(): Response
    {
        $customer = auth('customer')->user();

        $orders = $customer->orders()
            ->with('products')
            ->latest()
            ->paginate(10);

        return Inertia::render('frontend/customer/orders', [
            'orders' => $orders,
            'orderCount' => $customer->orders()->count(),
            'pendingCount' => $customer->orders()->where('status', 1)->count(),
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

    public function downloadInvoice(int $id): HttpResponse
    {
        $order = auth('customer')->user()
            ->orders()
            ->with('products')
            ->findOrFail($id);

        return $this->invoicePdfService->download($order);
    }
}
