import { Link } from '@inertiajs/react';

/**
 * Navigasi halaman untuk hasil paginate() Laravel.
 */
export default function Pagination({ paginator }) {
    if (paginator.last_page <= 1) {
        return null;
    }

    return (
        <nav className="pagination" aria-label="Halaman">
            <span className="text-muted">
                {paginator.from}–{paginator.to} dari {paginator.total}
            </span>
            <span className="pagination-links">
                {paginator.links.map((link, index) =>
                    link.url ? (
                        <Link
                            key={index}
                            href={link.url}
                            preserveScroll
                            className={`btn ${link.active ? 'btn-primary' : 'btn-secondary'}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ) : (
                        <span key={index} className="btn btn-secondary" aria-disabled="true" style={{ opacity: 0.45 }} dangerouslySetInnerHTML={{ __html: link.label }} />
                    ),
                )}
            </span>
        </nav>
    );
}
