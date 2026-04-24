{{-- Order items modal — mirrors the frontend cart's bundle card design
     (活動限時優惠 red badge + 購買內容 / 加贈 sections + FREE badges).
     Standalone products render as individual rows. --}}
@php
    /** @var \Illuminate\Support\Collection $items */
    /** @var \Illuminate\Support\Collection $bundles  keyed by bundle name */
    /** @var \App\Models\Order $order */
    $grouped = $items->groupBy('bundle_group');
    $totalQty = $items->sum('quantity');
    $grandTotal = $items->sum('subtotal');

    $storage = \Illuminate\Support\Facades\Storage::disk('public');
    $thumbFor = function ($path) use ($storage) {
        return $path ? $storage->url($path) : null;
    };
@endphp

<div class="space-y-3">
    @forelse ($grouped as $groupName => $groupItems)
        @if ($groupName === '單品')
            @foreach ($groupItems as $item)
                @php
                    $gallery = $item->product?->gallery ?? [];
                    $thumb = ! empty($gallery) ? $storage->url($gallery[0]) : null;
                @endphp
                <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                    <div class="w-14 h-14 shrink-0 rounded overflow-hidden bg-white dark:bg-gray-900 flex items-center justify-center">
                        @if ($thumb)
                            <img src="{{ $thumb }}" alt="{{ $item->display_name }}" class="w-full h-full object-cover" />
                        @else
                            <x-filament::icon icon="heroicon-o-photo" class="w-6 h-6 text-gray-300" />
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-gray-900 dark:text-gray-100 truncate" title="{{ $item->display_name }}">
                            {{ $item->display_name }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            NT${{ number_format($item->unit_price) }} × {{ $item->quantity }}
                        </div>
                    </div>
                    <div class="shrink-0 text-right">
                        <div class="font-bold text-gray-900 dark:text-gray-100">
                            NT${{ number_format($item->subtotal) }}
                        </div>
                    </div>
                </div>
            @endforeach
        @else
            @php
                $bundle = $bundles[$groupName] ?? null;
                $bundleThumb = $thumbFor($bundle?->image);
                $bundleSubtotal = $groupItems->sum('subtotal');
                // Split buy vs gift (mirrors frontend cart)
                [$giftItems, $buyItems] = $groupItems->partition(fn ($i) => $i->bundle_is_gift);
            @endphp
            <div class="rounded-xl border border-[#e7d9cb] bg-[#fdf7ef] p-4">
                <div class="flex items-start gap-3 mb-3">
                    {{-- Bundle cover thumbnail --}}
                    <div class="w-20 h-20 shrink-0 rounded-lg overflow-hidden bg-white flex items-center justify-center border border-[#e7d9cb]">
                        @if ($bundleThumb)
                            <img src="{{ $bundleThumb }}" alt="{{ $groupName }}" class="w-full h-full object-contain" />
                        @else
                            <x-filament::icon icon="heroicon-o-gift" class="w-6 h-6 text-[#9F6B3E]" />
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-[#c0392b] text-white text-[10px] font-black mb-1.5">
                            活動限時優惠
                        </span>
                        <div class="font-bold text-gray-900 leading-tight" title="{{ $groupName }}">
                            {{ $groupName }}
                        </div>
                    </div>
                    <div class="shrink-0 text-right">
                        <div class="font-bold text-[#9F6B3E]">
                            NT${{ number_format($bundleSubtotal) }}
                        </div>
                    </div>
                </div>

                @if ($buyItems->isNotEmpty())
                    <div class="space-y-1 mb-2">
                        <div class="text-[10px] font-black text-[#9F6B3E] tracking-wider">購買內容</div>
                        @foreach ($buyItems as $item)
                            <div class="flex items-center gap-2 text-sm text-gray-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-[#9F6B3E] shrink-0"></span>
                                <span class="flex-1 min-w-0">{{ $item->display_name }}</span>
                                <span class="shrink-0 text-gray-500 tabular-nums">× {{ $item->quantity }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($giftItems->isNotEmpty())
                    <div class="space-y-1">
                        <div class="text-[10px] font-black text-[#e74c3c] tracking-wider">加贈</div>
                        @foreach ($giftItems as $item)
                            <div class="flex items-center gap-2 text-sm text-gray-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-[#e74c3c] shrink-0"></span>
                                <span class="flex-1 min-w-0">{{ $item->display_name }}</span>
                                <span class="shrink-0 text-gray-500 tabular-nums">× {{ $item->quantity }}</span>
                                <span class="shrink-0 text-[10px] font-black text-[#e74c3c] bg-[#e74c3c]/10 px-1.5 py-0.5 rounded-full">FREE</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    @empty
        <div class="text-center text-gray-400 py-8">此訂單沒有商品紀錄</div>
    @endforelse

    @if ($items->isNotEmpty())
        <div class="flex items-center justify-between pt-3 border-t border-gray-200 dark:border-gray-700 text-sm">
            <span class="text-gray-500 dark:text-gray-400">
                共 {{ $grouped->count() }} 組 · {{ $totalQty }} 件
            </span>
            <span class="font-bold text-gray-900 dark:text-gray-100">
                小計 NT${{ number_format($grandTotal) }}
            </span>
        </div>
    @endif
</div>
