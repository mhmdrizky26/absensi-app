import { Link, usePage } from '@inertiajs/react';
import { LogOut, Menu, UserRound, X } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import ProfileModal from '@/Components/ProfileModal';
import { dutyLabel, navLinksFor } from '@/lib/duties';
import { labelStackedTables } from '@/lib/stackTables';
import { useLiveUpdates } from '@/lib/useLiveUpdates';

function initials(name) {
    return name
        .split(/\s+/)
        .filter((word) => /^[A-Za-z]/.test(word))
        .slice(0, 2)
        .map((word) => word[0].toUpperCase())
        .join('');
}

function isCurrent(url, href) {
    return url === href || url.startsWith(`${href}/`) || url.startsWith(`${href}?`);
}

function NavLinks({ links, url, counts, className, onNavigate }) {
    return links.map((link) => (
        <Link key={link.href} href={link.href} className={className} aria-current={isCurrent(url, link.href) ? 'page' : undefined} onClick={onNavigate}>
            {link.label}
            {link.badge && counts[link.badge] > 0 && <span className="nav-badge">{counts[link.badge]}</span>}
        </Link>
    ));
}

export default function AppLayout({ children }) {
    const { props, url } = usePage();
    const { auth, flash } = props;
    const user = auth.user;
    const links = navLinksFor(user);
    const userLabel = dutyLabel(user);
    const counts = props.navCounts ?? {};

    const navRef = useRef(null);
    const measureRef = useRef(null);
    const sideRef = useRef(null);
    const brandRef = useRef(null);
    const fullSideWidthRef = useRef(0);
    const mainRef = useRef(null);
    const [compact, setCompact] = useState(false);
    const [menuOpen, setMenuOpen] = useState(false);
    const [profileOpen, setProfileOpen] = useState(false);
    const [toast, setToast] = useState(null);

    useLiveUpdates({
        onNewPendingDispensations: (count) => setToast(count === 1 ? 'Ada 1 pengajuan dispensasi baru yang menunggu persetujuan Anda.' : `Ada ${count} pengajuan dispensasi baru yang menunggu persetujuan Anda.`),
    });

    useEffect(() => {
        if (!toast) {
            return;
        }

        const timer = setTimeout(() => setToast(null), 10000);

        return () => clearTimeout(timer);
    }, [toast]);
    // Admin mengubah akunnya sendiri lewat halaman Pengguna, jadi tanpa modal profil.
    const hasProfile = user.role !== 'admin';

    // Lipat menu menjadi tombol ☰ kalau semua tautan tidak muat dalam satu baris.
    useLayoutEffect(() => {
        const nav = navRef.current;

        function check() {
            // Saat terlipat, nama pengguna disembunyikan sehingga area kanan menyempit.
            // Pakai lebar area kanan dalam keadaan penuh supaya tidak bolak-balik terlipat.
            if (!nav.classList.contains('is-compact')) {
                fullSideWidthRef.current = sideRef.current.offsetWidth;
            }

            const sideWidth = Math.max(sideRef.current.offsetWidth, fullSideWidthRef.current);
            const style = getComputedStyle(nav);
            const padding = parseFloat(style.paddingLeft) + parseFloat(style.paddingRight);
            const gaps = parseFloat(style.columnGap || '0') * 2;
            const available = nav.clientWidth - padding - gaps - brandRef.current.offsetWidth - sideWidth - 8;
            setCompact(measureRef.current.scrollWidth > available);
        }

        check();
        const observer = new ResizeObserver(check);
        observer.observe(nav);
        // Ukur ulang setelah font Archivo termuat: teksnya sedikit lebih lebar dari font cadangan.
        document.fonts?.ready.then(check);

        return () => observer.disconnect();
    }, [links.length]);

    useEffect(() => setMenuOpen(false), [url]);

    useEffect(() => {
        if (!menuOpen) {
            return;
        }

        const close = (event) => event.key === 'Escape' && setMenuOpen(false);
        document.addEventListener('keydown', close);

        return () => document.removeEventListener('keydown', close);
    }, [menuOpen]);

    // Tabel berubah menjadi kartu di layar sempit; tiap sel diberi label kolomnya.
    useEffect(() => {
        const main = mainRef.current;
        labelStackedTables(main);
        const observer = new MutationObserver(() => labelStackedTables(main));
        observer.observe(main, { childList: true, subtree: true });

        return () => observer.disconnect();
    }, []);

    return (
        <div className="app-shell">
            <nav ref={navRef} className={`nav nav-app ${compact ? 'is-compact' : ''}`}>
                <span ref={brandRef} className="nav-brand-wrap">
                    <Link href={links[0]?.href ?? '/dasbor'} className="nav-brand">
                        {props.appName}
                    </Link>
                </span>

                <div className="nav-links" aria-hidden={compact || undefined}>
                    <NavLinks links={links} url={url} counts={counts} />
                </div>
                <div ref={measureRef} className="nav-links nav-measure" aria-hidden="true">
                    <NavLinks links={links} url={url} counts={counts} />
                </div>

                <div ref={sideRef} className="nav-side">
                    {hasProfile ? (
                        <button type="button" className="nav-user" title={`${user.name} · ${userLabel} — ganti username atau password`} onClick={() => setProfileOpen(true)}>
                            <span className="avatar" aria-hidden="true">
                                {initials(user.name)}
                            </span>
                            <span className="nav-user-name">
                                {user.name} <span className="text-muted">· {userLabel}</span>
                            </span>
                        </button>
                    ) : (
                        <span className="nav-user" title={`${user.name} · ${userLabel}`}>
                            <span className="avatar" aria-hidden="true">
                                {initials(user.name)}
                            </span>
                            <span className="nav-user-name">
                                {user.name} <span className="text-muted">· {userLabel}</span>
                            </span>
                        </span>
                    )}
                    {compact ? (
                        <button type="button" className="btn btn-secondary nav-menu-button" aria-expanded={menuOpen} aria-controls="nav-drawer" onClick={() => setMenuOpen((open) => !open)}>
                            {menuOpen ? <X size={18} aria-hidden="true" /> : <Menu size={18} aria-hidden="true" />}
                            <span>Menu</span>
                            {!menuOpen && Object.values(counts).some((count) => count > 0) && <span className="nav-dot" aria-label="Ada yang perlu ditindaklanjuti" />}
                        </button>
                    ) : (
                        <Link href="/keluar" method="post" as="button" className="btn btn-secondary nav-logout" aria-label="Keluar">
                            <span className="nav-logout-label">Keluar</span>
                            <LogOut size={16} aria-hidden="true" />
                        </Link>
                    )}
                </div>

                {compact && menuOpen && (
                    <div id="nav-drawer" className="nav-drawer">
                        <div className="nav-drawer-user">
                            <b>{user.name}</b>
                            <span className="text-muted">{userLabel}</span>
                        </div>
                        <div className="nav-drawer-links">
                            <NavLinks links={links} url={url} counts={counts} className="nav-drawer-link" onNavigate={() => setMenuOpen(false)} />
                        </div>
                        <div className="nav-drawer-actions">
                            {hasProfile && (
                                <button
                                    type="button"
                                    className="nav-drawer-link"
                                    onClick={() => {
                                        setMenuOpen(false);
                                        setProfileOpen(true);
                                    }}
                                >
                                    Profil <UserRound size={16} aria-hidden="true" />
                                </button>
                            )}
                            <Link href="/keluar" method="post" as="button" className="nav-drawer-link nav-drawer-logout">
                                Keluar <LogOut size={16} aria-hidden="true" />
                            </Link>
                        </div>
                    </div>
                )}
            </nav>
            {compact && menuOpen && <div className="nav-scrim" onClick={() => setMenuOpen(false)} />}
            {toast && (
                <div className="live-toast" role="status">
                    <span>{toast}</span>
                    <Link href="/dispensasi" className="btn btn-primary" onClick={() => setToast(null)}>
                        Buka
                    </Link>
                    <button type="button" className="btn btn-ghost" aria-label="Tutup" onClick={() => setToast(null)}>
                        <X size={16} aria-hidden="true" />
                    </button>
                </div>
            )}
            {hasProfile && profileOpen && <ProfileModal user={user} onClose={() => setProfileOpen(false)} />}

            <main ref={mainRef} className="page">
                {props.serverWarnings?.length > 0 && (
                    <div className="alert alert-error" role="alert" style={{ marginBottom: 16 }}>
                        <b>Pengaturan server belum aman.</b>
                        <ul style={{ margin: '6px 0 0', paddingLeft: 18 }}>
                            {props.serverWarnings.map((warning) => (
                                <li key={warning}>{warning}</li>
                            ))}
                        </ul>
                    </div>
                )}
                {props.demoMode && (
                    <p className="alert alert-warning demo-banner" role="note">
                        <b>Mode demo aktif.</b> Jadwal gerbang tidak dipakai dan siswa tidak pernah tercatat Terlambat.
                        {user.role === 'admin' ? (
                            <>
                                {' '}
                                Matikan di <Link href="/pengaturan">Pengaturan</Link> sebelum dipakai sungguhan.
                            </>
                        ) : (
                            ' Minta admin mematikannya sebelum dipakai sungguhan.'
                        )}
                    </p>
                )}
                {flash.success && (
                    <p className="alert" role="status">
                        {flash.success}
                    </p>
                )}
                {flash.error && (
                    <p className="alert alert-error" role="alert">
                        {flash.error}
                    </p>
                )}
                {children}
            </main>
        </div>
    );
}
