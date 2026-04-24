{{-- Modal content for the "商品" action on the order list page.
     Groups items by bundle_group so an activity bundle renders as ONE
     card listing its constituent items, not N separate cards. Standalone
     products (the '單品' group) still render individually. --}}
@php
    /** @var \Illuminate\Support\Collection $items */
    /** @var \App\Models\Order $order */
    $grouped = $items->groupBy('bundle_group');
    $totalQty = $items->sum('quantity');
    $grandTotal = $items->sum('subtotal');
@endphp

<div class="space-y-3">
    @forelse ($grouped as $groupName => $groupItems)
        @if ($groupName === '單品')
            {{-- Single products: one card per line item --}}
            @foreach ($groupItems as $item)
                @php
                    $gallery = $item->product?->gallery ?? [];
                    $thumb = ! empty($gallery)
                        ? \Illuminate\Support\Facades\Storage::disk('public')->url($gallery[0])
                        : null;
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
            {{-- Activity bundle: header + nested constituent list --}}
            @php
                $bundleSubtotal = $groupItems->sum('subtotal');
                $firstItem = $groupItems->first();
                $firstGallery = $firstItem->product?->gallery ?? [];
                $firstThumb = ! empty($firstGallery)
                    ? \Illuminate\Support\Facades\Storage::disk('public')->url($firstGallery[0])
                    : null;
            @endphp
            <div class="rounded-lg border border-[#e7d9cb] bg-[#fdf7ef] p-3">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-14 h-14 shrink-0 rounded overflow-hidden bg-white flex items-center justify-center border border-[#e7d9cb]">
                        @if ($firstThumb)
                            <img src="{{ $firstThumb }}" alt="{{ $groupName }}" class="w-full h-full object-cover" />
                        @else
                            <x-filament::icon icon="heroicon-o-gift" class="w-6 h-6 text-[#9F6B3E]" />
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-[10px] font-black tracking-[0.15em] text-[#9F6B3E] mb-0.5">活動套組</div>
                        <div class="font-bold text-gray-900 truncate" title="{{ $groupName }}">
                            {{ $groupName }}
                        </div>
                        <div class="text-xs text-gray-500 mt-0.5">
                            {{ $groupItems->count() }} 項 · {{ $groupItems->sum('quantity') }} 件
                        </div>
                    </div>
                    <div class="shrink-0 text-right">
                        <div class="font-bold text-[#9F6B3E]">
                            NT${{ number_format($bundleSubtotal) }}
                        </div>
                    </div>
                </div>
                <div class="space-y-1.5 pl-4 border-l-2 border-[#9F6B3E]/25">
                    @foreach ($groupItems as $item)
                        <div class="flex items-start gap-2 text-sm">
                            <div class="flex-1 text-gray-700">
                                {{ $item->display_name }}
                                @if ($item->bundle_is_gift)
                                    <span class="text-[10px] px-1.5 py-0.5 bg-pink-100 text-pink-700 rounded ml-1 font-semibold">贈品</span>
                                @endif
                            </div>
                            <div class="shrink-0 text-xs text-gray-500 tabular-nums">
                                × {{ $item->quantity }}
                            </div>
                        </div>
                    @endforeach
                </div>
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
