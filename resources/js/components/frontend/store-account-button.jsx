import { Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { UserRound } from 'lucide-react';

export function StoreAccountButton({ href, customer, className = '', onClick }) {
    const isLoggedIn = Boolean(customer);
    const label = isLoggedIn ? 'My Account' : 'Login';

    return (
        <motion.div whileHover={{ scale: 1.02 }} whileTap={{ scale: 0.98 }} className={className}>
            <Link
                href={href}
                onClick={onClick}
                className="group inline-flex items-center gap-2 rounded-full border border-store-primary/10 bg-store-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors duration-200 hover:border-store-accent hover:bg-store-accent"
            >
                <UserRound className="size-4 shrink-0" strokeWidth={2.25} />
                <span>{label}</span>
            </Link>
        </motion.div>
    );
}
