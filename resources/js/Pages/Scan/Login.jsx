import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

const today = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date());

export default function ClassLogin({ opensAt, closesAt, closedReason }) {
    const { appName, schoolName, flash } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({ code: '' });

    function submit(event) {
        event.preventDefault();
        post('/absen');
    }

    return (
        <>
            <Head title="Absen kelas" />

            <div className="auth auth-class">
                <div className="auth-poster auth-poster-accent">
                    <div className="auth-brand">{appName}</div>
                    <div>
                        <div className="auth-kicker">Scan absensi kelas</div>
                        <h1 className="auth-headline">Buka absensi kelas.</h1>
                    </div>
                    <div className="auth-poster-foot">
                        <span>{schoolName}</span>
                        <span>{today}</span>
                    </div>
                </div>

                <div className="auth-panel">
                    <form className="auth-form" onSubmit={submit} noValidate>
                        <div>
                            <h2 style={{ margin: '0 0 8px' }}>Kode kelas</h2>
                            <p className="text-muted" style={{ margin: 0, fontSize: 14 }}>
                                Guru jam pertama memasukkan kode kelas dari admin, lalu scanner QR untuk absensi hari ini langsung terbuka. Absensi kelas dibuka pukul {opensAt}–{closesAt}.
                            </p>
                        </div>

                        {closedReason && !errors.code && (
                            <p className="alert" style={{ margin: 0 }}>
                                {closedReason}
                            </p>
                        )}
                        {flash.error && !errors.code && (
                            <p className="alert alert-error" role="alert" style={{ margin: 0 }}>
                                {flash.error}
                            </p>
                        )}

                        <div className="field">
                            <label htmlFor="code">Kode kelas</label>
                            <input
                                id="code"
                                className="input code-input"
                                autoComplete="off"
                                autoCapitalize="characters"
                                autoCorrect="off"
                                spellCheck={false}
                                maxLength={12}
                                placeholder="••••••"
                                autoFocus
                                value={data.code}
                                aria-invalid={errors.code ? 'true' : undefined}
                                onChange={(event) => setData('code', event.target.value.toUpperCase().replace(/[^A-Z0-9]/g, ''))}
                            />
                            {errors.code && <div className="field-error">{errors.code}</div>}
                        </div>

                        <button type="submit" className="btn btn-primary auth-submit" disabled={processing || data.code.length < 4}>
                            {processing ? 'Membuka…' : 'Buka scanner'}
                            <ArrowRight size={18} strokeWidth={2.25} aria-hidden="true" />
                        </button>

                        <div className="auth-foot" style={{ display: 'flex', justifyContent: 'space-between', gap: 12 }}>
                            <span className="text-muted">Guru piket, wali kelas, atau admin?</span>
                            <Link href="/masuk">Masuk staf</Link>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}
