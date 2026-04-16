'use client';

import { useState, useEffect, useRef, useCallback } from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { searchProducts, imageUrl, type Product } from '@/lib/api';
import { formatPrice } from '@/lib/format';

export default function SearchModal({
  open,
  onClose,
}: {
  open: boolean;
  onClose: () => void;
}) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<Product[]>([]);
  const [loading, setLoading] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Focus input when modal opens
  useEffect(() => {
    if (open) {
      setQuery('');
      setResults([]);
      setTimeout(() => inputRef.current?.focus(), 100);
    }
  }, [open]);

  // Close on Escape
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    if (open) {
      document.addEventListener('keydown', handleKeyDown);
      document.body.style.overflow = 'hidden';
    }
    return () => {
      document.removeEventListener('keydown', handleKeyDown);
      document.body.style.overflow = '';
    };
  }, [open, onClose]);

  const doSearch = useCallback(async (q: string) => {
    if (q.trim().length < 1) {
      setResults([]);
      return;
    }
    setLoading(true);
    try {
      const data = await searchProducts(q.trim());
      setResults(data);
    } catch {
      setResults([]);
    } finally {
      setLoading(false);
    }
  }, []);

  const handleInputChange = (value: string) => {
    setQuery(value);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => doSearch(value), 300);
  };

  return (
    <div
      className="fixed inset-0 z-[200]"
      style={{
        opacity: open ? 1 : 0,
        pointerEvents: open ? 'auto' : 'none',
        transition: 'opacity 0.2s ease',
      }}
    >
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/50 backdrop-blur-sm"
        onClick={onClose}
      />

      {/* Modal content */}
      <div className="relative w-full max-w-2xl mx-auto mt-20 sm:mt-32 px-4">
        <div className={`bg-white rounded-2xl shadow-2xl overflow-hidden ${open ? 'modal-content-enter' : ''}`}>
          {/* Search input */}
          <div className="flex items-center gap-3 px-5 py-4 border-b border-gray-100">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              strokeWidth={1.5}
              stroke="currentColor"
              className="w-5 h-5 text-[#9F6B3E] shrink-0"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"
              />
            </svg>
            <input
              ref={inputRef}
              type="text"
              value={query}
              onChange={(e) => handleInputChange(e.target.value)}
              placeholder="搜尋商品..."
              className="flex-1 text-lg outline-none text-gray-900 placeholder:text-gray-400"
            />
            <button
              onClick={onClose}
              className="p-1 text-gray-400 hover:text-gray-600 transition-colors"
              aria-label="關閉搜尋"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                strokeWidth={2}
                stroke="currentColor"
                className="w-5 h-5"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>
          </div>

          {/* Results */}
          <div className="max-h-[60vh] overflow-y-auto">
            {loading && (
              <div className="flex items-center justify-center py-8">
                <div className="w-6 h-6 border-2 border-[#9F6B3E] border-t-transparent rounded-full animate-spin" />
              </div>
            )}

            {!loading && query.trim().length > 0 && results.length === 0 && (
              <div className="py-8 text-center text-gray-400">
                <p className="text-sm">找不到相關商品</p>
              </div>
            )}

            {!loading && results.length > 0 && (
              <ul>
                {results.map((product, index) => (
                  <li
                    key={product.id}
                    className="animate-on-scroll in-view"
                    style={{ '--stagger-delay': `${index * 50}ms` } as React.CSSProperties}
                  >
                    <Link
                      href={`/products/${product.slug}`}
                      onClick={onClose}
                      className="flex items-center gap-4 px-5 py-3 hover:bg-gray-50 transition-colors"
                    >
                      <div className="relative w-12 h-12 bg-gray-100 rounded-lg overflow-hidden shrink-0">
                        {product.image ? (
                          <Image
                            src={imageUrl(product.image)!}
                            alt={product.name}
                            fill
                            sizes="48px"
                            className="object-cover"
                          />
                        ) : (
                          <div className="w-full h-full flex items-center justify-center text-gray-300">
                            <svg
                              xmlns="http://www.w3.org/2000/svg"
                              fill="none"
                              viewBox="0 0 24 24"
                              strokeWidth={1}
                              stroke="currentColor"
                              className="w-6 h-6"
                            >
                              <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z"
                              />
                            </svg>
                          </div>
                        )}
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium text-gray-900 truncate">
                          {product.name}
                        </p>
                        <p className="text-sm text-[#9F6B3E] font-semibold">
                          {formatPrice(product.combo_price ?? product.price)}
                          {product.combo_price && (
                            <span className="text-xs text-gray-400 line-through ml-2">
                              {formatPrice(product.price)}
                            </span>
                          )}
                        </p>
                      </div>
                    </Link>
                  </li>
                ))}
              </ul>
            )}

            {!loading && query.trim().length === 0 && (
              <div className="py-8 text-center text-gray-400">
                <p className="text-sm">輸入關鍵字搜尋商品</p>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
