'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/components/AuthProvider';
import { useToast } from '@/components/Toast';
import LogoLoader from '@/components/LogoLoader';
import {
  getAddresses,
  createAddress,
  updateAddress,
  deleteAddress,
  type CustomerAddress,
} from '@/lib/api';
import { TW_CITIES, districtsFor, zipFor } from '@/lib/tw-regions';
import SiteIcon from '@/components/SiteIcon';

type Draft = Omit<CustomerAddress, 'id'>;
const emptyDraft: Draft = {
  label: '',
  recipient_name: '',
  phone: '',
  postal_code: '',
  city: '',
  district: '',
  street: '',
  is_default: false,
};

export default function AddressesPage() {
  const router = useRouter();
  const { token, isLoggedIn, loading: authLoading } = useAuth();
  const { toast } = useToast();
  const [addresses, setAddresses] = useState<CustomerAddress[]>([]);
  const [loading, setLoading] = useState(true);
  const [editing, setEditing] = useState<{ id?: number; draft: Draft } | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (authLoading) return;
    if (!isLoggedIn || !token) {
      router.replace('/account');
      return;
    }
    refresh();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token, isLoggedIn, authLoading]);

  const refresh = async () => {
    if (!token) return;
    setLoading(true);
    try {
      const data = await getAddresses(token);
      setAddresses(data);
    } finally {
      setLoading(false);
    }
  };

  const startCreate = () => setEditing({ draft: { ...emptyDraft } });
  const startEdit = (a: CustomerAddress) =>
    setEditing({
      id: a.id,
      draft: {
        label: a.label || '',
        recipient_name: a.recipient_name,
        phone: a.phone,
        postal_code: a.postal_code || '',
        city: a.city || '',
        district: a.district || '',
        street: a.street,
        is_default: a.is_default,
      },
    });

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token || !editing) return;
    setSaving(true);
    try {
      if (editing.id) {
        await updateAddress(token, editing.id, editing.draft);
        toast('地址已更新');
      } else {
        await createAddress(token, editing.draft);
        toast('地址已新增');
      }
      setEditing(null);
      await refresh();
    } catch {
      toast('儲存失敗');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id: number) => {
    if (!token) return;
    if (!confirm('確定要刪除這個地址？')) return;
    try {
      await deleteAddress(token, id);
      toast('已刪除');
      await refresh();
    } catch {
      toast('刪除失敗');
    }
  };

  const handleSetDefault = async (a: CustomerAddress) => {
    if (!token || a.is_default) return;
    await updateAddress(token, a.id, { is_default: true });
    toast('已設為預設');
    await refresh();
  };

  if (loading) {
    return <div className="py-24 flex justify-center"><LogoLoader size={72} /></div>;
  }

  return (
    <div className="max-w-2xl mx-auto p-4 sm:p-6 space-y-5 pb-24">
      <div className="flex items-center gap-2">
        <Link href="/account/profile" className="text-[#9F6B3E] text-sm font-black">
          ← 個人資料
        </Link>
      </div>

      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-black text-slate-800">常用地址</h1>
        <button
          onClick={startCreate}
          className="px-4 h-10 rounded-full bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white text-sm font-black shadow-md shadow-[#9F6B3E]/25 active:scale-[0.98] transition-transform"
        >
          + 新增
        </button>
      </div>

      {addresses.length === 0 ? (
        <div className="bg-white rounded-3xl border border-[#e7d9cb] p-10 text-center">
          <div className="text-5xl mb-3"><SiteIcon name="target" size={48} className="text-[#9F6B3E]/30" /></div>
          <p className="text-sm font-black text-slate-700 mb-1">還沒有儲存地址</p>
          <p className="text-[11px] text-slate-500">新增常用地址，下次結帳一鍵選取</p>
        </div>
      ) : (
        <div className="space-y-3">
          {addresses.map((a) => (
            <div key={a.id} className="bg-white rounded-3xl border border-[#e7d9cb] p-5">
              <div className="flex items-start justify-between gap-3 mb-3">
                <div className="flex items-center gap-2 flex-wrap">
                  {a.label && (
                    <span className="px-2 py-0.5 rounded-full bg-[#fdf7ef] text-[11px] font-black text-[#9F6B3E]">
                      {a.label}
                    </span>
                  )}
                  {a.is_default && (
                    <span className="px-2 py-0.5 rounded-full bg-[#9F6B3E] text-white text-[11px] font-black">
                      預設
                    </span>
                  )}
                </div>
                <div className="flex gap-2 text-xs">
                  <button onClick={() => startEdit(a)} className="text-[#9F6B3E] font-black">編輯</button>
                  <button onClick={() => handleDelete(a.id)} className="text-slate-400 hover:text-red-500">刪除</button>
                </div>
              </div>
              <div className="text-sm font-black text-slate-800">{a.recipient_name}</div>
              <div className="text-[12px] text-slate-500 mt-0.5">{a.phone}</div>
              <div className="text-sm text-slate-700 mt-2">
                {a.postal_code ? `${a.postal_code} ` : ''}
                {a.city}{a.district} {a.street}
              </div>
              {!a.is_default && (
                <button
                  onClick={() => handleSetDefault(a)}
                  className="mt-3 text-[11px] text-[#9F6B3E] font-black underline"
                >
                  設為預設地址
                </button>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Edit modal */}
      {editing && (
        <div className="fixed inset-0 z-[200] flex items-end md:items-center justify-center bg-black/40 backdrop-blur-sm" onClick={() => setEditing(null)}>
          <form
            onSubmit={handleSave}
            onClick={(e) => e.stopPropagation()}
            className="bg-white w-full md:max-w-lg md:rounded-3xl rounded-t-3xl p-5 sm:p-6 space-y-3 max-h-[90vh] overflow-y-auto"
          >
            <h2 className="text-lg font-black text-slate-800 mb-2">{editing.id ? '編輯地址' : '新增地址'}</h2>

            <Row label="標籤 (選填)">
              <input
                type="text" value={editing.draft.label || ''}
                onChange={(e) => setEditing({ ...editing, draft: { ...editing.draft, label: e.target.value } })}
                placeholder="家 / 公司 / 父母家" maxLength={50}
                className="input"
              />
            </Row>
            <Row label="收件人 *">
              <input
                type="text" required value={editing.draft.recipient_name}
                onChange={(e) => setEditing({ ...editing, draft: { ...editing.draft, recipient_name: e.target.value } })}
                className="input"
              />
            </Row>
            <Row label="手機 *">
              <input
                type="tel" required value={editing.draft.phone}
                onChange={(e) => setEditing({ ...editing, draft: { ...editing.draft, phone: e.target.value } })}
                className="input"
              />
            </Row>
            <div className="grid grid-cols-2 gap-2">
              <Row label="縣市 *">
                <select
                  value={editing.draft.city || ''}
                  onChange={(e) => {
                    const city = e.target.value;
                    setEditing({ ...editing, draft: { ...editing.draft, city, district: '', postal_code: '' } });
                  }}
                  required
                  className="input"
                >
                  <option value="">請選擇縣市</option>
                  {TW_CITIES.map((c) => (
                    <option key={c.city} value={c.city}>{c.city}</option>
                  ))}
                </select>
              </Row>
              <Row label="區 *">
                <select
                  value={editing.draft.district || ''}
                  onChange={(e) => {
                    const district = e.target.value;
                    const zip = editing.draft.city ? (zipFor(editing.draft.city, district) ?? '') : '';
                    setEditing({ ...editing, draft: { ...editing.draft, district, postal_code: zip } });
                  }}
                  required
                  disabled={!editing.draft.city}
                  className="input disabled:bg-slate-50 disabled:text-slate-400"
                >
                  <option value="">{editing.draft.city ? '請選擇區' : '先選縣市'}</option>
                  {districtsFor(editing.draft.city || '').map((d) => (
                    <option key={d.name} value={d.name}>{d.name}</option>
                  ))}
                </select>
              </Row>
            </div>
            <Row label="街道地址 *">
              <input
                type="text" required value={editing.draft.street}
                onChange={(e) => setEditing({ ...editing, draft: { ...editing.draft, street: e.target.value } })}
                placeholder="例：仁愛路二段 100 號 5 樓" className="input"
              />
            </Row>
            <label className="flex items-center gap-2 text-sm text-slate-700">
              <input
                type="checkbox" checked={editing.draft.is_default || false}
                onChange={(e) => setEditing({ ...editing, draft: { ...editing.draft, is_default: e.target.checked } })}
              />
              設為預設地址
            </label>

            <div className="flex gap-2 pt-2">
              <button type="button" onClick={() => setEditing(null)} className="flex-1 h-12 rounded-full bg-slate-100 text-slate-700 font-black">
                取消
              </button>
              <button type="submit" disabled={saving} className="flex-1 h-12 rounded-full bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white font-black disabled:opacity-50">
                {saving ? '儲存中...' : '儲存'}
              </button>
            </div>

            <style>{`.input { width: 100%; padding: 0.75rem 1rem; border-radius: 0.75rem; border: 1px solid #e7d9cb; background: #fff; font-size: 0.875rem; outline: none; }
            .input:focus { border-color: #9F6B3E; }`}</style>
          </form>
        </div>
      )}
    </div>
  );
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <label className="block text-[11px] font-black text-slate-500 tracking-wider mb-1">{label}</label>
      {children}
    </div>
  );
}
