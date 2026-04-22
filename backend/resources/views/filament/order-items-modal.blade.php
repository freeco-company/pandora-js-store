{{-- Modal content for the "商品" action on the order list page.
     Shows each line item with thumbnail, name, qty, unit price and
     subtotal, so the admin can eyeball what was in an order without
     clicking into the edit page. --}}
@php
    /** @var \Illuminate\Support\Collection $items */
    /** @var \App\Models\Order $order */
    $totalQty = $items->sum('quantity');
@endphp

<div class="space-y-3">
    @forelse ($items as $item)
        @php
            $gallery = $item->product?->gallery ?? [];
            $thumb = ! empty($gallery) ? \Illuminate\Support\Facades\Storage::disk('public')->url($gallery[0]) : null;
        @endphp
        <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
            <div class="w-14 h-14 shrink-0 rounded overflow-hidden bg-white dark:bg-gray-900 flex items-center justify-center">
                @if ($thumb)
                    <img src="{{ $thumb }}" alt="{{ $item->product_name }}" class="w-full h-full object-cover" />
                @else
                    <x-filament::icon icon="heroicon-o-photo" class="w-6 h-6 text-gray-300" />
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <div class="font-semibold text-gray-900 dark:text-gray-100 truncate" title="{{ $item->product_name }}">
                    {{ $item->product_name }}
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
    @empty
        <div class="text-center text-gray-400 py-8">此訂單沒有商品紀錄</div>
    @endforelse

    @if ($items->isNotEmpty())
        <div class="flex items-center justify-between pt-3 border-t border-gray-200 dark:border-gray-700 text-sm">
            <span class="text-gray-500 dark:text-gray-400">共 {{ $items->count() }} 項 · {{ $totalQty }} 件</span>
            <span class="font-bold text-gray-900 dark:text-gray-100">
                小計 NT${{ number_format($items->sum('subtotal')) }}
            </span>
        </div>
    @endif
</div>
