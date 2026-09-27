import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import ConfirmDialog from '@/Components/ConfirmDialog';
import Field from '@/Components/Field';
import Modal from '@/Components/Modal';
import Pagination from '@/Components/Pagination';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime } from '@/lib/format';

const EMPTY_USER = { name: '', username: '', role: 'wali_kelas', is_active: true, password: '' };

export default function UsersIndex({ users, filters, roles }) {
    const { auth } = usePage().props;
    const [query, setQuery] = useState(filters.q);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const form = useForm(EMPTY_USER);
    const firstRender = useRef(true);

    function applyFilters(changes) {
        router.get('/pengguna', { ...filters, ...changes }, { preserveState: true, preserveScroll: true, replace: true });
    }

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timer = setTimeout(() => applyFilters({ q: query }), 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query]);

    function openCreate() {
        form.clearErrors();
        form.setData({ ...EMPTY_USER, role: filters.role || 'wali_kelas' });
        setEditing('new');
    }

    function openEdit(user) {
        form.clearErrors();
        form.setData({ name: user.name, username: user.username, role: user.role, is_active: user.isActive, password: '' });
        setEditing(user);
    }

    function submit(event) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setEditing(null) };

        if (editing === 'new') {
            form.post('/pengguna', options);
        } else {
            form.put(`/pengguna/${editing.id}`, options);
        }
    }

    function destroy() {
        router.delete(`/pengguna/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) });
    }

    const isSelf = editing !== 'new' && editing?.id === auth.user.id;

    return (
        <>
            <Head title="Pengguna" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">Master data</h6>
                    <h1>Pengguna</h1>
                </div>
                <button type="button" className="btn btn-primary" onClick={openCreate}>
                    <Plus size={16} aria-hidden="true" /> Tambah pengguna
                </button>
            </div>

            <div className="toolbar">
                <div className="field field-grow">
                    <label htmlFor="q">Cari</label>
                    <input id="q" type="search" className="input" placeholder="Nama atau username" value={query} onChange={(event) => setQuery(event.target.value)} />
                </div>
                <div className="field" style={{ maxWidth: 'none' }}>
                    <label>Peran</label>
                    <div className="seg" role="radiogroup" aria-label="Filter peran">
                        {[{ value: '', label: 'Semua' }, ...roles].map((role) => (
                            <label key={role.value} className="seg-opt">
                                <input type="radio" name="role-filter" checked={filters.role === role.value} onChange={() => applyFilters({ role: role.value, page: 1 })} />
                                {role.label}
                            </label>
                        ))}
                    </div>
                </div>
            </div>

            {users.data.length === 0 ? (
                <div className="empty">
                    <h4>Tidak ada pengguna</h4>
                    <p className="text-muted">Tidak ada pengguna yang cocok dengan pencarian ini.</p>
                </div>
            ) : (
                <div className="table-wrap">
                    <table className="table" style={{ minWidth: 720 }}>
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th style={{ width: 120 }}>Peran</th>
                                <th style={{ width: 110 }}>Wali kelas</th>
                                <th style={{ width: 100 }}>Status</th>
                                <th style={{ width: 150 }}>Login terakhir</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => (
                                <tr key={user.id}>
                                    <td>
                                        <span style={{ fontWeight: 600 }}>{user.name}</span>
                                        <span className="cell-sub">@{user.username}</span>
                                    </td>
                                    <td>{user.roleLabel}</td>
                                    <td style={{ fontWeight: 800 }}>{user.homeroomClassroom ?? <span className="text-muted" style={{ fontWeight: 400 }}>—</span>}</td>
                                    <td>
                                        <span className={`tag ${user.isActive ? 'tag-neutral' : 'tag-outline'}`}>{user.isActive ? 'Aktif' : 'Nonaktif'}</span>
                                    </td>
                                    <td className="text-muted" style={{ fontSize: 13 }}>
                                        {user.lastLoginAt ? formatDateTime(user.lastLoginAt) : 'Belum pernah'}
                                    </td>
                                    <td className="cell-actions">
                                        <button type="button" className="btn btn-ghost" onClick={() => openEdit(user)}>
                                            Ubah
                                        </button>
                                        {user.id !== auth.user.id && (
                                            <button type="button" className="btn btn-ghost" onClick={() => setDeleting(user)}>
                                                Hapus
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <Pagination paginator={users} />

            <Modal open={editing !== null} title={editing === 'new' ? 'Tambah pengguna' : `Ubah akun ${editing?.name}`} onClose={() => setEditing(null)}>
                <form onSubmit={submit} noValidate>
                    <Field label="Nama lengkap" htmlFor="name" error={form.errors.name}>
                        <input id="name" className="input" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                    </Field>
                    <div className="form-grid">
                        <Field label="Username" htmlFor="username" error={form.errors.username} hint="Huruf kecil, tanpa spasi">
                            <input id="username" className="input" autoCapitalize="none" autoComplete="off" value={form.data.username} onChange={(event) => form.setData('username', event.target.value.toLowerCase())} />
                        </Field>
                        <Field label="Peran" htmlFor="role" error={form.errors.role}>
                            <select id="role" className="input" value={form.data.role} disabled={isSelf} onChange={(event) => form.setData('role', event.target.value)}>
                                {roles.map((role) => (
                                    <option key={role.value} value={role.value}>
                                        {role.label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    </div>
                    <Field
                        label={editing === 'new' ? 'Password' : 'Password baru'}
                        htmlFor="password"
                        error={form.errors.password}
                        hint={editing === 'new' ? 'Minimal 8 karakter' : 'Kosongkan kalau tidak diganti'}
                    >
                        <input id="password" type="password" className="input" autoComplete="new-password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} />
                    </Field>
                    <div>
                        <label className="radio checkbox">
                            <input type="checkbox" checked={form.data.is_active} disabled={isSelf} onChange={(event) => form.setData('is_active', event.target.checked)} />
                            <span className="dot" />
                            Akun aktif (bisa login)
                        </label>
                        {form.errors.is_active && <div className="field-error">{form.errors.is_active}</div>}
                    </div>
                    {editing !== 'new' && editing?.role === 'wali_kelas' && form.data.role !== 'wali_kelas' && editing.homeroomClassroom && (
                        <p className="alert" style={{ margin: 0 }}>
                            {editing.name} tidak lagi menjadi wali kelas {editing.homeroomClassroom} setelah perannya diganti.
                        </p>
                    )}
                    <div className="dialog-actions">
                        <button type="button" className="btn btn-secondary" onClick={() => setEditing(null)}>
                            Batal
                        </button>
                        <button type="submit" className="btn btn-primary" disabled={form.processing}>
                            Simpan
                        </button>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog open={deleting !== null} title={`Hapus akun ${deleting?.name}?`} confirmLabel="Hapus" onConfirm={destroy} onClose={() => setDeleting(null)}>
                Akun dihapus permanen. Kalau guru hanya berhenti sementara, lebih baik nonaktifkan akunnya.
            </ConfirmDialog>
        </>
    );
}

UsersIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
