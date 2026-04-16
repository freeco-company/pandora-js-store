import type { Product } from './api';

const VIP_THRESHOLD = 4000;

export type PricingTier = 'regular' | 'combo' | 'vip';

export interface LocalCartItem {
  product: Product;
  quantity: number;
}

export function getPrice(product: Product, tier: PricingTier): number {
  switch (tier) {
    case 'vip':
      return product.vip_price ?? product.price;
    case 'combo':
      return product.combo_price ?? product.price;
    default:
      return product.price;
  }
}

export function calculateCartLocally(items: LocalCartItem[]): {
  tier: PricingTier;
  total: number;
  itemPrices: { productId: number; unitPrice: number; subtotal: number }[];
} {
  const totalQuantity = items.reduce((sum, i) => sum + i.quantity, 0);

  let tier: PricingTier = 'regular';
  if (totalQuantity >= 2) {
    tier = 'combo';
    const comboTotal = items.reduce(
      (sum, i) => sum + getPrice(i.product, 'combo') * i.quantity,
      0
    );
    if (comboTotal >= VIP_THRESHOLD) tier = 'vip';
  }

  const itemPrices = items.map((i) => {
    const unitPrice = getPrice(i.product, tier);
    return { productId: i.product.id, unitPrice, subtotal: unitPrice * i.quantity };
  });

  return {
    tier,
    total: itemPrices.reduce((sum, i) => sum + i.subtotal, 0),
    itemPrices,
  };
}

export function tierLabel(tier: PricingTier): string {
  switch (tier) {
    case 'vip':
      return 'VIP 優惠價';
    case 'combo':
      return '1+1 搭配價';
    default:
      return '原價';
  }
}
