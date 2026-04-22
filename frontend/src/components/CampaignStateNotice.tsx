import Link from 'next/link';
import SiteIcon from '@/components/SiteIcon';

type State = 'upcoming' | 'ended';

function formatDateTime(iso: string) {
  const d = new Date(iso);
  return d.toLocaleString('zh-TW', {
    year: 'numeric', month: 'long', day: 'numeric',
    hour: '2-digit', minute: '2-digit', hour12: false,
  });
}

/**
 * Full-page notice shown on /campaigns/[slug] or /bundles/[slug]
 * when the campaign is not currently running.
 */
export default function CampaignStateNotice({
  state,
  name,
  startAt,
  endAt,
}: {
  state: State;
  name: string;
  startAt: string;
  endAt: string;
}) {
  const title = state === 'ended' ? '活動已結束' : '活動尚未開始';
  const subtitle =
    state === 'ended'
      ? `活動已於 ${formatDateTime(endAt)} 結束，感謝您的支持。`
      : `活動將於 ${formatDateTime(startAt)} 正式開始，敬請期待。`;

  return (
    <div className="min-h-[70vh] bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] flex items-center justify-center px-5 py-16">
      <div className="max-w-md w-full text-center bg-white rounded-3xl shadow-xl shadow-[#9F6B3E]/10 border border-[#e7d9cb]/60 p-8 sm:p-10">
        <div className="mx-auto w-16 h-16 rounded-full bg-[#9F6B3E]/10 flex items-center justify-center mb-5">
          <SiteIcon name={state === 'ended' ? 'leaf-falling' : 'sparkle'} size={28} color="#9F6B3E" />
        </div>
        <div className="text-[10px] font-black tracking-[0.3em] text-[#9F6B3E]/70 mb-2">
          {state === 'ended' ? 'CAMPAIGN ENDED' : 'COMING SOON'}
        </div>
        <h1 className="text-2xl sm:text-3xl font-black text-[#3d2e22] mb-3">{title}</h1>
        <p className="text-sm text-[#7a5836] mb-2 font-bold">{name}</p>
        <p className="text-sm text-[#7a5836]/80 leading-relaxed mb-8">{subtitle}</p>
        <div className="flex flex-col sm:flex-row gap-3 justify-center">
          <Link
            href="/"
            className="inline-flex items-center justify-center gap-2 px-6 py-3 bg-[#9F6B3E] text-white font-black rounded-full shadow-lg shadow-[#9F6B3E]/25 hover:bg-[#85572F] transition min-h-[44px]"
          >
            回到首頁
          </Link>
          <Link
            href="/products"
            className="inline-flex items-center justify-center gap-2 px-6 py-3 bg-white text-[#9F6B3E] font-black rounded-full border border-[#e7d9cb] hover:bg-[#fdf7ef] transition min-h-[44px]"
          >
            逛逛其他商品
          </Link>
        </div>
      </div>
    </div>
  );
}
