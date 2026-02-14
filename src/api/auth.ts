import { apiFetch } from './client';

export type AppRole = 'admin' | 'staff';

export type ApiUser = {
  id: string;
  name: string;
  email: string;
  role: AppRole;
  status: 'active' | 'inactive';
};

export async function apiLogin(email: string, password: string) {
  return apiFetch<{ user: ApiUser; csrfToken: string }>('/auth/login.php', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  });
}

export async function apiMe() {
  return apiFetch<{ user: ApiUser; csrfToken: string }>('/auth/me.php');
}

export async function apiLogout() {
  return apiFetch<{}>('/auth/logout.php', { method: 'POST' });
}
