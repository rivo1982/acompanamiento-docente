{{-- @host-owned Copied from Vinkured resources/views/components/page-header.blade.php — do not modify here; replace with the host class at integration. --}}
@props(['titulo', 'descripcion' => null])

<div {{ $attributes->class(['mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between']) }}>
    <div class="min-w-0">
        <h1 class="text-titulo font-semibold text-balance">{{ $titulo }}</h1>
        @if ($descripcion)
            <p class="mt-1 text-base text-texto-atenuado text-pretty">{{ $descripcion }}</p>
        @endif
    </div>
    @isset($acciones)
        <div class="flex shrink-0 flex-wrap gap-3">{{ $acciones }}</div>
    @endisset
</div>
