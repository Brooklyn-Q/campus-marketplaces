/**
 * Campus Marketplace — API Service Layer
 * All frontend API calls go through this module.
 * Replaces direct PHP database access with REST API calls.
 */

function getDefaultApiBase(): string {
  if (typeof window === 'undefined') {
    return '/backend/api';
  }

  const { origin, hostname, pathname } = window.location;

  if (hostname === 'localhost' || hostname === '127.0.0.1') {
    const localBase = pathname.startsWith('/marketplace/') ? '/marketplace' : '';
    return `${origin}${localBase}/backend/api`;
  }

  return `${origin}/backend/api`;
}

const API_BASE =
  (((import.meta as any).env.VITE_API_URL as string | undefined)?.replace(/\/$/, '')) ||
  getDefaultApiBase();

// ── Token Management ──

export function getToken(): string | null {
  return localStorage.getItem('cm_token');
}

export function setToken(token: string): void {
  localStorage.setItem('cm_token', token);
}

export function removeToken(): void {
  localStorage.removeItem('cm_token');
}

// ── Core Fetch Wrapper ──

export async function apiFetch<T = any>(
  endpoint: string,
  options: RequestInit = {}
): Promise<T> {
  const token = getToken();
  const headers: Record<string, string> = {
    ...((options.headers as Record<string, string>) || {}),
  };

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  // Don't set Content-Type for FormData (browser sets it with boundary)
  if (!(options.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
  }

  const normalizedEndpoint = endpoint.replace(/^\/+/, '');
  const res = await fetch(`${API_BASE}/${normalizedEndpoint}`, {
    ...options,
    headers,
  });

  // Handle 401 - token expired
  if (res.status === 401) {
    removeToken();
    document.dispatchEvent(new Event('auth_unauthorized'));
    throw new Error('Authentication required');
  }

  const contentType = res.headers.get('content-type') || '';
  const isJson = contentType.includes('application/json');
  const rawBody = res.status === 204 ? '' : await res.text();
  const data = rawBody ? (isJson ? JSON.parse(rawBody) : rawBody) : null;

  if (!res.ok) {
    if (isJson && data && typeof data === 'object') {
      throw new Error((data as any).error || (data as any).message || `API Error ${res.status}`);
    }

    throw new Error(
      (typeof data === 'string' && data.trim()) || `API Error ${res.status}`
    );
  }

  return data as T;
}

// ── AUTH API ──

export const auth = {
  login: (identifier: string, password: string) =>
    apiFetch('auth/login', {
      method: 'POST',
      body: JSON.stringify({ email: identifier, username: identifier, password }),
    }),

  register: (formData: FormData) =>
    apiFetch('auth/register', {
      method: 'POST',
      body: formData,
    }),

  me: () => apiFetch('auth/me'),

  acceptTerms: () =>
    apiFetch('auth/accept-terms', { method: 'POST' }),
};

// ── PRODUCTS API ──

export const products = {
  list: (params?: Record<string, string>) => {
    const qs = params ? '?' + new URLSearchParams(params).toString() : '';
    return apiFetch(`products${qs}`);
  },

  get: (id: number) => apiFetch(`products/${id}`),

  create: (formData: FormData) =>
    apiFetch('products', {
      method: 'POST',
      body: formData,
    }),

  update: (id: number, data: any) =>
    apiFetch(`products/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    }),

  delete: (id: number) =>
    apiFetch(`products/${id}`, { method: 'DELETE' }),

  myProducts: () => apiFetch('products/my'),
};

// ── ORDERS API ──

export const orders = {
  list: () => apiFetch('orders'),

  create: (productId: number, quantity: number = 1) =>
    apiFetch('orders', {
      method: 'POST',
      body: JSON.stringify({ product_id: productId, quantity }),
    }),

  accept: (orderId: number, deliveryNote: string = '') =>
    apiFetch(`orders/${orderId}/accept`, {
      method: 'PUT',
      body: JSON.stringify({ delivery_note: deliveryNote }),
    }),

  confirmSold: (orderId: number) =>
    apiFetch(`orders/${orderId}/confirm-sold`, { method: 'PUT' }),

  confirmReceived: (orderId: number) =>
    apiFetch(`orders/${orderId}/confirm-received`, { method: 'PUT' }),

  dispute: (orderId: number, reason: string) =>
    apiFetch(`orders/${orderId}/dispute`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    }),
};

// ── MESSAGES API ──

export const messages = {
  conversations: () => apiFetch('messages/conversations'),

  getThread: (userId: number) => apiFetch(`messages/${userId}`),

  send: (receiverId: number, message: string) =>
    apiFetch('messages', {
      method: 'POST',
      body: JSON.stringify({ receiver_id: receiverId, message }),
    }),

  sendMedia: (formData: FormData) =>
    apiFetch('messages', {
      method: 'POST',
      body: formData,
    }),
};

// ── REVIEWS API ──

export const reviews = {
  get: (productId: number) => apiFetch(`reviews/${productId}`),

  create: (productId: number, rating: number, comment: string) =>
    apiFetch(`products/${productId}/review`, {
      method: 'POST',
      body: JSON.stringify({ rating, comment }),
    }),
};

// ── USERS API ──

export const users = {
  get: (id: number) => apiFetch(`users/${id}`),

  updateProfile: (data: any) =>
    apiFetch('users/profile', {
      method: 'PUT',
      body: JSON.stringify(data),
    }),

  uploadProfilePic: (formData: FormData) =>
    apiFetch('users/profile-pic', {
      method: 'POST',
      body: formData,
    }),

  requestVacation: () =>
    apiFetch('users/vacation', { method: 'POST' }),

  endVacation: () =>
    apiFetch('users/vacation', { method: 'DELETE' }),
};

// ── SETTINGS API ──

export const settings = {
  tiers: () => apiFetch('settings/tiers'),
};

// ── PAYMENTS API ──

export const payments = {
  initialize: (type: 'pro' | 'premium' | 'deposit', amount?: number) =>
    apiFetch('payments/initialize', {
      method: 'POST',
      body: JSON.stringify({ type, amount }),
    }),

  verify: (reference: string) =>
    apiFetch(`payments/verify/${reference}`, {
      method: 'GET',
    }),

  transactions: () => apiFetch('payments/transactions'),
};

// ── SEARCH API ──

export const search = {
  query: (q: string) => apiFetch(`search?q=${encodeURIComponent(q)}`),

  suggest: (q: string) =>
    apiFetch(`search/suggest?q=${encodeURIComponent(q)}`),
};

// ── RECOMMENDATIONS API ──

export const recommendations = {
  get: (context?: string) => {
    const qs = context ? `?context=${encodeURIComponent(context)}` : '';
    return apiFetch(`recommendations${qs}`);
  },
};

// ── LEADERBOARD API ──

export const leaderboard = {
  get: () => apiFetch('leaderboard'),
};

// ── ADS API ──

export const ads = {
  list: (placement?: string) => {
    const qs = placement ? `?placement=${encodeURIComponent(placement)}` : '';
    return apiFetch(`ads${qs}`);
  },

  click: (id: number) =>
    apiFetch(`ads/${id}/click`, { method: 'POST' }),
};

// ── NOTIFICATIONS API ──

export const notifications = {
  list: () => apiFetch('notifications'),

  poll: () => apiFetch('notifications/poll'),

  markRead: (id: number) =>
    apiFetch(`notifications/${id}/read`, { method: 'POST' }),
};

// ── AI API ──

export const ai = {
  chat: (message: string, history: any[] = []) =>
    apiFetch('ai/chat', {
      method: 'POST',
      body: JSON.stringify({ message, history }),
    }),
};

// ── UPLOAD API ──

export const upload = {
  file: (formData: FormData) =>
    apiFetch('upload', {
      method: 'POST',
      body: formData,
    }),
};

// ── ADMIN API ──

export const admin = {
  dashboard: () => apiFetch('admin/dashboard'),

  users: {
    list: () => apiFetch('admin/users'),
    suspend: (id: number) =>
      apiFetch(`admin/users/${id}/suspend`, { method: 'POST' }),
    unsuspend: (id: number) =>
      apiFetch(`admin/users/${id}/unsuspend`, { method: 'POST' }),
  },

  products: {
    list: () => apiFetch('admin/products'),
    approve: (id: number) =>
      apiFetch(`admin/products/${id}/approve`, { method: 'POST' }),
    reject: (id: number) =>
      apiFetch(`admin/products/${id}/reject`, { method: 'POST' }),
  },

  moderation: {
    pending: () => apiFetch('admin/moderation'),
  },

  ads: {
    list: () => apiFetch('admin/ads'),
    create: (formData: FormData) => apiFetch('admin/ads', { method: 'POST', body: formData }),
    toggle: (id: number) => apiFetch(`admin/ads/${id}/toggle`, { method: 'PUT' }),
    delete: (id: number) => apiFetch(`admin/ads/${id}`, { method: 'DELETE' }),
  },
};

// Default export as namespace
const api = {
  auth,
  products,
  orders,
  messages,
  reviews,
  users,
  payments,
  search,
  recommendations,
  leaderboard,
  ads,
  notifications,
  ai,
  upload,
  admin,
  getToken,
  setToken,
  removeToken,
};

export default api;
