import SiteIcon from '@/components/SiteIcon';

type Faq = { question: string; answer: string };

/**
 * Lightweight FAQ block rendered beneath product description.
 * Uses native <details>/<summary> — zero JS, fully crawlable, accessible.
 * Content must mirror the FAQPage JSON-LD emitted on the page for
 * Google's rich-result eligibility.
 */
export default function ProductFaq({ faqs }: { faqs: Faq[] }) {
  if (faqs.length === 0) return null;
  return (
    <section className="mt-12 pt-10 border-t border-gray-200">
      <div className="max-w-3xl mx-auto">
        <div className="text-[10px] font-black tracking-[0.2em] text-[#7a5836]">FAQ · 常見問題</div>
        <h2 className="text-xl sm:text-2xl font-black text-gray-900 mt-1 mb-6">購買前，你可能想知道</h2>
        <div className="divide-y divide-[#e7d9cb] rounded-2xl border border-[#e7d9cb] bg-white overflow-hidden">
          {faqs.map((f, i) => (
            <details key={i} className="group">
              <summary className="flex items-start gap-3 px-4 sm:px-5 py-4 cursor-pointer list-none select-none hover:bg-[#fdf7ef] transition-colors">
                <span className="shrink-0 mt-0.5 text-[#9F6B3E]">
                  <SiteIcon name="sparkle" size={16} />
                </span>
                <span className="flex-1 text-sm sm:text-base font-black text-gray-900 leading-snug">
                  {f.question}
                </span>
                <span
                  aria-hidden
                  className="shrink-0 text-[#9F6B3E] text-xl leading-none transition-transform duration-200 group-open:rotate-45"
                >
                  +
                </span>
              </summary>
              <div className="px-4 sm:px-5 pb-4 -mt-1">
                <p className="text-sm text-gray-600 leading-[1.9] pl-7">{f.answer}</p>
              </div>
            </details>
          ))}
        </div>
      </div>
    </section>
  );
}
