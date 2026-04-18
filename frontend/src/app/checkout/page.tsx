'use client';

import { useState, useEffect, useCallback, useRef } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { useCart } from '@/components/CartProvider';
import ImageWithFallback, { LogoPlaceholder } from '@/components/ImageWithFallback';
import { useAuth } from '@/components/AuthProvider';
import { useFormValidation } from '@/hooks/useFormValidation';
import { useToast } from '@/components/Toast';
import { trackBeginCheckout, trackPurchase } from '@/components/Analytics';
import { tierLabel } from '@/lib/pricing';
import { formatPrice } from '@/lib/format';
import { API_URL, fetchApi, imageUrl, type CelebrationKeys } from '@/lib/api';
import CheckoutStickyCTA from '@/components/CheckoutStickyCTA';
import SavedAddressPicker from '@/components/SavedAddressPicker';
import type { CustomerAddress } from '@/lib/api';
import { TW_CITIES, districtsFor, zipFor } from '@/lib/tw-regions';
import { useCelebrate } from '@/components/Celebration';
import SiteIcon from '@/components/SiteIcon';
import { useSerendipity } from '@/components/Serendipity';

type PaymentMethod = 'ecpay_credit' | 'cod' | 'bank_transfer';
type ShippingMethod = 'cvs_711' | 'cvs_family' | 'home_delivery';

interface OrderForm {
  name: string;
  email: string;
  phone: string;
  payment_method: PaymentMethod;
  shipping_method: ShippingMethod;
  shipping_name: string;
  shipping_phone: string;
  shipping_address: string;
  shipping_store_id: string;
  shipping_store_name: string;
  social_id: string;
  note: string;
  same_as_customer: boolean;
}

export default function CheckoutPage() {
  const router = useRouter();
  const { items, tier, total, itemPrices, itemCount, clearCart } = useCart();
  const { isLoggedIn, customer } = useAuth();
  const { errors, validate, clearError } = useFormValidation();
  const { toast } = useToast();
  const { celebrateMany } = useCelebrate();
  const { show: showSerendipity } = useSerendipity();
  const [submitting, setSubmitting] = useState(false);
  const [selectedAddressId, setSelectedAddressId] = useState<number | null>(null);
  const [codStatus, setCodStatus] = useState<{ blocked: boolean; message: string | null }>({ blocked: false, message: null });
  const [termsAgreed, setTermsAgreed] = useState(false);

  // Shipping address split state (re-assembled into form.shipping_address on change)
  const [shipCity, setShipCity] = useState('');
  const [shipDistrict, setShipDistrict] = useState('');
  const [shipStreet, setShipStreet] = useState('');
  const codCheckTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const finalTotal = total;

  const [form, setForm] = useState<OrderForm>({
    name: customer?.name || '',
    email: customer?.email || '',
    phone: customer?.phone || '',
    payment_method: 'ecpay_credit',
    shipping_method: 'home_delivery',
    shipping_name: '',
    shipping_phone: '',
    shipping_address: '',
    shipping_store_id: '',
    shipping_store_name: '',
    social_id: '',
    note: '',
    same_as_customer: true,
  });

  // Pre-fill form when customer data becomes available
  useEffect(() => {
    if (customer) {
      setForm((prev) => ({
        ...prev,
        name: prev.name || customer.name,
        email: prev.email || customer.email,
        phone: prev.phone || customer.phone || '',
      }));
    }
  }, [customer]);

  // After returning from ECPay's CVS map, consume the one-shot token to fill store fields.
  useEffect(() => {
    const sp = new URLSearchParams(window.location.search);
    const token = sp.get('cvs_token');
    if (!token) return;
    (async () => {
      try {
        const res = await fetch(`${API_URL}/logistics/cvs/pick/${token}`);
        if (!res.ok) return;
        const d = await res.json() as { store_id: string; store_name: string; address: string; shipping_method: string };
        setForm((prev) => ({
          ...prev,
          shipping_method: d.shipping_method === 'cvs_family' ? 'cvs_family' : 'cvs_711',
          shipping_store_id: d.store_id,
          shipping_store_name: d.store_name,
          shipping_address: d.address,
        }));
        toast(`✓ 已選取 ${d.store_name}`);
      } catch {
        toast('取貨門市讀取失敗，請重新選擇');
      } finally {
        // Remove the token from the URL so reload doesn't re-consume
        sp.delete('cvs_token');
        const qs = sp.toString();
        window.history.replaceState(null, '', window.location.pathname + (qs ? `?${qs}` : ''));
      }
    })();
  }, [toast]);

  const update = (field: keyof OrderForm, value: string | boolean) => {
    setForm((prev) => ({ ...prev, [field]: value }));
    clearError(field);
  };

  // Check blacklist when email/phone changes (debounced)
  const checkCodAvailability = useCallback(async (email: string, phone: string) => {
    if (!email) return;
    try {
      const res = await fetchApi<{ cod_available: boolean; message: string | null }>('/orders/check-cod', {
        method: 'POST',
        body: JSON.stringify({ email, phone }),
      });
      setCodStatus({ blocked: !res.cod_available, message: res.message });
      // If currently selected COD and now blocked, switch to credit
      if (!res.cod_available) {
        setForm((prev) => prev.payment_method === 'cod' ? { ...prev, payment_method: 'ecpay_credit' } : prev);
      }
    } catch {
      // On error, don't block
    }
  }, []);

  useEffect(() => {
    if (codCheckTimer.current) clearTimeout(codCheckTimer.current);
    codCheckTimer.current = setTimeout(() => {
      if (form.email) checkCodAvailability(form.email, form.phone);
    }, 600);
    return () => { if (codCheckTimer.current) clearTimeout(codCheckTimer.current); };
  }, [form.email, form.phone, checkCodAvailability]);

  // COD is only available to logged-in, non-blacklisted users
  const codDisabled = !isLoggedIn || codStatus.blocked;

  const isCvs = form.shipping_method === 'cvs_711' || form.shipping_method === 'cvs_family';

  const validationRules = {
    name: [{ required: true, message: '請輸入姓名' }],
    email: [
      { required: true, message: '請輸入有效的 Email' },
      { pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/, message: '請輸入有效的 Email' },
    ],
    phone: [
      { required: true, message: '請輸入有效手機號碼 (09xxxxxxxx)' },
      { pattern: /^09\d{8}$/, message: '請輸入有效手機號碼 (09xxxxxxxx)' },
    ],
    shipping_address: [
      { required: true, message: '請輸入完整地址（縣市、區、路段門牌）', when: () => !isCvs },
      {
        validator: () => !isCvs ? !!(shipCity && shipDistrict && shipStreet.trim()) : true,
        message: '請完整填寫縣市、區、路段門牌',
      },
    ],
    // shipping_store_name / shipping_store_id are intentionally NOT in the
    // base rules: when CVS is selected without a store picked we don't want
    // the form to hard-block on them — handleSubmit instead auto-opens the
    // ECPay map so the user can finish the flow in one click.
    shipping_name: [
      { required: true, message: '請輸入收件人姓名', when: () => !form.same_as_customer },
    ],
    shipping_phone: [
      { required: true, message: '請輸入收件人電話', when: () => !form.same_as_customer },
    ],
    social_id: [
      { required: true, message: '請填寫您的社群帳號以利聯繫', when: () => form.payment_method === 'cod' },
    ],
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    // Always validate the base form first (name, email, phone, recipient
    // name/phone if not same-as-customer). That way fields like 電話 are
    // caught BEFORE we try to open the CVS map.
    if (!validate(form as unknown as Record<string, any>, validationRules)) {
      return;
    }

    // If CVS pickup and no store yet, auto-open the map instead of
    // blocking with a validation error (everything else is valid by here).
    if (isCvs && (!form.shipping_store_id || !form.shipping_store_name)) {
      const sub = form.shipping_method === 'cvs_family' ? 'FAMI' : 'UNIMART';
      const cod = form.payment_method === 'cod' ? 1 : 0;
      window.open(`${API_URL}/logistics/cvs/init?sub=${sub}&cod=${cod}`, '_blank', 'noopener');
      toast('請先於新分頁選擇取貨門市，選完會自動回填');
      return;
    }

    setSubmitting(true);

    trackBeginCheckout(
      finalTotal,
      items.map((i) => {
        const p = itemPrices.find((ip) => ip.productId === i.product.id);
        return { id: i.product.id, name: i.product.name, price: p?.unitPrice ?? i.product.price, qty: i.quantity };
      }),
    );

    try {
      const payload = {
        items: items.map((i) => ({ product_id: i.product.id, quantity: i.quantity })),
        customer: {
          name: form.name,
          email: form.email.trim().toLowerCase(),
          phone: form.phone,
        },
        payment_method: form.payment_method,
        shipping_method: form.shipping_method,
        shipping_name: form.same_as_customer ? form.name : form.shipping_name,
        shipping_phone: form.same_as_customer ? form.phone : form.shipping_phone,
        shipping_address: isCvs ? undefined : form.shipping_address,
        shipping_store_id: isCvs ? form.shipping_store_id : undefined,
        shipping_store_name: isCvs ? form.shipping_store_name : undefined,
        note: [
          form.social_id ? `[社群帳號] ${form.social_id}` : '',
          form.note || '',
        ].filter((s) => s.trim()).join('\n') || undefined,
        idempotency_key: crypto.randomUUID(),
      };

      const order = await fetchApi<{ order_number: string } & CelebrationKeys>('/orders', {
        method: 'POST',
        body: JSON.stringify(payload),
      });

      trackPurchase(
        order.order_number,
        finalTotal,
        items.map((i) => {
          const p = itemPrices.find((ip) => ip.productId === i.product.id);
          return { id: i.product.id, name: i.product.name, price: p?.unitPrice ?? i.product.price, qty: i.quantity };
        }),
        form.payment_method,
      );

      celebrateMany(order._achievements, order._outfits);
      if (order._serendipity) showSerendipity(order._serendipity);

      clearCart();

      // ECPay credit cards need a hand-off to their hosted payment page;
      // COD / bank transfer can go straight to the order-complete thank-you.
      if (form.payment_method === 'ecpay_credit') {
        try {
          const pay = await fetchApi<{ action: string; params: Record<string, string> }>('/payment/create', {
            method: 'POST',
            body: JSON.stringify({ order_number: order.order_number }),
          });
          const f = document.createElement('form');
          f.method = 'POST';
          f.action = pay.action;
          for (const [k, v] of Object.entries(pay.params)) {
            const i = document.createElement('input');
            i.type = 'hidden';
            i.name = k;
            i.value = String(v);
            f.appendChild(i);
          }
          document.body.appendChild(f);
          f.submit();                        // navigates the window away
          return;
        } catch (e: any) {
          toast(`ECPay 付款跳轉失敗：${e?.message ?? ''}，請稍後重試`, 'error');
          setSubmitting(false);
          return;
        }
      }

      setTimeout(() => {
        const params = new URLSearchParams({ order: order.order_number, payment: form.payment_method });
        if (form.payment_method === 'bank_transfer') params.set('total', String(finalTotal));
        router.push(`/order-complete?${params}`);
      }, (order._achievements?.length || 0) > 0 ? 1200 : 0);
    } catch (err: any) {
      toast(err?.message || '訂單建立失敗，請稍後再試', 'error');
    } finally {
      setSubmitting(false);
    }
  };

  if (items.length === 0) {
    return (
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
        <h1 className="text-2xl font-bold text-gray-900 mb-2">購物車是空的</h1>
        <p className="text-gray-500 mb-6">請先選購商品再進行結帳</p>
        <Link
          href="/products"
          className="inline-flex items-center px-8 py-3 bg-[#9F6B3E] text-white font-semibold rounded-full hover:bg-[#85572F] transition-colors"
        >
          前往選購
        </Link>
      </div>
    );
  }

  return (
    <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 pb-[calc(6rem+env(safe-area-inset-bottom))] md:pb-12">
      <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 mb-8">結帳</h1>

      <form id="checkout-form" onSubmit={handleSubmit} className="lg:grid lg:grid-cols-12 lg:gap-8">
        {/* Left column - Form */}
        <div className="lg:col-span-7 space-y-8">
          {/* Customer Info */}
          <section>
            <h2 className="text-lg font-semibold text-gray-900 mb-4">訂購人資料</h2>
            <div className="space-y-4">
              <div>
                <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
                  姓名 <span className="text-red-500">*</span>
                </label>
                <input
                  id="name"
                  type="text"
                  value={form.name}
                  onChange={(e) => update('name', e.target.value)}
                  className={`w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#9F6B3E] focus:border-transparent outline-none ${errors.name ? 'field-error' : ''}`}
                />
                {errors.name && <p className="field-error-text">{errors.name}</p>}
              </div>
              <div>
                <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
                  Email <span className="text-red-500">*</span>
                  {isLoggedIn && (
                    <span className="text-[10px] text-gray-400 ml-2 font-normal">（Google 帳號綁定，無法修改）</span>
                  )}
                </label>
                <input
                  id="email"
                  type="email"
                  value={form.email}
                  onChange={(e) => !isLoggedIn && update('email', e.target.value)}
                  readOnly={isLoggedIn}
                  disabled={isLoggedIn}
                  className={`w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#9F6B3E] focus:border-transparent outline-none ${isLoggedIn ? 'bg-gray-50 text-gray-600 cursor-not-allowed' : ''} ${errors.email ? 'field-error' : ''}`}
                />
                {errors.email && <p className="field-error-text">{errors.email}</p>}
              </div>
              <div>
                <label htmlFor="phone" className="block text-sm font-medium text-gray-700 mb-1">
                  手機 <span className="text-red-500">*</span>
                </label>
                <input
                  id="phone"
                  type="tel"
                  value={form.phone}
                  onChange={(e) => update('phone', e.target.value)}
                  placeholder="09xxxxxxxx"
                  className={`w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#9F6B3E] focus:border-transparent outline-none ${errors.phone ? 'field-error' : ''}`}
                />
                {errors.phone && <p className="field-error-text">{errors.phone}</p>}
              </div>
            </div>
          </section>

          {/* Shipping */}
          <section>
            <h2 className="text-lg font-semibold text-gray-900 mb-4">配送方式</h2>
            <div className="space-y-3">
              <label className="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-[#9F6B3E] has-[:checked]:border-[#9F6B3E] has-[:checked]:bg-[#9F6B3E]/5">
                <input
                  type="radio"
                  name="shipping_method"
                  value="home_delivery"
                  checked={form.shipping_method === 'home_delivery'}
                  onChange={(e) => update('shipping_method', e.target.value)}
                  className="text-[#9F6B3E] focus:ring-[#9F6B3E]"
                />
                <div>
                  <span className="font-medium text-gray-900">宅配到府</span>
                  <span className="text-sm text-green-600 ml-2">免運</span>
                </div>
              </label>
              <label className="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-[#9F6B3E] has-[:checked]:border-[#9F6B3E] has-[:checked]:bg-[#9F6B3E]/5">
                <input
                  type="radio"
                  name="shipping_method"
                  value="cvs_711"
                  checked={form.shipping_method === 'cvs_711'}
                  onChange={(e) => update('shipping_method', e.target.value)}
                  className="text-[#9F6B3E] focus:ring-[#9F6B3E]"
                />
                <div>
                  <span className="font-medium text-gray-900">7-11 超商取貨</span>
                  <span className="text-sm text-green-600 ml-2">免運</span>
                </div>
              </label>
              <label className="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-[#9F6B3E] has-[:checked]:border-[#9F6B3E] has-[:checked]:bg-[#9F6B3E]/5">
                <input
                  type="radio"
                  name="shipping_method"
                  value="cvs_family"
                  checked={form.shipping_method === 'cvs_family'}
                  onChange={(e) => update('shipping_method', e.target.value)}
                  className="text-[#9F6B3E] focus:ring-[#9F6B3E]"
                />
                <div>
                  <span className="font-medium text-gray-900">全家超商取貨</span>
                  <span className="text-sm text-green-600 ml-2">免運</span>
                </div>
              </label>
            </div>

            {/* Shipping details */}
            <div className="mt-4 space-y-4">
              {/* Saved-address picker — logged-in users only. Picks a card → untick
                  "same as customer" and autofill the shipping_* fields. */}
              <SavedAddressPicker
                selectedId={selectedAddressId}
                onSelect={(a) => {
                  setSelectedAddressId(a.id);
                  update('same_as_customer', false);
                  update('shipping_name', a.recipient_name);
                  update('shipping_phone', a.phone);
                  const full = [a.postal_code, a.city, a.district, a.street].filter(Boolean).join(' ');
                  update('shipping_address', full);
                }}
              />

              <label className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={form.same_as_customer}
                  onChange={(e) => { setSelectedAddressId(null); update('same_as_customer', e.target.checked); }}
                  className="rounded text-[#9F6B3E] focus:ring-[#9F6B3E]"
                />
                <span className="text-sm text-gray-700">收件人同訂購人</span>
              </label>

              {!form.same_as_customer && (
                <div className="space-y-4">
                  <div>
                    <label htmlFor="shipping_name" className="block text-sm font-medium text-gray-700 mb-1">
                      收件人姓名 <span className="text-red-500">*</span>
                    </label>
                    <input
                      id="shipping_name"
                      type="text"
                      value={form.shipping_name}
                      onChange={(e) => update('shipping_name', e.target.value)}
                      className={`w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#9F6B3E] focus:border-transparent outline-none ${errors.shipping_name ? 'field-error' : ''}`}
                    />
                    {errors.shipping_name && <p className="field-error-text">{errors.shipping_name}</p>}
                  </div>
                  <div>
                    <label htmlFor="shipping_phone" className="block text-sm font-medium text-gray-700 mb-1">
                      收件人電話 <span className="text-red-500">*</span>
                    </label>
                    <input
                      id="shipping_phone"
                      type="tel"
                      value={form.shipping_phone}
                      onChange={(e) => update('shipping_phone', e.target.value)}
                      className={`w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#9F6B3E] focus:border-transparent outline-none ${errors.shipping_phone ? 'field-error' : ''}`}
                    />
                    {errors.shipping_phone && <p className="field-error-text">{errors.shipping_phone}</p>}
                  </div>
                </div>
              )}

              {!isCvs && (
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      縣市 <span className="text-red-500">*</span>
                    </label>
                    <select
                      value={shipCity}
                      onChange={(e) => {
                        const city = e.target.value;
                        setShipCity(city);
                        setShipDistrict('');
                        update('shipping_address', [city, '', shipStreet].filter(Boolean).join(' '));
                      }}
                      className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#9F6B3E] focus:border-transparent outline-none bg-white"
                    >
                      <option value="">請選擇</option>
                      {TW_CITIES.map((c) => (
                        <option key={c.city} value={c.city}>{c.city}</option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      區 <span className="text-red-500">*</span>
                    </label>
                    <select
                      value={shipDistrict}
                      onChange={(e) => {
                        const d = e.target.value;
                        setShipDistrict(d);
                        const zip = shipCity ? (zipFor(shipCity, d) ?? '') : '';
                        update('shipping_address', [zip, shipCity, d, shipStreet].filter(Boolean).join(' '));
                      }}
                      disabled={!shipCity}
                      className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#9F6B3E] focus:border-transparent outline-none bg-white disabled:bg-gray-50 disabled:text-gray-400"
                    >
                      <option value="">{shipCity ? '請選擇區' : '先選縣市'}</option>
                      {districtsFor(shipCity).map((d) => (
                        <option key={d.name} value={d.name}>{d.name}</option>
                      ))}
                    </select>
                  </div>
                  <div className="col-span-2">
                    <label htmlFor="shipping_address" className="block text-sm font-medium text-gray-700 mb-1">
                      路段 + 門牌 <span className="text-red-500">*</span>
                    </label>
                    <input
                      id="shipping_address"
                      type="text"
                      value={shipStreet}
                      onChange={(e) => {
                        const street = e.target.value;
                        setShipStreet(street);
                        const zip = shipCity && shipDistrict ? (zipFor(shipCity, shipDistrict) ?? '') : '';
                        update('shipping_address', [zip, shipCity, shipDistrict, street].filter(Boolean).join(' '));
                      }}
                      placeholder="例：仁愛路二段 100 號 5 樓"
                      className={`w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#9F6B3E] focus:border-transparent outline-none ${errors.shipping_address ? 'field-error' : ''}`}
                    />
                    {errors.shipping_address && <p className="field-error-text">{errors.shipping_address}</p>}
                  </div>
                </div>
              )}

              {isCvs && (
                <div className="space-y-4">
                  {/* Open ECPay's map in new tab; page auto-returns via cvs_token */}
                  <a
                    href={`${API_URL}/logistics/cvs/init?sub=${form.shipping_method === 'cvs_family' ? 'FAMI' : 'UNIMART'}&cod=${form.payment_method === 'cod' ? 1 : 0}`}
                    target="_blank"
                    rel="noopener"
                    className="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white font-black text-sm shadow-md shadow-[#9F6B3E]/20 hover:opacity-90 transition-opacity"
                  >
                    <SiteIcon name="package" size={16} className="inline" /> 選擇{form.shipping_method === 'cvs_family' ? '全家' : '7-11'}門市 →
                  </a>
                  {(form.shipping_store_name || form.shipping_store_id) ? (
                    <div className="p-3 bg-[#fdf7ef] border border-[#e7d9cb] rounded-lg text-sm space-y-0.5">
                      <div className="flex items-center gap-2">
                        <span className="text-xs font-black text-[#9F6B3E]">✓ 已選取</span>
                      </div>
                      <div className="font-black text-gray-900">{form.shipping_store_name}</div>
                      <div className="text-[11px] text-gray-500">店號 {form.shipping_store_id}</div>
                      {form.shipping_address && <div className="text-[11px] text-gray-600">{form.shipping_address}</div>}
                    </div>
                  ) : (
                    <p className="text-xs text-gray-500">點上方按鈕進入綠界門市地圖，選擇後會自動回填到結帳頁。</p>
                  )}
                  {(errors.shipping_store_name || errors.shipping_store_id) && (
                    <p className="field-error-text">請先選取取貨門市</p>
                  )}
                </div>
              )}
            </div>
          </section>

          {/* Payment */}
          <section>
            <h2 className="text-lg font-semibold text-gray-900 mb-4">付款方式</h2>
            <div className="space-y-3">
              <label className="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-[#9F6B3E] has-[:checked]:border-[#9F6B3E] has-[:checked]:bg-[#9F6B3E]/5">
                <input
                  type="radio"
                  name="payment_method"
                  value="ecpay_credit"
                  checked={form.payment_method === 'ecpay_credit'}
                  onChange={(e) => update('payment_method', e.target.value)}
                  className="text-[#9F6B3E] focus:ring-[#9F6B3E]"
                />
                <span className="font-medium text-gray-900">信用卡付款</span>
              </label>
              <label className="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-[#9F6B3E] has-[:checked]:border-[#9F6B3E] has-[:checked]:bg-[#9F6B3E]/5">
                <input
                  type="radio"
                  name="payment_method"
                  value="bank_transfer"
                  checked={form.payment_method === 'bank_transfer'}
                  onChange={(e) => update('payment_method', e.target.value)}
                  className="text-[#9F6B3E] focus:ring-[#9F6B3E]"
                />
                <span className="font-medium text-gray-900">銀行轉帳</span>
              </label>

              {/* COD - visible but may be disabled */}
              <label className={`flex items-center gap-3 p-3 border rounded-lg ${
                codDisabled
                  ? 'border-gray-200 bg-gray-50 cursor-not-allowed opacity-60'
                  : 'border-gray-200 cursor-pointer hover:border-[#9F6B3E] has-[:checked]:border-[#9F6B3E] has-[:checked]:bg-[#9F6B3E]/5'
              }`}>
                <input
                  type="radio"
                  name="payment_method"
                  value="cod"
                  checked={form.payment_method === 'cod'}
                  onChange={(e) => { if (!codDisabled) update('payment_method', e.target.value); }}
                  disabled={codDisabled}
                  className="text-[#9F6B3E] focus:ring-[#9F6B3E] disabled:opacity-50"
                />
                <div className="flex-1">
                  <span className={`font-medium ${codDisabled ? 'text-gray-400' : 'text-gray-900'}`}>貨到付款</span>
                  {!isLoggedIn && !codStatus.blocked && (
                    <span className="text-xs text-gray-400 ml-2">需登入會員</span>
                  )}
                  {codStatus.blocked && (
                    <span className="text-xs text-red-400 ml-2">已停用</span>
                  )}
                </div>
              </label>

              {/* COD notice: guest user — must log in */}
              {!isLoggedIn && !codStatus.blocked && (
                <div className="flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" className="w-5 h-5 shrink-0 mt-0.5 text-amber-500">
                    <path fillRule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clipRule="evenodd" />
                  </svg>
                  <div>
                    <p><strong>貨到付款僅限已登入會員</strong></p>
                    <p className="mt-1">
                      <Link href="/login" className="text-[#9F6B3E] font-semibold underline hover:text-[#85572F]">
                        免費註冊 / 登入
                      </Link>
                      {' '}即可解鎖貨到付款，訪客請使用信用卡或 銀行轉帳。
                    </p>
                  </div>
                </div>
              )}

              {/* COD notice: blacklisted (永久 disabled) */}
              {codStatus.blocked && (
                <div className="flex items-start gap-2 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" className="w-5 h-5 shrink-0 mt-0.5 text-red-400">
                    <path fillRule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clipRule="evenodd" />
                  </svg>
                  <div>
                    <p className="font-black">貨到付款功能已永久停用</p>
                    <p className="mt-1 leading-relaxed">
                      您過去曾有「貨到付款未取件」紀錄，依本站政策已停用此支付方式。
                      您仍可使用<strong>信用卡</strong>或<strong>銀行轉帳</strong>正常完成訂購。
                      若有疑問請透過{' '}
                      <a href="https://lin.ee/62wj7qa" target="_blank" rel="noopener" className="underline font-black">
                        LINE 客服
                      </a>
                      聯繫。
                    </p>
                  </div>
                </div>
              )}

              {/* Social ID — always visible, required for COD */}
              <div className="space-y-2">
                <label className="block text-sm font-semibold text-gray-700">
                  社群帳號（FB / IG / LINE）
                  {form.payment_method === 'cod' && <span className="text-red-500"> *</span>}
                </label>
                <input
                  type="text"
                  value={form.social_id}
                  onChange={(e) => update('social_id', e.target.value)}
                  placeholder="請填寫 IG、FB 或 LINE 的帳號（擇一即可）"
                  className={`w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-[#9F6B3E] focus:border-transparent outline-none text-sm ${errors.social_id ? 'border-red-400' : 'border-gray-300'}`}
                />
                <p className="text-[11px] text-gray-400">方便我們聯繫您，不會公開顯示{form.payment_method !== 'cod' && '（選填）'}</p>
                {errors.social_id && <p className="field-error-text">{errors.social_id}</p>}
              </div>

              {/* COD policy warning — visible when COD selected */}
              {isLoggedIn && !codStatus.blocked && form.payment_method === 'cod' && (
                <>
                  <div className="flex items-start gap-2 p-3 bg-[#fdf7ef] border border-[#e7d9cb] rounded-lg text-[12px] text-[#7a5836] leading-relaxed">
                    <svg className="w-4 h-4 shrink-0 mt-0.5 text-amber-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden><path fillRule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.168 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" /></svg>
                    <div>
                      <p className="font-black text-sm text-[#9F6B3E]">貨到付款注意事項</p>
                      <ul className="mt-1.5 space-y-1 list-disc list-inside marker:text-[#9F6B3E]">
                        <li>超商取貨須於 <strong>7 天內</strong>到店取件，逾期視同放棄。</li>
                        <li>
                          <strong className="text-red-600">一次未取件將永久停用貨到付款</strong>
                        </li>
                      </ul>
                    </div>
                  </div>
                </>
              )}
            </div>
          </section>

          {/* Notes */}
          <section>
            <h2 className="text-lg font-semibold text-gray-900 mb-4">訂單備註</h2>
            <textarea
              value={form.note}
              onChange={(e) => update('note', e.target.value)}
              rows={3}
              placeholder="有任何特殊需求請在此備註"
              className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#9F6B3E] focus:border-transparent outline-none resize-none"
            />
          </section>
        </div>

        {/* Right column - Order summary */}
        <div className="lg:col-span-5 mt-8 lg:mt-0">
          <div className="bg-gray-50 rounded-xl p-6 lg:sticky lg:top-24">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">訂單摘要</h2>

            {/* Items */}
            <div className="space-y-3 mb-6 max-h-64 overflow-y-auto">
              {items.map((item) => {
                const priceInfo = itemPrices.find((p) => p.productId === item.product.id);
                const unitPrice = priceInfo?.unitPrice ?? item.product.price;
                const subtotal = priceInfo?.subtotal ?? item.product.price * item.quantity;

                return (
                  <div key={item.product.id} className="flex gap-3">
                    <div className="relative w-14 h-14 bg-white rounded-lg overflow-hidden shrink-0 border border-gray-100">
                      {item.product.image ? (
                        <ImageWithFallback
                          src={imageUrl(item.product.image)!}
                          alt={item.product.name}
                          fill
                          sizes="56px"
                          className="object-cover"
                        />
                      ) : (
                        <LogoPlaceholder />
                      )}
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium text-gray-900 line-clamp-1">{item.product.name}</p>
                      <p className="text-xs text-gray-500">{formatPrice(unitPrice)} x {item.quantity}</p>
                    </div>
                    <span className="text-sm font-medium text-gray-900 shrink-0">{formatPrice(subtotal)}</span>
                  </div>
                );
              })}
            </div>

            {/* Totals */}
            <div className="border-t border-gray-200 pt-4 space-y-2">
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">商品小計（{itemCount} 件）</span>
                <span>{formatPrice(total)}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">運費</span>
                <span className="text-green-600">免運</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">適用方案</span>
                <span className="text-[#9F6B3E] font-medium">{tierLabel(tier)}</span>
              </div>
              <div className="border-t border-gray-200 pt-3 flex justify-between">
                <span className="text-lg font-bold text-gray-900">應付金額</span>
                <span className="text-lg font-bold text-[#9F6B3E]">{formatPrice(finalTotal)}</span>
              </div>
            </div>

            {/* Legal agreement */}
            <label className="mt-6 flex items-start gap-2.5 cursor-pointer group">
              <input
                type="checkbox"
                checked={termsAgreed}
                onChange={(e) => setTermsAgreed(e.target.checked)}
                className="mt-0.5 w-4 h-4 rounded border-gray-300 text-[#9F6B3E] focus:ring-[#9F6B3E] shrink-0"
              />
              <span className="text-[11px] text-gray-500 leading-relaxed">
                我已閱讀並同意
                <a href="/terms" target="_blank" className="text-[#9F6B3E] underline">服務條款</a>、
                <a href="/return-policy" target="_blank" className="text-[#9F6B3E] underline">退換貨政策</a>
                及<a href="/privacy" target="_blank" className="text-[#9F6B3E] underline">隱私權條款</a>，
                並確認享有商品到貨後七日內無條件退貨之權利。
              </span>
            </label>

            {/* Submit */}
            <button
              type="submit"
              disabled={submitting || !termsAgreed}
              className="mt-4 w-full py-3 bg-[#9F6B3E] text-white font-semibold rounded-full hover:bg-[#85572F] transition-colors disabled:opacity-50 disabled:cursor-not-allowed btn-press hidden md:block"
            >
              {submitting ? '處理中...' : '確認送出訂單'}
            </button>

            <Link
              href="/cart"
              className="block mt-3 text-center text-sm text-gray-500 hover:text-[#9F6B3E] transition-colors"
            >
              返回購物車
            </Link>
          </div>
        </div>
      </form>

      {/* Mobile spacer so last form field isn't under sticky CTA */}

      {/* Mobile sticky submit bar */}
      <CheckoutStickyCTA submitting={submitting} termsAgreed={termsAgreed} />
    </div>
  );
}
