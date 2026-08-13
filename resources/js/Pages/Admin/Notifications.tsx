import { useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { route } from '../../lib/route';

export default function Notifications({ clientEmailEnabled, eventToggles, eventLabels, billingFailedAllowed, masterLabel }: any) {
    const form = useForm({
        client_email_notifications_enabled: clientEmailEnabled,
        client_email_billing_payment_failed: billingFailedAllowed,
        events: Object.entries(eventToggles || {}).filter(([, on]) => on).map(([key]) => key),
    });

    return (
        <AdminLayout
            title="Notification center"
            description="Mute client alert emails per event so SMTP quota is not burned. Database (bell) delivery stays on."
            actions={<button form="notify-form" className="button-primary">Save</button>}
        >
            <form id="notify-form" onSubmit={(e) => { e.preventDefault(); form.put(route('admin.settings.notifications')); }} className="panel space-y-5">
                <label className="check-row">
                    <input type="checkbox" checked={form.data.client_email_notifications_enabled} onChange={(e) => form.setData('client_email_notifications_enabled', e.target.checked)} />
                    <span>
                        {masterLabel || 'Send operational alert emails'}
                        <span className="mt-0.5 block text-xs font-normal muted">Master switch for customer operational mail.</span>
                    </span>
                </label>
                <div>
                    <h2 className="section-title">Events</h2>
                    <p className="field-hint">Unchecked events stay in the customer bell but are not emailed.</p>
                    <div className="mt-3 grid gap-2 sm:grid-cols-2">
                        {Object.keys(eventToggles || {}).map((key) => (
                            <label key={key} className="check-row">
                                <input type="checkbox" checked={form.data.events.includes(key)} onChange={(e) => form.setData('events', e.target.checked ? [...form.data.events, key] : form.data.events.filter((k) => k !== key))} />
                                {eventLabels?.[key] || key}
                            </label>
                        ))}
                    </div>
                </div>
                <label className="check-row">
                    <input type="checkbox" checked={form.data.client_email_billing_payment_failed} onChange={(e) => form.setData('client_email_billing_payment_failed', e.target.checked)} />
                    Billing payment failed emails
                </label>
            </form>
        </AdminLayout>
    );
}
