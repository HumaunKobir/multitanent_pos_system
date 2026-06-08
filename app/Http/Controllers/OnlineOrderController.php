<?php

namespace App\Http\Controllers;

use App\Actions\Steadfast\SendOrderToSteadfast;
use App\Actions\Steadfast\SyncSteadfastOrderStatus;
use App\Enums\OrderStatus;
use App\Exceptions\SteadfastCourierException;
use App\Models\OnlineOrder;
use App\Services\OnlineOrderAccountingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class OnlineOrderController extends Controller
{
    public function __construct(
        private OnlineOrderAccountingService $accountingService,
        private SendOrderToSteadfast $sendOrderToSteadfast,
        private SyncSteadfastOrderStatus $syncSteadfastOrderStatus,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('online-order.view');

        $orders = OnlineOrder::query()
            ->withCount('products')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('courier_tracking_code', 'like', "%{$search}%")
                        ->orWhere('courier_invoice', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%");
                });
            })
            ->when($request->status, fn ($query, $status) => $query->where('status', (int) $status))
            ->when($request->courier, function ($query, $courier) {
                if ($courier === 'none') {
                    $query->whereNull('courier_consignment_id');
                } else {
                    $query->where('courier', $courier);
                }
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/online-order/index', [
            'orders' => $orders,
            'filters' => $request->only('search', 'status', 'courier'),
            'statuses' => collect(OrderStatus::cases())->map(fn (OrderStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->values(),
        ]);
    }

    public function show(OnlineOrder $onlineOrder): Response
    {
        $this->authorize('online-order.view');

        $onlineOrder->load('products.product');

        return Inertia::render('admin/online-order/show', [
            'order' => $onlineOrder,
            'statuses' => collect(OrderStatus::cases())->map(fn (OrderStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->values(),
            'canSendToSteadfast' => $onlineOrder->canSendToSteadfast(),
            'steadfastBlockReason' => $onlineOrder->steadfastSendBlockReason(),
        ]);
    }

    public function sendToSteadfast(OnlineOrder $onlineOrder): RedirectResponse
    {
        $this->authorize('online-order.update');

        try {
            $this->sendOrderToSteadfast->execute($onlineOrder);
        } catch (SteadfastCourierException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Order sent to Steadfast successfully.');
    }

    public function syncCourierStatus(OnlineOrder $onlineOrder): RedirectResponse
    {
        $this->authorize('online-order.update');

        try {
            $this->syncSteadfastOrderStatus->execute($onlineOrder);
        } catch (SteadfastCourierException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Steadfast delivery status updated.');
    }

    public function fulfill(Request $request, OnlineOrder $onlineOrder): RedirectResponse
    {
        $this->authorize('online-order.update');

        try {
            $this->accountingService->fulfill($onlineOrder);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Order delivered and accounting posted successfully.');
    }

    public function updateStatus(Request $request, OnlineOrder $onlineOrder): RedirectResponse
    {
        $this->authorize('online-order.update');

        $data = $request->validate([
            'status' => ['required', 'integer'],
        ]);

        $status = OrderStatus::tryFrom((int) $data['status']);

        if ($status === null) {
            return back()->with('error', 'Invalid order status.');
        }

        if ($status === OrderStatus::Delivered) {
            return $this->fulfill($request, $onlineOrder);
        }

        $onlineOrder->update(['status' => $status]);

        return back()->with('success', 'Order status updated.');
    }
}
