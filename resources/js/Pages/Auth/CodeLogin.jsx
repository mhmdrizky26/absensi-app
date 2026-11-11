import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';
import Modal from '@/Components/Modal';
import SecretInput from '@/Components/SecretInput';

const today = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date());

/**
 * Satu layar untuk dua jenis kode: kode tab (TAB1) membuka scanner gerbang,
 * kode kelas (7A) menampilkan kehadiran kelas hari ini untuk guru mapel.
 */
export default function CodeLogin() {
    const { appName, schoolName, flash } = usePage().props;
    const { data, setData, post, processing, errors, reset } = useForm({ code: '' });
    // Tab gerbang baru saja dikeluarkan, atau login ditolak, karena gerbang sudah ditutup.
    const [closedMessage, setClosedMessage] = useState(flash.gateClosed);

    function submit(event) {
        event.preventDefault();
        post('/kode', {
            onSuccess: (page) => {
                if (page.props.flash.gateClosed) {
                    setClosedMessage(page.props.flash.gateClosed);
                    reset('code');
                }
            },
        });
    }

    return (
        <>
            <Head title="Masukkan kode" />

            <div className="auth auth-class">
                <div className="auth-poster auth-poster-accent">
                    <div className="auth-brand">{appName}</div>
                    <div>
                        <div className="auth-kicker">Tab gerbang · Guru mapel</div>
                        <h1 className="auth-headline">Masukkan kode.</h1>
                    </div>
                    <div className="auth-poster-foot">
                        <span>{schoolName}</span>
                        <span>{today}</span>
                    </div>
                </div>

                <div className="auth-panel">
                    <form className="auth-form" onSubmit={submit} noValidate>
                        <div>
                            <h2 style={{ margin: '0 0 8px' }}>Masukkan kode</h2>
                            <p className="text-muted" style={{ margin: 0, fontSize: 14 }}>
                                Kode tab untuk scan di gerbang, kode kelas untuk melihat kehadiran kelas hari ini.
                            </p>
                        </div>

                        {flash.error && !errors.code && (
                            <p className="alert alert-error" role="alert" style={{ margin: 0 }}>
                                {flash.error}
                            </p>
                        )}

                        <div className="field">
                            <label htmlFor="code">Kode</label>
                            <SecretInput
                                id="code"
                                label="kode"
                                autoCapitalize="characters"
                                className="input code-input"
                                maxLength={30}
                                autoFocus
                                value={data.code}
                                aria-invalid={errors.code ? 'true' : undefined}
                                onChange={(event) => setData('code', event.target.value.toUpperCase().replace(/\s+/g, ''))}
                            />
                            {errors.code && <div className="field-error">{errors.code}</div>}
                        </div>

                        <button type="submit" className="btn btn-primary auth-submit" disabled={processing || data.code.length < 1}>
                            {processing ? 'Membuka…' : 'Masuk'}
                            <ArrowRight size={18} strokeWidth={2.25} aria-hidden="true" />
                        </button>

                        <div className="auth-foot" style={{ display: 'flex', justifyContent: 'space-between', gap: 12 }}>
                            <span className="text-muted">Guru piket, wali kelas, atau admin?</span>
                            <Link href="/masuk">Masuk staf</Link>
                        </div>
                    </form>
                </div>
            </div>

            <Modal open={Boolean(closedMessage)} title="Scan sudah ditutup" onClose={() => setClosedMessage(null)}>
                <p style={{ marginTop: 0 }}>{closedMessage}</p>
                <div className="dialog-actions">
                    <button type="button" className="btn btn-primary" onClick={() => setClosedMessage(null)}>
                        Mengerti
                    </button>
                </div>
            </Modal>
        </>
    );
}
