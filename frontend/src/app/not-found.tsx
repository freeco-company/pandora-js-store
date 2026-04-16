import Link from 'next/link';

export default function NotFound() {
  return (
    <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
      <h1 className="text-6xl font-bold text-gray-200 mb-4">404</h1>
      <h2 className="text-xl font-semibold text-gray-900 mb-2">找不到此頁面</h2>
      <p className="text-gray-500 mb-8">您要找的頁面不存在或已被移除。</p>
      <Link
        href="/"
        className="inline-flex items-center px-6 py-3 bg-[#9F6B3E] text-white font-semibold rounded-full hover:bg-[#85572F] transition-colors"
      >
        回首頁
      </Link>
    </div>
  );
}
