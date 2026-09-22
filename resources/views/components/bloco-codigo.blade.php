@props([
    'linguagem' => 'plaintext',
    'rotulo' => null,
])

<figure class="overflow-hidden rounded-lg border border-slate-800 bg-slate-900">
    <figcaption class="flex items-center justify-between border-b border-slate-800 px-3 py-1.5">
        <span class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $rotulo ?? $linguagem }}</span>
    </figcaption>
    <pre class="overflow-x-auto p-3 text-xs leading-relaxed"><code class="language-{{ $linguagem }}">{{ trim($slot) }}</code></pre>
</figure>
