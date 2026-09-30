{{-- Global toast stack. Livewire: $this->dispatch('toast', type: 'success', message: '…'). Session: flash 'toast'. --}}
<div x-data="{
        toasts: [],
        add(detail) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, type: detail.type ?? 'success', message: detail.message });
            setTimeout(() => this.remove(id), detail.timeout ?? 4500);
        },
        remove(id) { this.toasts = this.toasts.filter(t => t.id !== id) },
    }"
    x-on:toast.window="add($event.detail)"
    @if (session('toast')) x-init="add(@js(session('toast')))" @endif
    class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:bottom-auto sm:top-16 sm:items-end"
    aria-live="polite">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-transition.opacity class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border bg-white p-3.5 shadow-(--shadow-overlay)"
            :class="{ 'border-emerald-200': toast.type === 'success', 'border-rose-200': toast.type === 'error', 'border-amber-200': toast.type === 'warning', 'border-sky-200': toast.type === 'info' }">
            <span class="mt-0.5 size-2 shrink-0 rounded-full"
                :class="{ 'bg-emerald-500': toast.type === 'success', 'bg-rose-500': toast.type === 'error', 'bg-amber-500': toast.type === 'warning', 'bg-sky-500': toast.type === 'info' }"></span>
            <p class="flex-1 text-sm text-slate-700" x-text="toast.message"></p>
            <button type="button" class="text-slate-400 hover:text-slate-600" x-on:click="remove(toast.id)">
                <span class="sr-only">{{ __('Dismiss') }}</span>
                <x-ui.icon name="x" class="size-4" />
            </button>
        </div>
    </template>
</div>
