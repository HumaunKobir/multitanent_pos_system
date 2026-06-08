import { ChevronDown, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { storeCn } from '@/lib/store-cn';

function FaqItem({ item, isOpen, onToggle }) {
    return (
        <div
            className={storeCn(
                'overflow-hidden rounded-xl border bg-white transition-shadow duration-200',
                isOpen
                    ? 'border-store-accent/30 shadow-md shadow-store-primary/5 ring-1 ring-store-accent/15'
                    : 'border-gray-200/80 hover:border-gray-300 hover:shadow-sm',
            )}
        >
            <button
                type="button"
                onClick={onToggle}
                aria-expanded={isOpen}
                className="flex w-full items-start gap-3 px-4 py-3.5 text-left sm:px-5 sm:py-4"
            >
                <span
                    className={storeCn(
                        'mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg text-xs font-bold transition-colors',
                        isOpen ? 'bg-store-accent text-white' : 'bg-store-primary/5 text-store-primary',
                    )}
                    aria-hidden
                >
                    ?
                </span>
                <span className="min-w-0 flex-1 text-sm font-semibold leading-snug text-store-primary sm:text-[15px]">
                    {item.question}
                </span>
                <ChevronDown
                    className={storeCn(
                        'mt-0.5 size-4 shrink-0 text-store-muted transition-transform duration-200',
                        isOpen && 'rotate-180 text-store-accent',
                    )}
                    aria-hidden
                />
            </button>

            <div
                className={storeCn(
                    'grid transition-[grid-template-rows] duration-200 ease-out',
                    isOpen ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
                )}
            >
                <div className="overflow-hidden">
                    <div
                        className="prose prose-sm max-w-none border-t border-gray-100 px-4 pb-4 pt-3 text-store-muted prose-p:my-0 prose-p:leading-relaxed prose-a:text-store-accent prose-strong:text-store-primary sm:px-5 sm:pb-5 sm:pl-14"
                        dangerouslySetInnerHTML={{ __html: item.answer }}
                    />
                </div>
            </div>
        </div>
    );
}

export function FaqAccordion({ items }) {
    const [query, setQuery] = useState('');
    const [openQuestions, setOpenQuestions] = useState(() => new Set(items[0] ? [items[0].question] : []));

    const filteredItems = useMemo(() => {
        const normalized = query.trim().toLowerCase();

        if (!normalized) {
            return items;
        }

        return items.filter(
            (item) =>
                item.question.toLowerCase().includes(normalized) ||
                item.answer.replace(/<[^>]+>/g, '').toLowerCase().includes(normalized),
        );
    }, [items, query]);

    const toggleItem = (question) => {
        setOpenQuestions((current) => {
            const next = new Set(current);

            if (next.has(question)) {
                next.delete(question);
            } else {
                next.add(question);
            }

            return next;
        });
    };

    return (
        <div>
            <div className="relative">
                <Search
                    className="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-store-muted"
                    aria-hidden
                />
                <input
                    type="search"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Search questions..."
                    className="w-full rounded-xl border border-gray-200 bg-white py-3 pl-10 pr-4 text-sm text-store-primary shadow-sm outline-none transition-colors placeholder:text-store-muted/70 focus:border-store-accent/50 focus:ring-2 focus:ring-store-accent/15"
                />
            </div>

            {filteredItems.length > 0 ? (
                <div className="mt-4 space-y-2.5">
                    {filteredItems.map((item) => (
                        <FaqItem
                            key={item.question}
                            item={item}
                            isOpen={openQuestions.has(item.question)}
                            onToggle={() => toggleItem(item.question)}
                        />
                    ))}
                </div>
            ) : (
                <div className="mt-6 rounded-xl border border-dashed border-gray-200 bg-gray-50/80 px-5 py-8 text-center">
                    <p className="text-sm font-medium text-store-primary">No matching questions</p>
                    <p className="mt-1 text-xs text-store-muted">Try a different search term or contact our support team.</p>
                </div>
            )}
        </div>
    );
}
