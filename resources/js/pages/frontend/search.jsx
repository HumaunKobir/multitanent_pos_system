import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import FrontendLayout from '@/layouts/frontend/frontend-layout';
import { ProductCard } from '@/components/frontend/product-card';
import { Link } from '@inertiajs/react';

export default function Search({ query, products }) {
    const [searchQuery, setSearchQuery] = useState(query);

    const handleSearch = (e) => {
        e.preventDefault();
        if (searchQuery.trim()) {
            router.get('/search', { q: searchQuery });
        }
    };

    return (
        <FrontendLayout>
            <Head title={`"${query}" খোঁজার ফলাফল`} />
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <form onSubmit={handleSearch} className="mb-8 flex gap-2">
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder="পণ্য খুঁজুন..."
                        className="flex-1 border border-gray-300 px-4 py-2.5 text-sm focus:border-black focus:outline-none"
                    />
                    <button
                        type="submit"
                        className="bg-black px-6 py-2.5 text-sm text-white hover:bg-gray-800"
                    >
                        খুঁজুন
                    </button>
                </form>

                {query && (
                    <h1 className="mb-4 text-lg font-semibold text-gray-900">
                        "{query}" এর জন্য {products.total} টি ফলাফল
                    </h1>
                )}

                {products.data?.length > 0 ? (
                    <>
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                            {products.data.map((product) => (
                                <ProductCard key={product.id} product={product} />
                            ))}
                        </div>
                        {products.links && (
                            <div className="mt-8 flex flex-wrap justify-center gap-1">
                                {products.links.map((link, i) =>
                                    link.url ? (
                                        <Link
                                            key={i}
                                            href={link.url}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                            className={`border px-3 py-1.5 text-sm ${link.active ? 'border-black bg-black text-white' : 'border-gray-300 text-gray-700 hover:border-black'}`}
                                        />
                                    ) : (
                                        <span
                                            key={i}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                            className="border border-gray-200 px-3 py-1.5 text-sm text-gray-400"
                                        />
                                    ),
                                )}
                            </div>
                        )}
                    </>
                ) : (
                    query && (
                        <div className="py-16 text-center text-gray-400">
                            "{query}" এর জন্য কোনো পণ্য পাওয়া যায়নি।
                        </div>
                    )
                )}
            </div>
        </FrontendLayout>
    );
}
