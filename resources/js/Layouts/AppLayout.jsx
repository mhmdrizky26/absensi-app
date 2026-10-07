import { Link, usePage } from '@inertiajs/react';
import { LogOut, Menu, UserRound, X } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import ProfileModal from '@/Components/ProfileModal';
import { labelStackedTables } from '@/lib/stackTables';

/**
 * Menu navigasi per peran.
 */
const NAV_LINKS = {
    admin: [
        { href: '/dasbor', label: 'Dasbor' },
        { href: '/pantauan', label: 'Pantauan' },
        { href: '/kelas', label: 'Kelas' },
        { href: '/siswa', label: 'Siswa' },
        { href: '/kartu', label: 'Kartu QR' },
        { href: '/izin', label: 'Izin & sakit' },
        { href: '/dispensasi', label: 'Dispensasi', badge: 'pendingDispensations' },
        { href: '/rekap', label: 'Rekap' },
        { href: '/siswa-berisiko', label: 'Siswa berisiko' },
        { href: '/pengguna', label: 'Pengguna' },
        { href: '/tahun-ajaran', label: 'Tahun ajaran' },
        { href: '/pengaturan', label: 'Pengaturan' },
    ],
    guru_piket: [
        { href: '/pantauan', label: 'Pantauan' },
        { href: '/terlambat', label: 'Scan terlambat' },
        { href: '/dispensasi', label: 'Dispensasi', badge: 'pendingDispensations' },
    ],
    guru_bk: [
        { href: '/dasbor', label: 'Dasbor' },
        { href: '/siswa-berisiko', label: 'Siswa berisiko' },
    ],
    wali_kelas: [
        { href: '/dasbor', label: 'Dasbor' },
        { href: '/rekap', label: 'Rekap kelas' },
        { href: '/siswa-berisiko', label: 'Siswa berisiko' },
        { href: '/izin', label: 'Izin & sakit' },
        { href: '/dispensasi', label: 'Dispensasi', badge: 'pendingDispensations' },
    ],
};

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
    const links = NAV_LINKS[user.role] ?? [];
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
                        Absensi
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
                        <button type="button" className="nav-user" title={`${user.name} · ${user.roleLabel} — ganti username atau password`} onClick={() => setProfileOpen(true)}>
                            <span className="avatar" aria-hidden="true">
                                {initials(user.name)}
                            </span>
                            <span className="nav-user-name">
                                {user.name} <span className="text-muted">· {user.roleLabel}</span>
                            </span>
                        </button>
                    ) : (
                        <span className="nav-user" title={`${user.name} · ${user.roleLabel}`}>
                            <span className="avatar" aria-hidden="true">
                                {initials(user.name)}
                            </span>
                            <span className="nav-user-name">
                                {user.name} <span className="text-muted">· {user.roleLabel}</span>
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
                            <span className="text-muted">{user.roleLabel}</span>
                        </div>
                        <NavLinks links={links} url={url} counts={counts} className="nav-drawer-link" onNavigate={() => setMenuOpen(false)} />
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
                )}
            </nav>
            {compact && menuOpen && <div className="nav-scrim" onClick={() => setMenuOpen(false)} />}
            {hasProfile && profileOpen && <ProfileModal user={user} onClose={() => setProfileOpen(false)} />}

            <main ref={mainRef} className="page">
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
