'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { API_URL, imageUrl } from '@/lib/api';
import ImageWithFallback, { LogoPlaceholder } from '@/components/ImageWithFallback';
import { formatPrice } from '@/lib/format';
import { useAuth } from '@/components/AuthProvider';
import SiteIcon from '@/components/SiteIcon';

interface OrderItem {
  product_id: number;
  name: string;
  quantity: number;
  unit_price: number;
  subtotal: number;
  image: string | null;
}

interface Order {
  order_number: string;
  status: string;
  payment_status: string;
  total: number;
  payment_method: string;
  shipping_method: string;
  shipping_name: string;
  shipping_phone: string;
  shipping_address: string | null;
  shipping_store_name: string | null;
  items: OrderItem[];
  created_at: string;
}

const STATUS_MAP: Record<string, { label: string; color: string }> = {
  pending: { label: '待處理', color: 'bg-yellow-100 text-yellow-800' },
  pending_payment: { label: '待付款', color: 'bg-orange-100 text-orange-800' },
  processing: { label: '處理中', color: 'bg-blue-100 text-blue-800' },
  shipped: { label: '已出貨', color: 'bg-blue-100 text-blue-800' },
  completed: { label: '已完成', color: 'bg-green-100 text-green-800' },
  cancelled: { label: '已取消', color: 'bg-red-100 text-red-800' },
  refunded: { label: '已退款', color: 'bg-red-100 text-red-800' },
};

/** Derive display status: pending + unpaid ecpay_credit → 待付款 */
function displayStatus(o: Order): string {
  if (o.status === 'pending' && o.payment_status === 'unpaid' && o.payment_method !== 'cod') {
    return 'pending_payment';
  }
  return o.status;
}

const PAYMENT_MAP: Record<string, string> = {
  ecpay_credit: '信用卡（綠界）',
  cod: '貨到付款',
  bank_transfer: 'ATM 轉帳',
};

const SHIPPING_MAP: Record<string, string> = {
  home_delivery: '宅配到府',
  cvs_711: '7-11 超商取貨',
  cvs_family: '全家超商取貨',
};

export default function OrderLookupForm() {
  const { token, isLoggedIn, loading: authLoading } = useAuth();
  const [orderNumber, setOrderNumber] = useState('');
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [order, setOrder] = useState<Order | null>(null);

  const [myOrders, setMyOrders] = useState<Order[] | null>(null);
  const [myOrdersLoading, setMyOrdersLoading] = useState(false);

  // Auto-fetch logged-in customer's orders
  useEffect(() => {
    if (!isLoggedIn || !token) return;
    setMyOrdersLoading(true);
    fetch(`${API_URL}/customer/orders`, {
      headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
    })
      .then((res) => (res.ok ? res.json() : Promise.reject()))
      .then((data) => setMyOrders(data.data ?? []))
      .catch(() => setMyOrders([]))
      .finally(() => setMyOrdersLoading(false));
  }, [isLoggedIn, token]);

  // Logged-in user → show order list (hide guest form)
  if (isLoggedIn) {
    return <MyOrdersList orders={myOrders} loading={myOrdersLoading || authLoading} />;
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!orderNumber.trim() || !email.trim()) return;

    setLoading(true);
    setError('');
    setOrder(null);

    try {
      const res = await fetch(
        `${API_URL}/orders/${encodeURIComponent(orderNumber.trim())}?email=${encodeURIComponent(email.trim())}`,
        { headers: { Accept: 'application/json' } }
      );

      if (res.status === 404) {
        setError('找不到此訂單，請確認訂單編號與 Email 是否正確。');
        return;
      }

      if (!res.ok) {
        throw new Error('查詢失敗');
      }

      const data: Order = await res.json();
      setOrder(data);
    } catch {
      setError('查詢時發生錯誤，請稍後再試。');
    } finally {
      setLoading(false);
    }
  };

  const status = order ? STATUS_MAP[displayStatus(order)] || { label: order.status, color: 'bg-gray-100 text-gray-800' } : null;

  return (
    <div>
      {/* Search Form */}
      <form onSubmit={handleSubmit} className="bg-white border border-gray-200 rounded-xl p-6 space-y-4">
        <div>
          <label htmlFor="orderNumber" className="block text-sm font-medium text-gray-700 mb-1">
            訂單編號
          </label>
          <input
            id="orderNumber"
            type="text"
            value={orderNumber}
            onChange={(e) => setOrderNumber(e.target.value)}
            placeholder="例：ORD-20260412-XXXX"
            required
            className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#9F6B3E] focus:border-transparent outline-none"
          />
        </div>
        <div>
          <label htmlFor="lookupEmail" className="block text-sm font-medium text-gray-700 mb-1">
            訂購人 Email
          </label>
          <input
            id="lookupEmail"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="輸入下單時使用的 Email"
            required
            className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#9F6B3E] focus:border-transparent outline-none"
          />
        </div>
        <button
          type="submit"
          disabled={loading}
          className="w-full py-3 bg-[#9F6B3E] text-white font-semibold rounded-full hover:bg-[#85572F] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {loading ? '查詢中...' : '查詢訂單'}
        </button>
      </form>

      {/* Error */}
      {error && (
        <div className="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
          {error}
        </div>
      )}

      {/* Order Result */}
      {order && status && (
        <div className="mt-8 border border-gray-200 rounded-xl overflow-hidden">
          {/* Header */}
          <div className="bg-gray-50 px-6 py-4 flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="text-sm text-gray-500">訂單編號</p>
              <p className="font-bold text-gray-900">{order.order_number}</p>
            </div>
            <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${status.color}`}>
              {status.label}
            </span>
          </div>

          {/* Items */}
          <div className="px-6 py-4 border-t border-gray-200">
            <h3 className="text-sm font-semibold text-gray-900 mb-3">訂購商品</h3>
            <div className="space-y-3">
              {order.items.map((item) => (
                <div key={item.product_id} className="flex gap-3 items-center">
                  <div className="relative w-12 h-12 bg-gray-100 rounded-lg overflow-hidden shrink-0">
                    {item.image ? (
                      <ImageWithFallback
                        src={imageUrl(item.image)!}
                        alt={item.name}
                        fill
                        sizes="48px"
                        className="object-cover"
                      />
                    ) : (
                      <LogoPlaceholder />
                    )}
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-gray-900 line-clamp-1">{item.name}</p>
                    <p className="text-xs text-gray-500">{formatPrice(item.unit_price)} x {item.quantity}</p>
                  </div>
                  <span className="text-sm font-medium text-gray-900 shrink-0">
                    {formatPrice(item.subtotal)}
                  </span>
                </div>
              ))}
            </div>
          </div>

          {/* Details */}
          <div className="px-6 py-4 border-t border-gray-200 space-y-2 text-sm">
            <div className="flex justify-between">
              <span className="text-gray-500">付款方式</span>
              <span className="text-gray-900">{PAYMENT_MAP[order.payment_method] || order.payment_method}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-gray-500">配送方式</span>
              <span className="text-gray-900">{SHIPPING_MAP[order.shipping_method] || order.shipping_method}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-gray-500">收件人</span>
              <span className="text-gray-900">{order.shipping_name}</span>
            </div>
            {order.shipping_address && (
              <div className="flex justify-between">
                <span className="text-gray-500">配送地址</span>
                <span className="text-gray-900 text-right">{order.shipping_address}</span>
              </div>
            )}
            {order.shipping_store_name && (
              <div className="flex justify-between">
                <span className="text-gray-500">取貨門市</span>
                <span className="text-gray-900">{order.shipping_store_name}</span>
              </div>
            )}
            <div className="flex justify-between">
              <span className="text-gray-500">訂購日期</span>
              <span className="text-gray-900">
                {new Date(order.created_at).toLocaleDateString('zh-TW', {
                  year: 'numeric',
                  month: 'long',
                  day: 'numeric',
                })}
              </span>
            </div>
          </div>

          {/* Total */}
          <div className="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-between">
            <span className="font-bold text-gray-900">訂單金額</span>
            <span className="font-bold text-[#9F6B3E] text-lg">{formatPrice(order.total)}</span>
          </div>
        </div>
      )}
    </div>
  );
}

function MyOrdersList({ orders, loading }: { orders: Order[] | null; loading: boolean }) {
  const [openId, setOpenId] = useState<string | null>(null);
  const [paying, setPaying] = useState<string | null>(null);

  const payAgain = async (orderNumber: string) => {
    setPaying(orderNumber);
    try {
      const res = await fetch(`${API_URL}/payment/create`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ order_number: orderNumber }),
      });
      if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message || '付款建立失敗');
      const pay = (await res.json()) as { action: string; params: Record<string, string> };
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
      f.submit();
    } catch (e: any) {
      alert(`付款跳轉失敗：${e?.message ?? ''}，請稍後再試`);
      setPaying(null);
    }
  };

  if (loading) {
    return (
      <div className="py-12 text-center text-sm text-gray-500">載入訂單中…</div>
    );
  }
  if (!orders || orders.length === 0) {
    return (
      <div className="py-12 text-center">
        <div className="mb-3"><SiteIcon name="mailbox" size={48} className="text-[#9F6B3E]/30" /></div>
        <p className="text-sm font-black text-gray-700">還沒有訂單</p>
        <Link
          href="/products"
          className="mt-5 inline-flex items-center gap-2 px-6 py-3 bg-[#9F6B3E] text-white font-black rounded-full hover:bg-[#85572F] transition-colors"
        >
          去逛商品 →
        </Link>
      </div>
    );
  }
  return (
    <div className="space-y-3">
      <div className="text-[11px] font-black text-[#7a5836] tracking-[0.15em] mb-3">
        您的訂單 ({orders.length})
      </div>
      {orders.map((o) => {
        const status = STATUS_MAP[displayStatus(o)] || { label: o.status, color: 'bg-gray-100 text-gray-800' };
        const isOpen = openId === o.order_number;
        return (
          <div
            key={o.order_number}
            className="bg-white border border-[#e7d9cb] rounded-2xl overflow-hidden transition-shadow"
          >
            <button
              type="button"
              onClick={() => setOpenId(isOpen ? null : o.order_number)}
              className="w-full text-left p-4 sm:p-5 hover:bg-[#fdf7ef]/50 transition-colors"
            >
              <div className="flex items-start justify-between gap-3 mb-2">
                <div className="min-w-0">
                  <div className="text-[11px] text-gray-400 font-bold tracking-wide">
                    #{o.order_number}
                  </div>
                  <div className="text-[11px] text-gray-500 mt-0.5">
                    {new Date(o.created_at).toLocaleDateString('zh-TW', { year: 'numeric', month: 'long', day: 'numeric' })}
                  </div>
                </div>
                <span className={`shrink-0 px-2.5 py-1 rounded-full text-[10px] font-black ${status.color}`}>
                  {status.label}
                </span>
              </div>
              <div className="flex items-center gap-3 mt-3">
                <div className="flex -space-x-2 shrink-0">
                  {o.items.slice(0, 3).map((it, i) => (
                    <div
                      key={`${it.product_id}-${i}`}
                      className="w-10 h-10 rounded-full bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] border-2 border-white overflow-hidden flex items-center justify-center"
                    >
                      {it.image ? (
                        <ImageWithFallback src={imageUrl(it.image)!} alt={it.name} width={40} height={40} className="object-cover w-full h-full" />
                      ) : (
                        <LogoPlaceholder />
                      )}
                    </div>
                  ))}
                  {o.items.length > 3 && (
                    <div className="w-10 h-10 rounded-full bg-slate-100 border-2 border-white flex items-center justify-center text-[10px] font-black text-slate-500">
                      +{o.items.length - 3}
                    </div>
                  )}
                </div>
                <div className="flex-1 min-w-0 text-xs text-gray-600 truncate">
                  {o.items.map((it) => it.name).join('、')}
                </div>
              </div>
              <div className="mt-3 flex items-center justify-between">
                <span className="text-sm font-black text-[#9F6B3E]">{formatPrice(o.total)}</span>
                <span className="text-[11px] text-[#9F6B3E] font-black">
                  {isOpen ? '收合 ▲' : '詳情 ▼'}
                </span>
              </div>
            </button>

            {/* Accordion detail */}
            {isOpen && (
              <div className="px-4 sm:px-5 pb-5 border-t border-dashed border-[#e7d9cb] space-y-4 bg-[#fdf7ef]/30">
                <div className="pt-4">
                  <div className="text-[10px] font-black text-[#7a5836] tracking-wider mb-2">商品</div>
                  <div className="space-y-2">
                    {o.items.map((it, i) => (
                      <div key={`${it.product_id}-d-${i}`} className="flex items-center gap-3 bg-white rounded-xl p-2">
                        <div className="w-12 h-12 rounded-lg bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] overflow-hidden shrink-0 flex items-center justify-center">
                          {it.image ? (
                            <ImageWithFallback src={imageUrl(it.image)!} alt={it.name} width={48} height={48} className="object-cover w-full h-full" />
                          ) : (
                            <LogoPlaceholder />
                          )}
                        </div>
                        <div className="flex-1 min-w-0">
                          <div className="text-xs font-black text-gray-900 truncate">{it.name}</div>
                          <div className="text-[11px] text-gray-500">{formatPrice(it.unit_price)} × {it.quantity}</div>
                        </div>
                        <div className="text-sm font-black text-[#9F6B3E] shrink-0">{formatPrice(it.subtotal)}</div>
                      </div>
                    ))}
                  </div>
                </div>

                <div>
                  <div className="text-[10px] font-black text-[#7a5836] tracking-wider mb-2">配送資訊</div>
                  <div className="bg-white rounded-xl p-3 text-xs space-y-1">
                    <div><span className="text-gray-500">方式：</span><span className="text-gray-800 font-black">{SHIPPING_MAP[o.shipping_method] || o.shipping_method}</span></div>
                    <div><span className="text-gray-500">收件人：</span><span className="text-gray-800">{o.shipping_name} · {o.shipping_phone}</span></div>
                    {o.shipping_store_name && (
                      <div><span className="text-gray-500">取貨門市：</span><span className="text-gray-800">{o.shipping_store_name}</span></div>
                    )}
                    {o.shipping_address && (
                      <div><span className="text-gray-500">地址：</span><span className="text-gray-800">{o.shipping_address}</span></div>
                    )}
                  </div>
                </div>

                <div>
                  <div className="text-[10px] font-black text-[#7a5836] tracking-wider mb-2">付款</div>
                  <div className="bg-white rounded-xl p-3 text-xs">
                    <div className="flex items-center justify-between">
                      <span className="text-gray-500">{PAYMENT_MAP[o.payment_method] || o.payment_method}</span>
                      <span className="text-sm font-black text-[#9F6B3E]">{formatPrice(o.total)}</span>
                    </div>
                  </div>
                </div>

                {o.status === 'pending' && o.payment_method === 'ecpay_credit' && (
                  <button
                    type="button"
                    onClick={() => payAgain(o.order_number)}
                    disabled={paying === o.order_number}
                    className="w-full text-center px-4 py-2.5 rounded-full bg-[#9F6B3E] text-white font-black text-sm hover:bg-[#85572F] disabled:opacity-60"
                  >
                    {paying === o.order_number ? '跳轉中…' : '前往付款 →'}
                  </button>
                )}
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}
