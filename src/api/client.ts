export type ApiResult<T> = {
  ok: boolean;
  error?: string;
} & Partial<T>;

const DEFAULT_BASE = 'http://localhost/inventory-system/api';

export const API_BASE = (import.meta as any).env?.VITE_API_BASE_URL || DEFAULT_BASE;

export async function apiFetch<T>(path: string, options: RequestInit = {}): Promise<ApiResult<T>> {
  const url = `${API_BASE}${path.startsWith('/') ? '' : '/'}${path}`;

  const method = (options.method || 'GET').toUpperCase();
  const hasBody = typeof options.body !== 'undefined' && options.body !== null;

  const headers: Record<string, string> = {};
  // Only set JSON content-type when we actually send a body.
  // This prevents unnecessary CORS preflight on simple GET requests.
  if (hasBody && method !== 'GET') {
    headers['Content-Type'] = 'application/json';
  }

  const res = await fetch(url, {
    ...options,
    headers: {
      ...headers,
      ...(options.headers || {}),
    } as any,
    credentials: 'include',
  });

  const text = await res.text();
  let data: any = {};
  try {
    data = text ? JSON.parse(text) : {};
  } catch {
    data = { ok: false, error: 'Invalid server response' };
  }

  if (!res.ok) {
    return { ok: false, error: data?.error || `HTTP ${res.status}` } as ApiResult<T>;
  }

  return (data && typeof data === 'object' ? data : { ok: false, error: 'Invalid server response' }) as ApiResult<T>;
}

export function qs(params: Record<string, any>): string {
  const sp = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v === undefined || v === null || v === '' || v === 'all') return;
    sp.set(k, String(v));
  });
  const s = sp.toString();
  return s ? `?${s}` : '';
}
