import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

export default function Login() {
    const { appName } = usePage().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        username: '',
        password: '',
        remember: true,
    });

    function submit(event) {
        event.preventDefault();
        post('/masuk', {
            onFinish: () => reset('password'),
        });
    }

    return (
        <>
            <Head title="Masuk" />

            <div className="auth">
                <div className="auth-poster">
                    <div className="auth-brand">{appName}</div>
                    <div>
                        <div className="auth-kicker">Guru &amp; staf</div>
                        <h1 className="auth-headline">Setiap siswa, tercatat.</h1>
                    </div>
                    <div className="auth-facts">
                        <div>
                            <strong>VII–IX</strong>
                            tiga angkatan
                        </div>
                        <div>
                            <strong>QR</strong>
                            satu kartu per siswa
                        </div>
                        <div>
                            <strong>5</strong>
                            status: H, T, S, I, A
                        </div>
                    </div>
                </div>

                <div className="auth-panel">
                    <form className="auth-form" onSubmit={submit} noValidate>
                        <h2 style={{ margin: 0 }}>Masuk staf</h2>

                        {errors.username && (
                            <p className="alert alert-error" role="alert" style={{ margin: 0 }}>
                                {errors.username}
                            </p>
                        )}

                        <div className="field">
                            <label htmlFor="username">Username</label>
                            <input
                                id="username"
                                className="input"
                                type="text"
                                autoComplete="username"
                                autoCapitalize="none"
                                autoFocus
                                value={data.username}
                                aria-invalid={errors.username ? 'true' : undefined}
                                onChange={(event) => setData('username', event.target.value)}
                            />
                        </div>

                        <div className="field">
                            <label htmlFor="password">Password</label>
                            <input
                                id="password"
                                className="input"
                                type="password"
                                autoComplete="current-password"
                                value={data.password}
                                aria-invalid={errors.password ? 'true' : undefined}
                                onChange={(event) => setData('password', event.target.value)}
                            />
                            {errors.password && <div className="field-error">{errors.password}</div>}
                        </div>

                        <label className="radio checkbox">
                            <input
                                type="checkbox"
                                checked={data.remember}
                                onChange={(event) => setData('remember', event.target.checked)}
                            />
                            <span className="dot" />
                            Tetap masuk di perangkat ini
                        </label>

                        <button type="submit" className="btn btn-primary auth-submit" disabled={processing}>
                            {processing ? 'Memproses…' : 'Masuk'}
                            <ArrowRight size={18} strokeWidth={2.25} aria-hidden="true" />
                        </button>

                        <p className="text-muted" style={{ margin: 0, fontSize: 13 }}>
                            Lupa password? Hubungi admin sekolah untuk mengatur ulang.
                        </p>

                        <div className="auth-foot" style={{ display: 'flex', justifyContent: 'space-between', gap: 12 }}>
                            <span className="text-muted">Mengabsen kelas jam pertama?</span>
                            <Link href="/absen">Masuk dengan kode kelas</Link>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}
