<?php

use App\Models\AuditLog;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $filterAction = '';
    public string $search       = '';

    public function getOrgIdProperty(): int
    {
        return auth()->user()->myOrganization->organization_id;
    }

    public function getLogsProperty()
    {
        return AuditLog::where('organization_id', $this->orgId)
            ->with('user')
            ->when($this->filterAction, fn ($q) => $q->where('action', $this->filterAction))
            ->when($this->search, fn ($q) => $q->where('description', 'like', "%{$this->search}%"))
            ->orderByDesc('created_at')
            ->paginate(25);
    }

    public function updatingSearch(): void  { $this->resetPage(); }
    public function updatingFilterAction(): void { $this->resetPage(); }
};
?>

<div class="min-h-screen bg-gray-50 p-6">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Audit Log</h1>
        <p class="mt-1 text-sm text-gray-500">A complete record of all financial entries, edits, and deletions</p>
    </div>

    {{-- Filters --}}
    <div class="mb-5 flex flex-wrap items-end gap-3">
        <div class="min-w-[260px]">
            <x-input wire:model.live.debounce.300ms="search" placeholder="Search descriptions…" icon="magnifying-glass" />
        </div>
        <x-select wire:model.live="filterAction" class="w-36">
            <option value="">All actions</option>
            <option value="created">Created</option>
            <option value="updated">Updated</option>
            <option value="deleted">Deleted</option>
        </x-select>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">When</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">User</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Action</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Description</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 bg-white">
                @forelse ($this->logs as $log)
                    @php
                        $colors = [
                            'created' => 'bg-green-100 text-green-700',
                            'updated' => 'bg-blue-100 text-blue-700',
                            'deleted' => 'bg-red-100 text-red-700',
                        ];
                    @endphp
                    <tr class="hover:bg-gray-50" wire:key="log-{{ $log->id }}">
                        <td class="px-6 py-4 text-gray-500 text-xs whitespace-nowrap">
                            {{ $log->created_at->format('M d, Y') }}<br>
                            <span class="text-gray-400">{{ $log->created_at->format('H:i') }}</span>
                        </td>
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $log->user?->name ?? 'System' }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize {{ $colors[$log->action] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ $log->action }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-700">{{ $log->description }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-2 text-gray-400">
                                <x-icon name="clipboard-document-list" class="h-10 w-10 opacity-40" />
                                <p class="text-sm">No audit entries yet. Changes to payments, expenses, and pledges will appear here.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($this->logs->hasPages())
            <div class="border-t border-gray-100 px-6 py-4">
                {{ $this->logs->links() }}
            </div>
        @endif
    </div>
</div>
