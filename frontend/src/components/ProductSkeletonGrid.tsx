/** Shimmer skeleton grid for product loading states. */

export default function ProductSkeletonGrid({ count = 8 }: { count?: number }) {
  return (
    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
      {Array.from({ length: count }).map((_, i) => (
        <div
          key={i}
          className="bg-white rounded-2xl overflow-hidden"
          style={{ border: '1px solid rgba(0,0,0,0.05)' }}
        >
          <div className="aspect-square bg-gradient-to-br from-[#f7eee3] to-[#efe2d1] shimmer" />
          <div className="p-3 sm:p-4 space-y-2">
            <div className="h-3 rounded bg-[#efe2d1] shimmer w-4/5" />
            <div className="h-3 rounded bg-[#efe2d1] shimmer w-3/5" />
            <div className="h-5 rounded bg-[#e7d9cb] shimmer w-1/2 mt-3" />
            <div className="h-8 rounded-full bg-[#e7d9cb] shimmer mt-2" />
          </div>
        </div>
      ))}
      <style>{`
        @keyframes shimmer-flow {
          0% { background-position: -200% 0; }
          100% { background-position: 200% 0; }
        }
        .shimmer {
          background-size: 200% 100%;
          background-image: linear-gradient(
            90deg,
            transparent 0%,
            rgba(255, 255, 255, 0.7) 50%,
            transparent 100%
          ), linear-gradient(to bottom right, #f7eee3, #efe2d1);
          animation: shimmer-flow 1.8s linear infinite;
        }
        @media (prefers-reduced-motion: reduce) {
          .shimmer { animation: none; }
        }
      `}</style>
    </div>
  );
}
