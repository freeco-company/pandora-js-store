'use client';

import SiteIcon from './SiteIcon';

export interface PillItem {
  key: string;
  label: string;
  /** SiteIcon name identifier */
  icon?: string;
  /** Accent colour for the icon when pill is inactive */
  iconColor?: string;
}

interface Props {
  items: PillItem[];
  activeKey: string;
  onChange: (key: string) => void;
}

export default function CategoryPills({ items, activeKey, onChange }: Props) {
  return (
    <div className="mb-5 -mx-4 px-4 sm:mx-0 sm:px-0">
      <div className="flex flex-wrap gap-2">
        {items.map((item, i) => {
          const active = activeKey === item.key;
          return (
            <button
              key={item.key || '__all__'}
              onClick={() => onChange(item.key)}
              className={`inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm transition-all duration-300 cursor-pointer ${
                active
                  ? 'bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white shadow-md shadow-[#9F6B3E]/30 scale-105 font-bold'
                  : 'bg-white border border-[#e7d9cb] text-gray-600 hover:border-[#9F6B3E] hover:text-[#9F6B3E] font-medium'
              }`}
              style={{
                opacity: 0,
                animation: `pill-in 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.1) ${i * 50}ms forwards`,
              }}
            >
              {item.icon && (
                <SiteIcon
                  name={item.icon}
                  size={16}
                  color={active ? '#fff' : (item.iconColor || '#9F6B3E')}
                />
              )}
              {item.label}
            </button>
          );
        })}
      </div>
      <style>{`
        @keyframes pill-in {
          from { opacity: 0; transform: translateY(8px); }
          to { opacity: 1; transform: translateY(0); }
        }
      `}</style>
    </div>
  );
}
