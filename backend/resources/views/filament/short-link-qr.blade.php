@props(['url', 'qr'])

<div class="flex flex-col items-center gap-4 p-4">
    <div class="w-64 h-64 [&>svg]:w-full [&>svg]:h-full">
        {!! $qr !!}
    </div>
    <div class="w-full">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
            短網址
        </label>
        <div class="flex gap-2">
            <input
                type="text"
                value="{{ $url }}"
                readonly
                onclick="this.select()"
                class="flex-1 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm font-mono"
            />
            <button
                type="button"
                onclick="navigator.clipboard.writeText('{{ $url }}').then(() => { this.textContent = '已複製 ✓'; setTimeout(() => this.textContent = '複製', 1500); })"
                class="px-3 py-1.5 bg-primary-600 text-white text-sm rounded hover:bg-primary-700"
            >
                複製
            </button>
        </div>
    </div>
    <p class="text-xs text-gray-500 dark:text-gray-400 text-center">
        貼到 IG 限動 / LINE OA / FB 都可以。掃 QR 也會帶 UTM 進站。
    </p>
</div>
