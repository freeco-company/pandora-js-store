export interface ArticleSubcategory {
  key: string;
  label: string;
}

export interface ArticleTab {
  key: string;
  label: string;
  icon: string;
  iconColor: string;
  subcategories?: ArticleSubcategory[];
}

export const ARTICLE_TABS: ArticleTab[] = [
  { key: '', label: '全部', icon: 'sparkle', iconColor: '#E8A93B' },
  { key: 'blog,news', label: '婕樂纖誌', icon: 'leaf', iconColor: '#4A9D5F' },
  {
    key: 'brand',
    label: '品牌事蹟',
    icon: 'trophy',
    iconColor: '#E8A93B',
    subcategories: [
      { key: 'award-recognition', label: '獎項肯定' },
      { key: 'media-coverage-affirmed', label: '媒體報導' },
      { key: 'activity-highlights', label: '活動花絮' },
    ],
  },
  {
    key: 'recommend',
    label: '口碑推薦',
    icon: 'heart',
    iconColor: '#E0748C',
    subcategories: [
      { key: 'kol-recommendation', label: 'KOL推薦' },
      { key: 'ordinary-people-recommendation', label: '素人推薦' },
      { key: 'program-recommendation', label: '節目推薦' },
      { key: 'magazine-recommendation', label: '雜誌推薦' },
    ],
  },
];

export const ARTICLE_TYPE_LABEL: Record<string, string> = {
  blog: '婕樂纖誌',
  news: '婕樂纖誌',
  brand: '品牌事蹟',
  recommend: '口碑推薦',
};
