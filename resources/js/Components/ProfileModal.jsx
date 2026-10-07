import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import Field from '@/Components/Field';
import Modal from '@/Components/Modal';

/**
 * Profil akun yang sedang masuk: ganti username atau password, dibuka dari nama di menu atas.
 * Dipasang ulang setiap kali dibuka sehingga isiannya selalu bersih.
 */
export default function ProfileModal({ user, onClose }) {
    const [tab, setTab] = useState('username');
    const usernameForm = useForm({ username: user.username, current_password: '' });
    const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });

    function submitUsername(event) {
        event.preventDefault();
        usernameForm.put('/profil/username', {
            preserveScroll: true,
            onSuccess: onClose,
            onError: () => usernameForm.reset('current_password'),
        });
    }

    function submitPassword(event) {
        event.preventDefault();
        passwordForm.put('/profil/password', {
            preserveScroll: true,
            onSuccess: onClose,
            onError: () => passwordForm.reset(),
        });
    }

    return (
        <Modal open title="Profil" onClose={onClose}>
            <p className="text-muted" style={{ margin: '0 0 16px', fontSize: 14 }}>
                {user.name} · {user.roleLabel}. Nama dan peran diatur oleh admin.
            </p>

            <div className="seg seg-fill" role="radiogroup" aria-label="Yang ingin diganti" style={{ marginBottom: 20 }}>
                {[
                    ['username', 'Username'],
                    ['password', 'Password'],
                ].map(([value, label]) => (
                    <label key={value} className="seg-opt">
                        <input type="radio" name="profile-tab" checked={tab === value} onChange={() => setTab(value)} />
                        {label}
                    </label>
                ))}
            </div>

            {tab === 'username' ? (
                <form onSubmit={submitUsername} noValidate>
                    <Field label="Username baru" htmlFor="profile_username" error={usernameForm.errors.username} hint="Huruf kecil, angka, titik, strip, atau garis bawah. Minimal 3 karakter.">
                        <input
                            id="profile_username"
                            className="input"
                            autoComplete="username"
                            autoCapitalize="none"
                            spellCheck={false}
                            maxLength={50}
                            value={usernameForm.data.username}
                            onChange={(event) => usernameForm.setData('username', event.target.value.toLowerCase())}
                        />
                    </Field>
                    <Field label="Password saat ini" htmlFor="profile_username_password" error={usernameForm.errors.current_password} hint="Untuk memastikan yang mengganti adalah pemilik akun.">
                        <input id="profile_username_password" type="password" className="input" autoComplete="current-password" value={usernameForm.data.current_password} onChange={(event) => usernameForm.setData('current_password', event.target.value)} />
                    </Field>
                    <div className="dialog-actions">
                        <button type="button" className="btn btn-secondary" onClick={onClose}>
                            Batal
                        </button>
                        <button type="submit" className="btn btn-primary" disabled={usernameForm.processing || usernameForm.data.username === user.username || !usernameForm.data.current_password}>
                            {usernameForm.processing ? 'Menyimpan…' : 'Simpan username'}
                        </button>
                    </div>
                </form>
            ) : (
                <form onSubmit={submitPassword} noValidate>
                    <Field label="Password saat ini" htmlFor="profile_current_password" error={passwordForm.errors.current_password}>
                        <input id="profile_current_password" type="password" className="input" autoComplete="current-password" value={passwordForm.data.current_password} onChange={(event) => passwordForm.setData('current_password', event.target.value)} />
                    </Field>
                    <Field label="Password baru" htmlFor="profile_password" error={passwordForm.errors.password} hint={'Minimal 8 karakter. Perangkat lain yang memakai "Tetap masuk" akan diminta masuk ulang.'}>
                        <input id="profile_password" type="password" className="input" autoComplete="new-password" maxLength={72} value={passwordForm.data.password} onChange={(event) => passwordForm.setData('password', event.target.value)} />
                    </Field>
                    <Field label="Ulangi password baru" htmlFor="profile_password_confirmation" error={passwordForm.errors.password_confirmation}>
                        <input id="profile_password_confirmation" type="password" className="input" autoComplete="new-password" maxLength={72} value={passwordForm.data.password_confirmation} onChange={(event) => passwordForm.setData('password_confirmation', event.target.value)} />
                    </Field>
                    <div className="dialog-actions">
                        <button type="button" className="btn btn-secondary" onClick={onClose}>
                            Batal
                        </button>
                        <button type="submit" className="btn btn-primary" disabled={passwordForm.processing || !passwordForm.data.current_password || !passwordForm.data.password}>
                            {passwordForm.processing ? 'Menyimpan…' : 'Simpan password'}
                        </button>
                    </div>
                </form>
            )}
        </Modal>
    );
}
