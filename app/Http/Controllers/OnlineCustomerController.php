<?php

namespace App\Http\Controllers;

use App\Enums\CustomerRegistrationType;
use App\Enums\OrderStatus;
use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnlineCustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('online-customer.view');

        $customers = Customer::query()
            ->where('registration_type', CustomerRegistrationType::Online)
            ->withCount($this->orderCountRelations())
            ->when($request->search, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/online-customer/index', [
            'customers' => $customers,
            'totalCustomers' => Customer::query()
                ->where('registration_type', CustomerRegistrationType::Online)
                ->count(),
            'filters' => $request->only('search'),
        ]);
    }

    public function show(Customer $customer): Response
    {
        $this->authorize('online-customer.view');

        abort_unless($customer->registration_type === CustomerRegistrationType::Online, 404);

        $customer->loadCount($this->orderCountRelations());

        $orders = $customer->orders()
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/online-customer/show', [
            'customer' => $customer,
            'orders' => $orders,
            'statuses' => collect(OrderStatus::cases())->map(fn (OrderStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->values(),
        ]);
    }

    /**
     * @return array<int|string, \Closure|string>
     */
    private function orderCountRelations(): array
    {
        return [
            'orders',
            'orders as pending_orders_count' => fn ($query) => $query->where('status', OrderStatus::Pending),
            'orders as processing_orders_count' => fn ($query) => $query->where('status', OrderStatus::Processing),
            'orders as confirmed_orders_count' => fn ($query) => $query->where('status', OrderStatus::Confirmed),
            'orders as shipping_orders_count' => fn ($query) => $query->where('status', OrderStatus::Shipping),
            'orders as delivered_orders_count' => fn ($query) => $query->where('status', OrderStatus::Delivered),
            'orders as canceled_orders_count' => fn ($query) => $query->where('status', OrderStatus::Canceled),
            'orders as cod_orders_count' => fn ($query) => $query->where('payment_method', 'cod'),
            'orders as online_payment_orders_count' => fn ($query) => $query->where('payment_method', 'sslcommerz'),
        ];
    }
}
