import ProductSkeletonGrid from '@/components/ProductSkeletonGrid';
import LogoLoader from '@/components/LogoLoader';

export default function Loading() {
  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
      <div className="h-8 w-36 rounded-lg bg-[#efe2d1] shimmer mb-8" />
      <div className="flex gap-2 mb-8 overflow-hidden">
        {[...Array(7)].map((_, i) => (
          <div key={i} className="h-9 w-24 rounded-full bg-[#f7eee3] shimmer" />
        ))}
      </div>
      <div className="flex justify-center mb-8">
        <LogoLoader size={56} />
      </div>
      <ProductSkeletonGrid count={8} />
    </div>
  );
}
