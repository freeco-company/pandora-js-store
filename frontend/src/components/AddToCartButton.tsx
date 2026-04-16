'use client';

import { useState, useRef } from 'react';
import type { Product } from '@/lib/api';
import { useCart } from './CartProvider';
import { flyToCart } from '@/lib/animations';
import { useToast } from './Toast';

export default function AddToCartButton({ product }: { product: Product }) {
  const { addToCart } = useCart();
  const { toast } = useToast();
  const [quantity, setQuantity] = useState(1);
  const buttonRef = useRef<HTMLButtonElement>(null);

  const handleAdd = () => {
    addToCart(product, quantity);

    if (buttonRef.current) {
      flyToCart(buttonRef.current);
    }

    toast('已加入購物車');
  };

  return (
    <div className="space-y-3">
      {/* Quantity Selector */}
      <div className="inline-flex items-center border border-gray-200 rounded-full">
        <button
          onClick={() => setQuantity(Math.max(1, quantity - 1))}
          className="w-10 h-10 flex items-center justify-center text-gray-600 hover:text-gray-900 transition-colors"
          aria-label="減少數量"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor" className="w-4 h-4">
            <path strokeLinecap="round" strokeLinejoin="round" d="M5 12h14" />
          </svg>
        </button>
        <span className="w-12 text-center font-medium text-gray-900">
          {quantity}
        </span>
        <button
          onClick={() => setQuantity(quantity + 1)}
          className="w-10 h-10 flex items-center justify-center text-gray-600 hover:text-gray-900 transition-colors"
          aria-label="增加數量"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor" className="w-4 h-4">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
        </button>
      </div>

      {/* Add to Cart Button */}
      <button
        ref={buttonRef}
        onClick={handleAdd}
        className="w-full py-3 px-6 font-semibold rounded-full bg-[#9F6B3E] text-white hover:bg-[#85572F] transition-all btn-press"
      >
        加入購物車
      </button>
    </div>
  );
}
