import type { Product, CampaignBundle } from './api';

const VIP_THRESHOLD = 4000;

export type PricingTier = 'regular' | 'combo' | 'vip';

/**
 * Cart items are either a normal product at quantity N, or a campaign
 * bundle at quantity N (i.e. "N copies of 母親節套組"). Bundles have
 * their own fixed price; a bundle in the cart also forces every other
 * item to VIP tier.
 */
export type LocalCartItem =
  | { type?: 'product'; product: Product; quantity: number }
  | { type: 'bundle'; bundle: CampaignBundle; quantity: number };

export function isBundleItem(
  item: LocalCartItem,
): item is { type: 'bundle'; bundle: CampaignBundle; quantity: number } {
  return item.type === 'bundle';
}

export function isProductItem(
  item: LocalCartItem,
): item is { type?: 'product'; product: Product; quantity: number } {
  return !isBundleItem(item);
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
  itemPrices: { key: string; unitPrice: number; subtotal: number }[];
} {
  const productItems = items.filter(isProductItem);
  const bundleItems = items.filter(isBundleItem);

  const productQty = productItems.reduce((sum, i) => sum + i.quantity, 0);

  // Bundle in cart → every non-bundle item jumps to VIP tier as well.
  const hasBundle = bundleItems.length > 0;

  let tier: PricingTier = 'regular';
  if (hasBundle) {
    tier = 'vip';
  } else if (productQty >= 2) {
    tier = 'combo';
    const comboTotal = productItems.reduce(
      (sum, i) => sum + getPrice(i.product, 'combo') * i.quantity,
      0,
    );
    if (comboTotal >= VIP_THRESHOLD) tier = 'vip';
  }

  const itemPrices: { key: string; unitPrice: number; subtotal: number }[] = [];

  for (const item of productItems) {
    const unitPrice = getPrice(item.product, tier);
    itemPrices.push({
      key: `p:${item.product.id}`,
      unitPrice,
      subtotal: unitPrice * item.quantity,
    });
  }
  for (const item of bundleItems) {
    // Bundle price is already VIP-locked at the server; don't re-tier.
    itemPrices.push({
      key: `b:${item.bundle.id}`,
      unitPrice: item.bundle.bundle_price,
      subtotal: item.bundle.bundle_price * item.quantity,
    });
  }

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
