import { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function urlIsActive(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl: string,
) {
    return toUrl(urlToCheck) === currentUrl;
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

/**
 * Get CSRF token from cookie
 * Laravel stores the CSRF token in the XSRF-TOKEN cookie
 */
export const getCsrfToken = (): string | null => {
    const name = 'XSRF-TOKEN=';
    const decodedCookie = decodeURIComponent(document.cookie);
    const ca = decodedCookie.split(';');
    
    for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) === ' ') {
            c = c.substring(1);
        }
        if (c.indexOf(name) === 0) {
            return c.substring(name.length, c.length);
        }
    }
    
    return null;
};

/**
 * Get default headers for Sanctum-authenticated API requests
 * These headers are required for Sanctum SPA authentication to work
 */
export const getApiHeaders = (includeCsrf: boolean = false): HeadersInit => {
    const headers: HeadersInit = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (includeCsrf) {
        const csrfToken = getCsrfToken();
        if (csrfToken) {
            headers['X-XSRF-TOKEN'] = csrfToken;
        }
    }

    return headers;
};

/**
 * Default fetch options for Sanctum-authenticated API requests
 * Includes credentials: 'include' to send cookies with requests
 */
export const getApiFetchOptions = (
    method: string = 'GET',
    body?: BodyInit,
    includeCsrf: boolean = false
): RequestInit => {
    return {
        method,
        headers: getApiHeaders(includeCsrf),
        credentials: 'include',
        ...(body && { body }),
    };
};
