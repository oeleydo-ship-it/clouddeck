import { PropsWithChildren } from 'react';
import ConsoleLayout from './ConsoleLayout';
import { Flash } from '../Components/Flash';

export default function AdminLayout({ children, title, description, actions }: PropsWithChildren<{ title: string; description?: string; actions?: React.ReactNode }>) {
    return (
        <ConsoleLayout crumb={title}>
            <div className="app-main">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div className="min-w-0">
                        <p className="page-eyebrow page-eyebrow-admin">Administration</p>
                        <h1 className="page-title">{title}</h1>
                        {description && <p className="page-subtitle">{description}</p>}
                    </div>
                    {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
                </header>
                <Flash />
                <div className="mt-6 space-y-6">{children}</div>
            </div>
        </ConsoleLayout>
    );
}
