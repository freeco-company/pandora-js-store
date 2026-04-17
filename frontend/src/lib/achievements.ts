/**
 * Achievement catalog — must mirror backend/app/Services/AchievementCatalog.php.
 * If you add a code here, add it there (and vice versa).
 */

export type AchievementTier = 'bronze' | 'silver' | 'gold';

export interface AchievementDef {
  code: string;
  name: string;
  description: string;
  emoji: string;
  tier: AchievementTier;
}

export const ACHIEVEMENT_CATALOG: Record<string, AchievementDef> = {
  first_browse: { code: 'first_browse', name: '好奇仙女', description: '逛了第一件商品', emoji: 'shopping-bag', tier: 'bronze' },
  first_article: { code: 'first_article', name: '閱讀仙女', description: '看了第一篇仙女誌', emoji: 'book', tier: 'bronze' },
  first_brand: { code: 'first_brand', name: '知心仙女', description: '認識 FP 團隊', emoji: 'cherry-blossom', tier: 'bronze' },
  first_cart: { code: 'first_cart', name: '心動瞬間', description: '加入第一件商品到購物車', emoji: 'cart', tier: 'bronze' },
  first_mascot: { code: 'first_mascot', name: '芽芽之友', description: '進入芽芽之家', emoji: 'sprout', tier: 'bronze' },
  first_order: { code: 'first_order', name: '首購達成', description: '完成第一筆訂單', emoji: 'party', tier: 'silver' },

  order_3: { code: 'order_3', name: '回頭仙女', description: '累積 3 筆訂單', emoji: 'cherry-blossom', tier: 'silver' },
  order_5: { code: 'order_5', name: '熟客仙女', description: '累積 5 筆訂單', emoji: 'star', tier: 'silver' },
  order_10: { code: 'order_10', name: '鐵粉仙女', description: '累積 10 筆訂單', emoji: 'diamond', tier: 'gold' },

  spend_1k: { code: 'spend_1k', name: '千元俱樂部', description: '累積消費滿 NT$1,000', emoji: 'money-bag', tier: 'bronze' },
  spend_5k: { code: 'spend_5k', name: '五千達人', description: '累積消費滿 NT$5,000', emoji: 'diamond', tier: 'silver' },
  spend_10k: { code: 'spend_10k', name: '萬元貴賓', description: '累積消費滿 NT$10,000', emoji: 'crown', tier: 'gold' },

  unlock_combo: { code: 'unlock_combo', name: '組合解鎖', description: '首次使用組合價下單', emoji: 'ribbon', tier: 'bronze' },
  unlock_vip: { code: 'unlock_vip', name: 'VIP 解鎖', description: '首次觸發 VIP 價下單', emoji: 'sparkle', tier: 'silver' },
  vip_3: { code: 'vip_3', name: 'VIP 常客', description: '累積 3 筆 VIP 價訂單', emoji: 'trophy', tier: 'gold' },

  explore_slimming: { code: 'explore_slimming', name: '纖體探索', description: '購買體重管理商品', emoji: 'leaf', tier: 'bronze' },
  explore_health: { code: 'explore_health', name: '保健探索', description: '購買健康保健商品', emoji: 'leaf-falling', tier: 'bronze' },
  explore_beauty: { code: 'explore_beauty', name: '美容探索', description: '購買美容保養商品', emoji: 'hibiscus', tier: 'bronze' },
  explore_all: { code: 'explore_all', name: '全品類達人', description: '三大品類皆有購買', emoji: 'rainbow', tier: 'gold' },

  streak_7: { code: 'streak_7', name: '七日連訪', description: '連續 7 天造訪', emoji: 'fire', tier: 'silver' },
  streak_30: { code: 'streak_30', name: '月月相伴', description: '連續 30 天造訪', emoji: 'fire', tier: 'gold' },
  streak_100: { code: 'streak_100', name: '百日傳說', description: '連續 100 天造訪', emoji: 'star', tier: 'gold' },

  first_referral: { code: 'first_referral', name: '第一位推薦者', description: '成功邀請一位朋友完成首單', emoji: 'gift', tier: 'silver' },
  referral_3: { code: 'referral_3', name: '仙女推廣大使', description: '累積推薦 3 位朋友', emoji: 'gift', tier: 'gold' },
  referral_10: { code: 'referral_10', name: '仙女 KOL', description: '累積推薦 10 位朋友', emoji: 'crown', tier: 'gold' },
  first_referred: { code: 'first_referred', name: '被邀請的仙女', description: '透過朋友推薦碼加入', emoji: 'cherry-blossom', tier: 'bronze' },
};

export const TIER_GRADIENTS: Record<AchievementTier, string> = {
  bronze: 'from-[#e7d9cb] to-[#c9b89a]',
  silver: 'from-[#d4d4d8] to-[#a1a1aa]',
  gold: 'from-[#fcd561] to-[#eab308]',
};

export type MascotStage = 'seedling' | 'sprout' | 'bloom';
export type MascotMood = 'neutral' | 'happy' | 'excited';

/** Pick mascot stage from streak days. */
export function stageFromStreak(streak: number): MascotStage {
  if (streak >= 7) return 'bloom';
  if (streak >= 3) return 'sprout';
  return 'seedling';
}
