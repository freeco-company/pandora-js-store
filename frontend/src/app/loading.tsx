import LogoLoader from '@/components/LogoLoader';

export default function Loading() {
  return (
    <div className="flex items-center justify-center py-24 min-h-[60vh]">
      <LogoLoader size={96} />
    </div>
  );
}
