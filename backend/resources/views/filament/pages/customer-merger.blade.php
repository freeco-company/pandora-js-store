<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Top bar --}}
        <div class="flex items-center justify-between bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">偵測到 {{ count($candidates) }} 組重複候選</p>
                <p class="text-xs text-gray-400 mt-1">
                    High = phone 相同 + 一邊是 LINE placeholder email；Medium = phone 相同 + 都真實 email + name 相似
                </p>
            </div>
            <button
                type="button"
                wire:click="refresh"
                class="inline-flex items-center gap-2 px-4 py-2 bg-amber-700 hover:bg-amber-800 text-white text-sm font-bold rounded-lg shadow"
            >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>
                重新掃描
            </button>
        </div>

        {{-- No candidates --}}
        @if (count($candidates) === 0)
            <div class="bg-white dark:bg-gray-800 p-12 rounded-xl shadow-sm text-center">
                <svg class="mx-auto mb-3 text-green-500" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">沒有偵測到重複會員</h3>
                <p class="text-sm text-gray-500 mt-1">所有 customer 都是獨立帳號，或已合併 / 已標記為不同人。</p>
            </div>
        @endif

        {{-- Candidate cards --}}
        @foreach ($candidates as $i => $c)
            @php
                $confidenceColor = $c['confidence'] === 'high' ? 'red' : 'amber';
                $confidenceLabel = $c['confidence'] === 'high' ? '高' : '中';
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden border-l-4 border-{{ $confidenceColor }}-500">

                {{-- Header --}}
                <div class="px-5 py-3 bg-{{ $confidenceColor }}-50 dark:bg-{{ $confidenceColor }}-900/20 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 text-xs font-bold rounded bg-{{ $confidenceColor }}-500 text-white">
                            信心：{{ $confidenceLabel }}
                        </span>
                        <span class="text-xs text-gray-600 dark:text-gray-400">{{ $c['reason'] }}</span>
                    </div>
                </div>

                {{-- Two side-by-side customer cards --}}
                <div class="grid md:grid-cols-2 gap-4 p-5">
                    @foreach (['a', 'b'] as $side)
                        @php $x = $c[$side]; $isRecommended = $x['id'] === $c['recommended_surviving_id']; @endphp
                        <div class="rounded-lg border-2 {{ $isRecommended ? 'border-green-400 bg-green-50/50 dark:bg-green-900/10' : 'border-gray-200 dark:border-gray-700' }} p-4">

                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-gray-500">CUSTOMER #{{ $x['id'] }}</span>
                                @if ($isRecommended)
                                    <span class="text-[10px] px-1.5 py-0.5 bg-green-500 text-white rounded font-bold">建議保留</span>
                                @endif
                            </div>

                            <h4 class="text-base font-bold text-gray-900 dark:text-gray-100">
                                {{ $x['name'] ?: '(無名)' }}
                            </h4>

                            <dl class="mt-3 space-y-1.5 text-sm">
                                <div class="flex">
                                    <dt class="w-20 text-gray-500 text-xs">Email</dt>
                                    <dd class="flex-1 break-all {{ $x['is_placeholder_email'] ? 'text-amber-600 italic' : 'text-gray-900 dark:text-gray-100' }}">
                                        {{ $x['email'] ?: '—' }}
                                        @if ($x['is_placeholder_email']) <span class="text-[10px] text-amber-700">(LINE placeholder)</span> @endif
                                    </dd>
                                </div>
                                <div class="flex">
                                    <dt class="w-20 text-gray-500 text-xs">Phone</dt>
                                    <dd class="text-gray-900 dark:text-gray-100">{{ $x['phone'] ?: '—' }}</dd>
                                </div>
                                @if ($x['google_id'])
                                    <div class="flex">
                                        <dt class="w-20 text-gray-500 text-xs">Google</dt>
                                        <dd class="text-gray-900 dark:text-gray-100 truncate">{{ $x['google_id'] }}</dd>
                                    </div>
                                @endif
                                @if ($x['line_id'])
                                    <div class="flex">
                                        <dt class="w-20 text-gray-500 text-xs">LINE</dt>
                                        <dd class="text-gray-900 dark:text-gray-100 truncate">{{ $x['line_id'] }}</dd>
                                    </div>
                                @endif
                                <div class="flex border-t pt-1.5 mt-1.5 border-gray-100 dark:border-gray-700">
                                    <dt class="w-20 text-gray-500 text-xs">訂單數</dt>
                                    <dd class="font-bold text-gray-900 dark:text-gray-100">{{ $x['total_orders'] }}</dd>
                                </div>
                                <div class="flex">
                                    <dt class="w-20 text-gray-500 text-xs">累積消費</dt>
                                    <dd class="font-bold text-amber-700">NT${{ number_format($x['total_spent']) }}</dd>
                                </div>
                                <div class="flex">
                                    <dt class="w-20 text-gray-500 text-xs">最後活躍</dt>
                                    <dd class="text-gray-600 dark:text-gray-300 text-xs">{{ $x['last_active_date'] ?: '從未' }}</dd>
                                </div>
                                <div class="flex">
                                    <dt class="w-20 text-gray-500 text-xs">建立日</dt>
                                    <dd class="text-gray-600 dark:text-gray-300 text-xs">{{ $x['created_at'] ?: '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                    @endforeach
                </div>

                {{-- Action bar --}}
                <div class="px-5 py-3 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-100 dark:border-gray-700 flex flex-wrap gap-2 items-center">
                    <span class="text-xs text-gray-500 mr-2">合併方向：</span>

                    <button
                        type="button"
                        wire:click="merge({{ $c['a']['id'] }}, {{ $c['b']['id'] }}, {{ $c['recommended_surviving_id'] }})"
                        wire:confirm="確定合併？保留 #{{ $c['recommended_surviving_id'] }}，砍掉另一個。此動作無法輕易回復。"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded-lg shadow"
                    >
                        ✓ 套用建議（保留 #{{ $c['recommended_surviving_id'] }}）
                    </button>

                    @php $other = $c['recommended_surviving_id'] === $c['a']['id'] ? $c['b']['id'] : $c['a']['id']; @endphp
                    <button
                        type="button"
                        wire:click="merge({{ $c['a']['id'] }}, {{ $c['b']['id'] }}, {{ $other }})"
                        wire:confirm="確定改保留 #{{ $other }} 而不是建議的 #{{ $c['recommended_surviving_id'] }}？"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-medium rounded-lg"
                    >
                        改保留 #{{ $other }}
                    </button>

                    <span class="flex-1"></span>

                    <button
                        type="button"
                        wire:click="dismiss({{ $c['a']['id'] }}, {{ $c['b']['id'] }})"
                        wire:confirm="確定這兩個是不同人？下次掃描將跳過。"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-red-300 hover:bg-red-50 text-red-700 text-xs font-medium rounded-lg"
                    >
                        不是同一人
                    </button>
                </div>
            </div>
        @endforeach

    </div>
</x-filament-panels::page>
