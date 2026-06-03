import { AnimatePresence, motion } from 'framer-motion';
import { CheckCircle2 } from 'lucide-react';

export function ToastNotification({ message }) {
    return (
        <AnimatePresence>
            {message && (
                <motion.div
                    initial={{ opacity: 0, y: -20, x: '-50%' }}
                    animate={{ opacity: 1, y: 0, x: '-50%' }}
                    exit={{ opacity: 0, y: -20, x: '-50%' }}
                    className="fixed left-1/2 top-4 z-[100] flex items-center gap-2 rounded-lg bg-store-primary px-4 py-2.5 text-sm text-white shadow-lg"
                >
                    <CheckCircle2 className="size-4 shrink-0 text-green-400" />
                    {message}
                </motion.div>
            )}
        </AnimatePresence>
    );
}
