import { useForm, usePage } from '@inertiajs/react';
import { route } from '@/lib/route';

export function FooterNewsletter() {
    const { flash, newsletter = {} } = usePage().props;
    const {
        enabled = true,
        title = 'Sign Up For Newsletter',
        description = '',
        placeholder = 'Your Email Address...',
        button = 'Subscribe',
    } = newsletter;

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
    });

    if (!enabled) {
        return null;
    }

    const submit = (event) => {
        event.preventDefault();

        post(route('subscribe.store'), {
            preserveScroll: true,
            onSuccess: () => reset('email'),
        });
    };

    return (
        <div className="lg:text-left">
            <h3 className="text-sm font-bold text-white">{title}</h3>

            {description && (
                <p className="mt-2 max-w-xs text-xs leading-relaxed text-white/65">{description}</p>
            )}

            <form onSubmit={submit} className="mt-4 flex max-w-xs flex-col items-start gap-4">
                <div className="w-full">
                    <input
                        type="email"
                        value={data.email}
                        onChange={(event) => setData('email', event.target.value)}
                        placeholder={placeholder}
                        className="w-full border-0 border-b border-white/35 bg-transparent px-0 py-2 text-sm text-white placeholder:text-white/45 focus:border-white focus:outline-none focus:ring-0"
                        required
                    />
                    {errors.email && <p className="mt-2 text-xs text-red-300">{errors.email}</p>}
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-full bg-store-accent px-8 py-2.5 text-sm font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-50"
                >
                    {button}
                </button>
            </form>

            {flash?.success && <p className="mt-3 max-w-xs text-xs text-white/80">{flash.success}</p>}
        </div>
    );
}
