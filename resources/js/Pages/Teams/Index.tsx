import { router, useForm } from '@inertiajs/react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { Flash } from '../../Components/Flash';
import { route } from '../../lib/route';

export default function Index({ owned, memberships, empty, roles, pendingLabel, roleGuide, actions }: any) {
    const create = useForm({ name: '' });
    return (
        <ConsoleLayout crumb="Teams">
            <div className="app-main">
                <h1 className="page-title">Teams</h1>
                <Flash />
                {(! owned || owned.length === 0) && <p className="mt-5 muted">{empty || 'You do not own a team yet'}</p>}
                <form onSubmit={(e) => { e.preventDefault(); create.post(route('teams.store')); }} className="panel mt-6 flex gap-3">
                    <input className="field" placeholder="Team name" value={create.data.name} onChange={(e) => create.setData('name', e.target.value)} />
                    <button className="button-primary">Create team</button>
                </form>
                {(owned || []).map((team: any) => (
                    <section key={team.id} className="panel mt-6">
                        <h2 className="font-semibold heading">{team.name}</h2>
                        <InviteForm team={team} />
                        <h3 className="mt-6 text-sm font-semibold">Members</h3>
                        {(team.memberships || []).map((member: any) => (
                            <div key={member.id} className="mt-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                                <span>{member.user?.name || member.user?.email} · {member.role}</span>
                                {member.role !== 'owner' && (
                                    <span className="flex gap-3">
                                        <form onSubmit={(e) => { e.preventDefault(); const role = (e.currentTarget.elements.namedItem('role') as HTMLSelectElement).value; router.patch(route('teams.members.role', [team.id, member.id]), { role }); }} className="flex gap-2">
                                            <select className="field mt-0" name="role" defaultValue={member.role}><option value="admin">Admin</option><option value="operator">Operator</option><option value="viewer">Viewer</option></select>
                                            <button className="link-action">Save</button>
                                        </form>
                                        <button className="link-danger" onClick={() => router.delete(route('teams.members.remove', [team.id, member.id]))}>Remove</button>
                                    </span>
                                )}
                            </div>
                        ))}
                        <h3 className="mt-6 text-sm font-semibold">{pendingLabel || 'Pending invitations'}</h3>
                        {(team.invitations || []).map((invitation: any) => (
                            <div key={invitation.id} className="mt-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                                <span>{invitation.email} · {invitation.role}</span>
                                <span className="flex gap-3">
                                    <form method="post" action={route('teams.invitations.update', [team.id, invitation.id])} onSubmit={(e) => { e.preventDefault(); router.patch(route('teams.invitations.update', [team.id, invitation.id]), { role: invitation.role }); }}>
                                        <button className="link-action">{actions?.[0] || 'Edit'}</button>
                                    </form>
                                    <form method="post" action={route('teams.invitations.resend', [team.id, invitation.id])} onSubmit={(e) => { e.preventDefault(); router.post(route('teams.invitations.resend', [team.id, invitation.id])); }}>
                                        <button className="link-action">{actions?.[1] || 'Resend'}</button>
                                    </form>
                                    <form method="post" action={route('teams.invitations.destroy', [team.id, invitation.id])} onSubmit={(e) => { e.preventDefault(); router.delete(route('teams.invitations.destroy', [team.id, invitation.id])); }}>
                                        <button className="link-danger">{actions?.[2] || 'Delete'}</button>
                                    </form>
                                </span>
                            </div>
                        ))}
                        <h3 className="mt-6 text-sm font-semibold">{roleGuide || 'What each role can do'}</h3>
                        <p className="mt-2 text-sm muted">{(roles || ['Operator', 'Read-only access to shared infrastructure.']).join(' — ')}</p>
                        <form onSubmit={(e) => { e.preventDefault(); router.post(route('teams.switch', team.id)); }} className="mt-4"><button className="button-secondary">Switch workspace</button></form>
                    </section>
                ))}
                {(memberships || []).length > 0 && (
                    <section className="panel mt-6"><h2 className="font-semibold">Memberships</h2>{memberships.map((m: any) => <p key={m.id} className="mt-2 text-sm">{m.team?.name} · {m.role}</p>)}</section>
                )}
            </div>
        </ConsoleLayout>
    );
}

function InviteForm({ team }: any) {
    const form = useForm({ email: '', role: 'operator' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.post(route('teams.invite', team.id)); }} className="mt-4 flex flex-wrap gap-2">
            <input className="field mt-0" type="email" placeholder="teammate@example.com" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
            <select className="field mt-0" value={form.data.role} onChange={(e) => form.setData('role', e.target.value)}><option value="admin">Admin</option><option value="operator">Operator</option><option value="viewer">Viewer</option></select>
            <button className="button-secondary">Invite</button>
        </form>
    );
}
