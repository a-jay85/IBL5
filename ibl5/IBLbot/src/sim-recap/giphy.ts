// GIPHY search for the closing GIF. Every failure path returns null so the drip still finishes.
// No log line ever includes the request URL or the key.

type FetchFn = typeof fetch;

const GIPHY_SEARCH_URL = 'https://api.giphy.com/v1/gifs/search';
const GIPHY_TIMEOUT_MS = 5000;

export async function searchGif(
    query: string,
    apiKey: string | undefined,
    fetchFn: FetchFn = fetch,
    rand: () => number = Math.random,
    log: (line: string) => void = console.log,
): Promise<string | null> {
    if (apiKey === undefined || apiKey === '') {
        log('sim-recap: GIPHY_API_KEY unset; skipping GIF');
        return null;
    }

    const params = new URLSearchParams({ api_key: apiKey, q: query, limit: '25', rating: 'pg' });
    try {
        const response = await fetchFn(`${GIPHY_SEARCH_URL}?${params.toString()}`, {
            signal: AbortSignal.timeout(GIPHY_TIMEOUT_MS),
        });
        if (!response.ok) {
            log(`sim-recap: giphy http ${response.status}`);
            return null;
        }
        const body = await response.json() as { data?: Array<{ url?: unknown }> };
        const data = Array.isArray(body.data) ? body.data : [];
        if (data.length === 0) {
            log('sim-recap: giphy returned no results');
            return null;
        }
        const url = data[Math.floor(rand() * data.length)]?.url;
        if (typeof url !== 'string' || url === '') {
            log('sim-recap: giphy result had no url');
            return null;
        }
        return url;
    } catch {
        log('sim-recap: giphy request failed');
        return null;
    }
}
