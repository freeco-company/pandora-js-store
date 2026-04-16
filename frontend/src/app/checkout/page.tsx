'use client';

import { useState, useEffect, useCallback, useRef } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import Image from 'next/image';
import { useCart } from '@/components/CartProvider';
import { useAuth } from '@/components/AuthProvider';
import { useFormValidation } from '@/hooks/useFormValidation';
import { useToast } from '@/components/Toast';
import { tierLabel } from '@/lib/pricing';
import { formatPrice } from '@/lib/format';
import { fetchApi, imageUrl, type CelebrationKeys } from '@/lib/api';
import CheckoutStickyCTA from '@/components/CheckoutStickyCTA';
import SavedAddressPicker from '@/components/SavedAddressPicker';
import type { CustomerAddress } from '@/lib/api';
import { useCelebrate } from '@/components/Celebration';
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
      { required: true, message: '請輸入配送地址', when: () => !isCvs },
    ],
    shipping_store_name: [
      { required: true, message: '請輸入取貨門市名稱', when: () => isCvs },
    ],
    shipping_store_id: [
      { required: true, message: '請輸入門市店號', when: () => isCvs },
    ],
    shipping_name: [
      { required: true, message: '請輸入收件人姓名', when: () => !form.same_as_customer },
    ],
    shipping_phone: [
      { required: true, message: '請輸入收件人電話', when: () => !form.same_as_customer },
    ],
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validate(form as unknown as Record<string, any>, validationRules)) {
      return;
    }

    setSubmitting(true);

    try {
      const payload = {
        items: items.map((i) => ({ product_id: i.product.id, quantity: i.quantity })),
        customer: {
          name: form.name,
          email: form.email,
          phone: form.phone,
        },
        payment_method: form.payment_method,
        shipping_method: form.shipping_method,
        shipping_name: form.same_as_customer ? form.name : form.shipping_name,
        shipping_phone: form.same_as_customer ? form.phone : form.shipping_phone,
        shipping_address: isCvs ? undefined : form.shipping_address,
        shipping_store_id: isCvs ? form.shipping_store_id : undefined,
        shipping_store_name: isCvs ? form.shipping_store_name : undefined,
        note: form.note || undefined,
      };

      const order = await fetchApi<{ order_number: string } & CelebrationKeys>('/orders', {
        method: 'POST',
        body: JSON.stringify(payload),
      });

      celebrateMany(order._achievements, order._outfits);
      if (order._serendipity) showSerendipity(order._serendipity);

      clearCart();
      // Give celebrations a moment to play before navigating away
      setTimeout(() => {
        router.push(`/order-complete?order=${order.order_number}`);
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
                </label>
                <input
                  id="email"
                  type="email"
                  value={form.email}
                  onChange={(e) => update('email', e.target.value)}
                  className={`w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#9F6B3E] focus:border-transparent outline-none ${errors.email ? 'field-error' : ''}`}
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
                <div>
                  <label htmlFor="shipping_address" className="block text-sm font-medium text-gray-700 mb-1">
                    配送地址 <span className="text-red-500">*</span>
                  </label>
                  <input
                    id="shipping_address"
                    type="text"
                    value={form.shipping_address}
                    onChange={(e) => update('shipping_address', e.target.value)}
                    placeholder="縣市 + 區域 + 路段門號"
                    className={`w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#9F6B3E] focus:border-transparent outline-none ${errors.shipping_address ? 'field-error' : ''}`}
                  />
                  {errors.shipping_address && <p className="field-error-text">{errors.shipping_address}</p>}
                </div>
              )}

              {isCvs && (
                <div className="space-y-4">
                  <div>
                    <label htmlFor="shipping_store_name" className="block text-sm font-medium text-gray-700 mb-1">
                      取貨門市名稱 <span className="text-red-500">*</span>
                    </label>
                    <input
                      id="shipping_store_name"
                      type="text"
                      value={form.shipping_store_name}
                      onChange={(e) => update('shipping_store_name', e.target.value)}
                      placeholder="例：台北信義門市"
                      className={`w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#9F6B3E] focus:border-transparent outline-none ${errors.shipping_store_name ? 'field-error' : ''}`}
                    />
                    {errors.shipping_store_name && <p className="field-error-text">{errors.shipping_store_name}</p>}
                  </div>
                  <div>
                    <label htmlFor="shipping_store_id" className="block text-sm font-medium text-gray-700 mb-1">
                      門市店號 <span className="text-red-500">*</span>
                    </label>
                    <input
                      id="shipping_store_id"
                      type="text"
                      value={form.shipping_store_id}
                      onChange={(e) => update('shipping_store_id', e.target.value)}
                      className={`w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#9F6B3E] focus:border-transparent outline-none ${errors.shipping_store_id ? 'field-error' : ''}`}
                    />
                    {errors.shipping_store_id && <p className="field-error-text">{errors.shipping_store_id}</p>}
                  </div>
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
                <span className="font-medium text-gray-900">信用卡付款（綠界）</span>
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
                <span className="font-medium text-gray-900">ATM 轉帳</span>
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
                      {' '}即可解鎖貨到付款，訪客請使用信用卡或 ATM 轉帳。
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
                      您仍可使用<strong>信用卡</strong>或<strong>ATM 轉帳</strong>正常完成訂購。
                      若有疑問請透過{' '}
                      <a href="https://lin.ee/pandorasdo" target="_blank" rel="noopener" className="underline font-black">
                        LINE 客服
                      </a>
                      聯繫。
                    </p>
                  </div>
                </div>
              )}

              {/* COD policy reminder — always visible when COD is selectable */}
              {isLoggedIn && !codStatus.blocked && form.payment_method === 'cod' && (
                <div className="flex items-start gap-2 p-3 bg-[#fdf7ef] border border-[#e7d9cb] rounded-lg text-[12px] text-[#7a5836] leading-relaxed">
                  <span className="text-base shrink-0 mt-0.5">⚠️</span>
                  <div>
                    <p className="font-black text-sm text-[#9F6B3E]">貨到付款注意事項</p>
                    <ul className="mt-1.5 space-y-1 list-disc list-inside marker:text-[#9F6B3E]">
                      <li>商品送達時請備妥足額現金，當面點收後付款。</li>
                      <li>超商取貨須於 <strong>7 天內</strong>到店取件，逾期視同放棄。</li>
                      <li>
                        <strong className="text-red-600">一次未取件將永久停用貨到付款</strong>
                        （不影響信用卡/ATM 訂購）。
                      </li>
                      <li>限台灣本島地區配送，單筆上限 NT$20,000。</li>
                    </ul>
                  </div>
                </div>
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
                        <Image
                          src={imageUrl(item.product.image)!}
                          alt={item.product.name}
                          fill
                          sizes="56px"
                          className="object-cover"
                        />
                      ) : (
                        <div className="w-full h-full flex items-center justify-center text-gray-300 text-xs">
                          無圖
                        </div>
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

            {/* Submit */}
            <button
              type="submit"
              disabled={submitting}
              className="mt-6 w-full py-3 bg-[#9F6B3E] text-white font-semibold rounded-full hover:bg-[#85572F] transition-colors disabled:opacity-50 disabled:cursor-not-allowed btn-press"
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
      <CheckoutStickyCTA submitting={submitting} />
    </div>
  );
}
