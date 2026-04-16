'use client';

export default function Error({
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  return (
    <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
      <h1 className="text-xl font-semibold text-gray-900 mb-2">發生錯誤</h1>
      <p className="text-gray-500 mb-6">很抱歉，頁面載入時發生問題。</p>
      <button
        onClick={reset}
        className="inline-flex items-center px-6 py-3 bg-[#9F6B3E] text-white font-semibold rounded-full hover:bg-[#85572F] transition-colors"
      >
        重新載入
      </button>
    </div>
  );
}
