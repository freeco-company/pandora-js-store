const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';
const STORAGE_URL = process.env.NEXT_PUBLIC_STORAGE_URL || 'http://localhost:8000';

/** Resolve image path to full URL (handles /storage/... paths from API) */
export function imageUrl(path: string | null): string | null {
  if (!path) return null;
  if (path.startsWith('http')) return path;
  // Normalize: ensure path starts with /storage/
  if (!path.startsWith('/storage/')) {
    path = '/storage/' + path.replace(/^\//, '');
  }
  return `${STORAGE_URL}${path}`;
}

export async function fetchApi<T>(endpoint: string, options?: RequestInit): Promise<T> {
  const res = await fetch(`${API_URL}${endpoint}`, {
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    ...options,
  });
  if (!res.ok) throw new Error(`API error: ${res.status}`);
  return res.json();
}

/**
 * Server-Component friendly GET with sensible defaults:
 *  - `revalidate: 300` (5-min soft cache) so ISR pages don't hammer Laravel
 *  - `tags` allow on-demand revalidation via revalidateTag() from the backend
 *    side (if we ever wire that up)
 * Don't use this from client components — Next.js ignores the cache opts
 * there and you'd pay for the serialization overhead.
 */
async function getPublic<T>(
  endpoint: string,
  { revalidate = 300, tags = [] as string[] } = {},
): Promise<T> {
  const res = await fetch(`${API_URL}${endpoint}`, {
    headers: { Accept: 'application/json' },
    next: { revalidate, tags },
  });
  if (!res.ok) throw new Error(`API error: ${res.status}`);
  return res.json();
}

// Customer types
export interface Customer {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  membership_level: string;
}

// Product types
export interface Product {
  id: number;
  name: string;
  slug: string;
  description: string;
  short_description: string;
  price: number;
  combo_price: number | null;
  vip_price: number | null;
  image: string | null;
  gallery: string[] | null;
  is_active: boolean;
  stock_status: 'instock' | 'outofstock';
  categories: ProductCategory[];
  seo_meta: SeoMeta | null;
  created_at?: string;
}

export interface ProductCategory {
  id: number;
  name: string;
  slug: string;
  products_count?: number;
}

export interface Article {
  id: number;
  title: string;
  slug: string;
  content: string;
  excerpt: string;
  featured_image: string | null;
  source_type: string;
  published_at: string;
  seo_meta: SeoMeta | null;
}

export interface SeoMeta {
  title: string;
  description: string;
  og_image: string;
}

export interface CartItem {
  product_id: number;
  quantity: number;
}

export interface CartCalculation {
  tier: 'regular' | 'combo' | 'vip';
  total: number;
  items: CartCalculationItem[];
}

export interface CartCalculationItem {
  product_id: number;
  name: string;
  quantity: number;
  original_price: number;
  unit_price: number;
  subtotal: number;
  image: string | null;
}

export const getProducts = (category?: string) =>
  getPublic<Product[]>(`/products${category ? `?category=${category}` : ''}`, { tags: ['products'] });

export const getProduct = (slug: string) =>
  getPublic<Product>(`/products/${slug}`, { tags: ['products', `product:${slug}`] });

export const getProductCategories = () =>
  getPublic<ProductCategory[]>('/product-categories', { revalidate: 3600, tags: ['product-categories'] });

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
}

export const getArticles = (type?: string, page = 1, perPage = 12) =>
  getPublic<PaginatedResponse<Article>>(
    `/articles?per_page=${perPage}&page=${page}${type ? `&type=${type}` : ''}`,
    { tags: ['articles'] },
  );

export const getArticle = (slug: string) =>
  getPublic<Article>(`/articles/${slug}`, { tags: ['articles', `article:${slug}`] });

export const calculateCart = (items: CartItem[]) =>
  fetchApi<CartCalculation>('/cart/calculate', {
    method: 'POST',
    body: JSON.stringify({ items }),
  });

// Banner types
export interface Banner {
  id: number;
  title: string;
  image: string;
  mobile_image: string | null;
  link: string | null;
}

export const getBanners = () => getPublic<Banner[]>('/banners', { revalidate: 900, tags: ['banners'] });

// Popup types
export interface Popup {
  id: number;
  title: string;
  image: string | null;
  link: string | null;
  content: string | null;
  display_frequency: 'once' | 'every_visit' | 'once_per_day';
}

export const getPopups = () => getPublic<Popup[]>('/popups', { revalidate: 900, tags: ['popups'] });

// Search
export const searchProducts = (q: string) =>
  fetchApi<Product[]>(`/products?q=${encodeURIComponent(q)}`);


// ─── Gamification ────────────────────────────────────────────────────────────

export interface CelebrationKeys {
  _achievement?: string | null;
  _achievements?: string[] | null;
  _outfits?: string[] | null;
  _serendipity?: { message: string; emoji: string } | null;
}

export interface CustomerGamificationState {
  id: number;
  name: string;
  email: string;
  streak_days: number;
  total_orders: number;
  total_spent: number;
  current_outfit: string | null;
  current_backdrop: string | null;
  activation_progress: {
    first_browse?: boolean;
    first_article?: boolean;
    first_brand?: boolean;
    first_cart?: boolean;
    first_mascot?: boolean;
    first_order?: boolean;
  };
}

export interface CustomerDashboard extends CelebrationKeys {
  customer: CustomerGamificationState;
  achievements: {
    earned: Array<{ code: string; awarded_at: string }>;
    catalog: Record<string, { emoji: string; name: string; description: string; tier: string }>;
  };
  outfits: {
    owned: Array<{ code: string; unlocked_at: string }>;
    catalog: Record<string, OutfitDef>;
    backdrops: Record<string, OutfitDef>;
  };
}

export interface OutfitDef {
  name: string;
  slot?: string;
  emoji: string;
  unlock: { type: 'orders' | 'spend' | 'streak' | 'achievements'; value: number };
}

async function authedFetch<T>(endpoint: string, token: string, options?: RequestInit): Promise<T> {
  const res = await fetch(`${API_URL}${endpoint}`, {
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
    ...options,
  });
  if (!res.ok) throw new Error(`API error: ${res.status}`);
  return res.json();
}

export const getCustomerDashboard = (token: string) =>
  authedFetch<CustomerDashboard>('/customer/dashboard', token);

export const setMascotOutfit = (token: string, code: string | null) =>
  authedFetch<{ ok: boolean }>('/customer/mascot/outfit', token, {
    method: 'POST',
    body: JSON.stringify({ code }),
  });

export const setMascotBackdrop = (token: string, code: string | null) =>
  authedFetch<{ ok: boolean }>('/customer/mascot/backdrop', token, {
    method: 'POST',
    body: JSON.stringify({ code }),
  });

export type ActivationStep =
  | 'first_browse'
  | 'first_article'
  | 'first_brand'
  | 'first_cart'
  | 'first_mascot'
  | 'first_order';

export const markActivation = (token: string, step: ActivationStep) =>
  authedFetch<{ _achievement: string | null }>('/customer/activation', token, {
    method: 'POST',
    body: JSON.stringify({ step }),
  });

// ---- Customer profile + address book ----

export interface CustomerProfile {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  is_vip: boolean;
  membership_level: string | null;
  total_orders: number;
  total_spent: number;
}

export interface CustomerAddress {
  id: number;
  label: string | null;
  recipient_name: string;
  phone: string;
  postal_code: string | null;
  city: string | null;
  district: string | null;
  street: string;
  is_default: boolean;
}

export const getProfile = (token: string) =>
  authedFetch<CustomerProfile>('/customer/profile', token);

export const updateProfile = (token: string, data: { name: string; phone?: string }) =>
  authedFetch<CustomerProfile>('/customer/profile', token, {
    method: 'PUT',
    body: JSON.stringify(data),
  });

export const getAddresses = (token: string) =>
  authedFetch<CustomerAddress[]>('/customer/addresses', token);

export const createAddress = (token: string, data: Omit<CustomerAddress, 'id'>) =>
  authedFetch<CustomerAddress>('/customer/addresses', token, {
    method: 'POST',
    body: JSON.stringify(data),
  });

export const updateAddress = (token: string, id: number, data: Partial<Omit<CustomerAddress, 'id'>>) =>
  authedFetch<CustomerAddress>(`/customer/addresses/${id}`, token, {
    method: 'PUT',
    body: JSON.stringify(data),
  });

export const deleteAddress = (token: string, id: number) =>
  authedFetch<{ ok: boolean }>(`/customer/addresses/${id}`, token, {
    method: 'DELETE',
  });
