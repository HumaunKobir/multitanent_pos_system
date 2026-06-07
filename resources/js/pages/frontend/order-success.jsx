import { Head, Link } from '@inertiajs/react';
import { CheckCircle } from 'lucide-react';
import { StoreButton } from '@/components/frontend/store-button';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export default function OrderSuccess({ order }) {
    return (
        <FrontendLayout>
            <Head title="Order Successful" />
            <div className="store-container max-w-2xl py-10 text-center">
                <CheckCircle className="mx-auto mb-4 size-14 text-green-500" />
                <h1 className="mb-2 text-2xl font-bold text-store-primary">Order placed successfully!</h1>
                <p className="text-store-muted">
                    Order number: <strong className="text-store-primary">#{order.id}</strong>
                </p>

                <div className="mt-6 rounded-lg border border-gray-100 bg-white p-5 text-left shadow-sm">
                    <h2 className="mb-3 font-semibold text-store-primary">Order details</h2>
                    <div className="mb-3 grid gap-2 text-sm sm:grid-cols-2">
                        <div>
                            <p className="text-store-muted">Name</p>
                            <p className="font-medium">{order.name}</p>
                        </div>
                        <div>
                            <p className="text-store-muted">Phone</p>
                            <p className="font-medium">{order.phone}</p>
                        </div>
                        <div className="sm:col-span-2">
                            <p className="text-store-muted">Address</p>
                            <p className="font-medium">{order.address}</p>
                        </div>
                    </div>
                    <div className="divide-y divide-gray-50">
                        {order.products?.map((item, i) => (
                            <div key={i} className="flex justify-between py-2 text-sm">
                                <span className="text-store-muted">
                                    {item.name} × {item.quantity}
                                </span>
                                <span>৳{Number(item.total_price).toFixed(0)}</span>
                            </div>
                        ))}
                    </div>
                    <div className="mt-3 space-y-1 border-t border-gray-100 pt-3 text-sm">
                        <div className="flex justify-between">
                            <span className="text-store-muted">Delivery</span>
                            <span>৳{Number(order.delivery_charge).toFixed(0)}</span>
                        </div>
                        <div className="flex justify-between font-bold">
                            <span>Total</span>
                            <span className="text-store-accent">৳{Number(order.total).toFixed(0)}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-store-muted">Payment</span>
                            <span className="rounded-full bg-store-warm px-2 py-0.5 text-xs capitalize">
                                {order.payment_method === 'cod'
                                    ? 'Cash on Delivery'
                                    : order.payment_method === 'sslcommerz' && order.payment_status === 'Paid'
                                      ? 'SSLCommerz · Paid'
                                      : order.payment_method === 'sslcommerz'
                                        ? 'SSLCommerz · Pending'
                                        : order.payment_status}
                            </span>
                        </div>
                    </div>
                </div>

                <div className="mt-6 flex flex-wrap justify-center gap-3">
                    <Link href="/">
                        <StoreButton>Back to Home</StoreButton>
                    </Link>
                    <Link href="/customer/orders">
                        <StoreButton variant="outline">My Orders</StoreButton>
                    </Link>
                </div>
            </div>
        </FrontendLayout>
    );
}
