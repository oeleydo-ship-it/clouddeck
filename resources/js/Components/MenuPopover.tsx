import { ReactNode, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

export function MenuPopover({
    open,
    anchor,
    onClose,
    widthClass = 'w-56',
    matchWidth = false,
    children,
}: {
    open: boolean;
    anchor: { current: HTMLElement | null };
    onClose: () => void;
    widthClass?: string;
    matchWidth?: boolean;
    children: ReactNode;
}) {
    const panelRef = useRef<HTMLDivElement>(null);
    const onCloseRef = useRef(onClose);
    onCloseRef.current = onClose;
    const [pos, setPos] = useState({ top: 56, right: 16, left: 16, width: 224 });

    useEffect(() => {
        if (! open) {
            return;
        }

        const place = () => {
            const rect = anchor.current?.getBoundingClientRect();
            if (! rect) {
                return;
            }

            const gap = 8;
            const height = panelRef.current?.offsetHeight ?? 0;
            const fitsBelow = rect.bottom + gap + height <= window.innerHeight - 12;
            const top = height && ! fitsBelow
                ? Math.max(12, rect.top - height - gap)
                : rect.bottom + gap;

            setPos({
                top,
                right: Math.max(12, window.innerWidth - rect.right),
                left: rect.left,
                width: rect.width,
            });
        };

        place();
        const frame = window.requestAnimationFrame(place);
        window.addEventListener('resize', place);
        window.addEventListener('scroll', place, true);
        const onDoc = (event: MouseEvent) => {
            const target = event.target as Node;
            if (anchor.current?.contains(target) || panelRef.current?.contains(target)) {
                return;
            }
            onCloseRef.current();
        };
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onCloseRef.current();
            }
        };
        document.addEventListener('mousedown', onDoc);
        document.addEventListener('keydown', onKey);

        return () => {
            window.cancelAnimationFrame(frame);
            window.removeEventListener('resize', place);
            window.removeEventListener('scroll', place, true);
            document.removeEventListener('mousedown', onDoc);
            document.removeEventListener('keydown', onKey);
        };
    }, [open, anchor]);

    if (! open || typeof document === 'undefined') {
        return null;
    }

    return createPortal(
        <div
            ref={panelRef}
            role="menu"
            className={`menu-panel !fixed !mt-0 z-[80] ${matchWidth ? '!w-auto' : widthClass}`}
            style={matchWidth ? { top: pos.top, left: pos.left, width: pos.width } : { top: pos.top, right: pos.right }}
        >
            {children}
        </div>,
        document.body,
    );
}
