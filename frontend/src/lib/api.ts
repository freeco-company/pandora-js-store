export const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';
const STORAGE_URL = process.env.NEXT_PUBLIC_STORAGE_URL || 'http://localhost:8000';

/**
 * Error thrown for non-2xx responses. Exposes the parsed Laravel validation
 * payload so UIs can show field-specific messages (e.g. "email 已被使用"
 * vs generic "儲存失敗").
 */
export class ApiError extends Error {
  constructor(
    public status: number,
    public body: { message?: string; errors?: Record<string, string[]> } = {},
  ) {
    super(body.message || `API error: ${status}`);
    this.name = 'ApiError';
  }

  /** First error message for a given field, or undefined. */
  fieldError(field: string): string | undefined {
    return this.body.errors?.[field]?.[0];
  }
}

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
    signal: AbortSignal.timeout(15_000),
    ...options,
  });
  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new ApiError(res.status, body);
  }
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
    signal: AbortSignal.timeout(15_000),
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
  auth_provider?: 'google' | 'line' | 'email';
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
  /** 衛福部健康食品認證字號 (e.g. 衛部健食字第 A00394 號) */
  hf_cert_no?: string | null;
  /** 經核可的保健功效，如「輔助調節血脂」 */
  hf_cert_claim?: string | null;
  /** Badge codes: snq, monde_selection, clean_label, patent, official */
  badges?: string[] | null;
  created_at?: string;
}

export interface ProductCategory {
  id: number;
  name: string;
  slug: string;
  products_count?: number;
}

/**
 * Campaign = time-bound wrapper with N bundles under it.
 * Bundles are the purchasable unit with name, image, buy/gift items,
 * and live at /bundles/{slug}.
 */
export interface CampaignBundleItem {
  product: Product;
  quantity: number;
}

export interface CampaignBundle {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  image: string | null;
  bundle_price: number;       // 套組價 — sum of buy items' VIP × qty
  bundle_value_price: number; // 價值 — admin-entered anchor, falls back to retail sum
  buy_items: CampaignBundleItem[];
  gift_items: CampaignBundleItem[];
  /** 自訂加贈（非商品）— 服務型贈品，只用於前台顯示 */
  custom_gifts: { name: string; quantity: number }[];
  /** Present on /api/bundles/{slug} responses */
  campaign?: {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    start_at: string;
    end_at: string;
    is_running: boolean;
  };
}

export interface Campaign {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  image: string | null;
  banner_image: string | null;
  start_at: string;
  end_at: string;
  is_running: boolean;
  bundles: CampaignBundle[];
}

export interface Article {
  id: number;
  title: string;
  slug: string;
  content: string;
  excerpt: string;
  featured_image: string | null;
  source_url: string | null;
  source_type: string;
  published_at: string;
  seo_meta: SeoMeta | null;
}

export interface SeoMeta {
  title: string;
  description: string;
  og_image: string;
}

/** Normal product item in a cart payload sent to the server. */
export interface CartProductPayload {
  product_id: number;
  quantity: number;
  type?: 'product';
}

/** Bundle item in a cart payload — represents N copies of a bundle. */
export interface CartBundlePayload {
  bundle_id: number;
  quantity: number;
  type: 'bundle';
}

export type CartItem = CartProductPayload | CartBundlePayload;

export interface CartUnavailableItem {
  product_id?: number;
  bundle_id?: number;
  reason: 'not_found' | 'inactive' | 'out_of_stock' | 'insufficient_stock' | 'bundle_not_found' | 'bundle_expired';
  name: string;
  available?: number;
  requested?: number;
}

export interface CartCalculation {
  tier: 'regular' | 'combo' | 'vip';
  total: number;
  campaign_vip?: boolean;
  items: CartCalculationItem[];
  bundles?: CartBundleCalculation[];
  unavailable?: CartUnavailableItem[];
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

export interface CartBundleCalculation {
  bundle_id: number;
  name: string;
  slug: string;
  image: string | null;
  quantity: number;
  unit_price: number;
  subtotal: number;
  buy_items: { product_id: number; name: string; image: string | null; quantity: number }[];
  gift_items: { product_id: number; name: string; image: string | null; quantity: number }[];
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

export const getArticles = (type?: string, page = 1, perPage = 12, category?: string) =>
  getPublic<PaginatedResponse<Article>>(
    `/articles?per_page=${perPage}&page=${page}${type ? `&type=${type}` : ''}${category ? `&category=${category}` : ''}`,
    { tags: ['articles'] },
  );

export const getArticle = (slug: string) =>
  getPublic<Article>(`/articles/${slug}`, { tags: ['articles', `article:${slug}`] });

export const calculateCart = (items: CartItem[]) =>
  fetchApi<CartCalculation>('/cart/calculate', {
    method: 'POST',
    body: JSON.stringify({ items }),
  });

/** Campaign with nested bundles (for /campaigns/[slug] overview). */
export const getCampaign = (slug: string) =>
  getPublic<Campaign>(`/campaigns/${slug}`, { tags: ['campaigns', `campaign:${slug}`] });

export const getCampaigns = () =>
  getPublic<Campaign[]>(`/campaigns`, { tags: ['campaigns'] });

/** Single bundle detail with parent campaign info (for /bundles/[slug]). */
export const getBundle = (slug: string) =>
  getPublic<CampaignBundle>(`/bundles/${slug}`, { tags: ['bundles', `bundle:${slug}`] });

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

// Reviews
export interface ReviewItem {
  id: number;
  rating: number;
  content: string | null;
  reviewer_name: string;
  is_verified_purchase: boolean;
  created_at: string;
}

export interface ProductReviewsData {
  average_rating: number;
  total_count: number;
  distribution: Record<number, number>;
  reviews: ReviewItem[];
}

export interface ReviewableItem {
  order_id: number;
  order_number: string;
  product_id: number;
  product_name: string;
  product_slug: string;
  product_image: string | null;
  completed_at: string;
}

export interface AggregateReviewsData {
  total_count: number;
  average_rating: number;
  products: Array<{
    product_id: number;
    product_name: string;
    product_slug: string;
    product_image: string | null;
    count: number;
    average_rating: number;
  }>;
  recent_reviews: Array<ReviewItem & { product_name: string; product_slug: string }>;
}

export const getAggregateReviews = () =>
  getPublic<AggregateReviewsData>('/reviews', { revalidate: 300, tags: ['reviews'] });

export const getProductReviews = (slug: string) =>
  getPublic<ProductReviewsData>(`/products/${slug}/reviews`, { revalidate: 300, tags: ['reviews', `reviews:${slug}`] });

export const getReviewableProducts = (token: string) =>
  authedFetch<ReviewableItem[]>('/customer/reviewable', token);

export const submitReview = (token: string, data: { product_id: number; order_id: number; rating: number; content?: string }) =>
  authedFetch<{ message: string; review: ReviewItem } & CelebrationKeys>('/customer/reviews', token, {
    method: 'POST',
    body: JSON.stringify(data),
  });


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
  total_xp: number;
  level: number;
  xp_in_level: number;
  referral_code: string | null;
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
    catalog: Record<string, AchievementDef>;
    /** Map of code → current/target for measurable achievements (binary ones omitted). */
    progress: Record<string, { current: number; target: number }>;
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

export interface AchievementDef {
  emoji: string;
  name: string;
  description: string;
  tier: 'bronze' | 'silver' | 'gold' | string;
  /** Present only for measurable achievements; absent on binary ones. */
  progress?: {
    type: 'order_count' | 'spend_total' | 'vip_order_count' | 'streak_days' | 'referral_count' | 'category_count';
    target: number;
  };
}

async function authedFetch<T>(endpoint: string, token: string, options?: RequestInit): Promise<T> {
  const res = await fetch(`${API_URL}${endpoint}`, {
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
    signal: AbortSignal.timeout(15_000),
    ...options,
  });
  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new ApiError(res.status, body);
  }
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
  auth_provider: 'google' | 'line' | 'email';
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

export const updateProfile = (token: string, data: { name: string; phone?: string; email?: string }) =>
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
