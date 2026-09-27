import { Link, usePage } from '@inertiajs/react';

/**
 * Pindah antar jenis rekap. Rekap sekolah hanya untuk admin.
 */
export default function RecapTabs({ current, query = '' }) {
    const { auth } = usePage().props;
    const tabs = [
        { key: 'monthly', href: '/rekap', label: 'Bulanan' },
        { key: 'semester', href: '/rekap/semester', label: 'Semester (rapor)' },
        ...(auth.user.role === 'admin' ? [{ key: 'school', href: '/rekap/sekolah', label: 'Sekolah' }] : []),
    ];

    return (
        <div className="seg no-print" role="tablist" aria-label="Jenis rekap">
            {tabs.map((tab) => (
                <Link key={tab.key} href={`${tab.href}${tab.key !== 'school' ? query : ''}`} className="seg-opt" role="tab" aria-selected={tab.key === current} data-active={tab.key === current || undefined}>
                    {tab.label}
                </Link>
            ))}
        </div>
    );
}
