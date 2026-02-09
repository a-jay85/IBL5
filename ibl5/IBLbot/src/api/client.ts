import { config } from '../config.js';
import type { ApiResponse, ApiErrorResponse } from './types.js';

export class ApiError extends Error {
    constructor(
        public readonly statusCode: number,
        public readonly errorCode: string,
        message: string,
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

const etagCache = new Map<string, { etag: string; data: unknown }>();

export async function apiGet<T>(
    endpoint: string,
    params?: Record<string, string | number | undefined>,
): Promise<ApiResponse<T>> {
    const url = new URL(`${config.api.baseUrl}/${endpoint}`);

    if (params) {
        for (const [key, value] of Object.entries(params)) {
            if (value !== undefined) {
                url.searchParams.set(key, String(value));
            }
        }
    }

    const urlString = url.toString();
    const headers: Record<string, string> = {
        'X-API-Key': config.api.key,
        'Accept': 'application/json',
    };

    // Add ETag for conditional request
    const cached = etagCache.get(urlString);
    if (cached) {
        headers['If-None-Match'] = cached.etag;
    }

    const response = await fetch(urlString, { headers });

    // 304 Not Modified - return cached data
    if (response.status === 304 && cached) {
        return cached.data as ApiResponse<T>;
    }

    const body = await response.json() as ApiResponse<T> | ApiErrorResponse;

    if (!response.ok || body.status === 'error') {
        const errorBody = body as ApiErrorResponse;
        throw new ApiError(
            response.status,
            errorBody.error?.code ?? 'unknown_error',
            errorBody.error?.message ?? `API request failed with status ${response.status}`,
        );
    }

    // Cache ETag
    const etag = response.headers.get('ETag');
    if (etag) {
        etagCache.set(urlString, { etag, data: body });
    }

    return body as ApiResponse<T>;
}

/**
 * Clear the ETag cache (useful for testing or when data changes)
 */
export function clearCache(): void {
    etagCache.clear();
}
