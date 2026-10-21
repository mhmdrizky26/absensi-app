import { useEffect, useState } from 'react';

/**
 * true selama media query cocok, misalnya '(max-width: 960px)' untuk layar HP/tablet.
 * Memakai addListener sebagai cadangan untuk Safari iOS lama.
 */
export function useMediaQuery(query) {
    const [matches, setMatches] = useState(() => typeof window !== 'undefined' && window.matchMedia(query).matches);

    useEffect(() => {
        const list = window.matchMedia(query);
        const update = () => setMatches(list.matches);
        update();

        if (list.addEventListener) {
            list.addEventListener('change', update);

            return () => list.removeEventListener('change', update);
        }

        list.addListener(update);

        return () => list.removeListener(update);
    }, [query]);

    return matches;
}
