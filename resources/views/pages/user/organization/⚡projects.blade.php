<?php

use App\Models\Projects;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new class extends Component
{
    use WithPagination, WireUiActions;

    // ── Modals ────────────────────────────────────────────────────────────────
    public bool $modalOpen         = false;
    public bool $confirmDeleteOpen = false;
    public bool $viewModalOpen     = false;

    // ── Form ──────────────────────────────────────────────────────────────────
    public ?int    $editingId       = null;
    public string  $project_title   = '';
    public string  $description     = '';
    public string  $project_budget  = '';

    // ── View ──────────────────────────────────────────────────────────────────
    public ?Projects $viewingProject = null;

    // ── Filters ───────────────────────────────────────────────────────────────
    public string $search = '';

    // ── Delete ────────────────────────────────────────────────────────────────
    public ?int $deletingId = null;

    // ─────────────────────────────────────────────────────────────────────────

    protected function rules(): array
    {
        return [
            'project_title'  => 'required|string|max:255',
            'description'    => 'nullable|string|max:2000',
            'project_budget' => 'required|numeric|min:0',
        ];
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    public function getProjectsProperty()
    {
        $organization_id = auth()->user()->myOrganization->organization_id;

        return Projects::withCount('pledges')
            ->withSum('pledges', 'amount')
            ->withSum('pledges', 'fulfilled_amount')
            ->where('organization_id', $organization_id)
            ->when($this->search, fn ($q) =>
                $q->where(fn ($q) =>
                    $q->where('project_title', 'like', "%{$this->search}%")
                      ->orWhere('description', 'like', "%{$this->search}%")
                )
            )
            ->latest()
            ->paginate(10);
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function updatingSearch(): void { $this->resetPage(); }

    // ── View ──────────────────────────────────────────────────────────────────

    public function openView(int $id): void
    {
        $this->viewingProject = Projects::with(['pledges.user', 'creator'])->findOrFail($id);
        $this->viewModalOpen  = true;
    }

    // ── Create / Edit ─────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        $this->resetForm();
        $this->modalOpen = true;
    }

    public function openEdit(int $id): void
    {
        $this->resetForm();
        $project               = Projects::findOrFail($id);
        $this->editingId       = $project->id;
        $this->project_title   = $project->project_title;
        $this->description     = $project->description ?? '';
        $this->project_budget  = $project->project_budget;
        $this->modalOpen       = true;
    }

    public function save(): void
    {
        $this->validate();

        $organization_id = auth()->user()->myOrganization->organization_id;

        $data = [
            'organization_id' => $organization_id,
            'project_title'   => $this->project_title,
            'description'     => $this->description ?: null,
            'project_budget'  => $this->project_budget,
        ];

        if ($this->editingId) {
            Projects::findOrFail($this->editingId)->update($data);
            $this->notification()->success('Project updated', "{$this->project_title} has been updated.");
        } else {
            $data['created_by'] = auth()->id();
            Projects::create($data);
            $this->notification()->success('Project created', "{$this->project_title} has been added.");
        }

        $this->modalOpen = false;
        $this->resetForm();
        $this->resetPage();
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        $this->deletingId      = $id;
        $this->confirmDeleteOpen = true;
    }

    public function delete(): void
    {
        if (! $this->deletingId) return;

        $project = Projects::findOrFail($this->deletingId);
        $title   = $project->project_title;
        $project->delete();

        $this->notification()->success('Project deleted', "{$title} has been removed.");
        $this->confirmDeleteOpen = false;
        $this->deletingId        = null;
        $this->resetPage();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resetForm(): void
    {
        $this->editingId      = null;
        $this->project_title  = '';
        $this->description    = '';
        $this->project_budget = '';
        $this->resetErrorBag();
    }
};
?>

<div class="min-h-screen bg-gray-50 p-6">

    {{-- ── Page Header ──────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Projects</h1>
            <p class="mt-1 text-sm text-gray-500">Manage fundraising projects and track pledge progress</p>
        </div>
        <x-button wire:click="openCreate" label="New Project" icon="plus" primary />
    </div>

    {{-- ── Search ───────────────────────────────────────────────────────────── --}}
    <div class="mb-4">
        <x-input
            wire:model.live.debounce.300ms="search"
            placeholder="Search projects…"
            icon="magnifying-glass"
            class="max-w-xs"
        />
    </div>

    {{-- ── Table ────────────────────────────────────────────────────────────── --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Project</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Budget</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Pledged</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Collected</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Progress</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($this->projects as $project)
                    @php
                        $pledged   = $project->pledges_sum_amount ?? 0;
                        $collected = $project->pledges_sum_fulfilled_amount ?? 0;
                        $pct       = $project->project_budget > 0
                            ? min(100, ($collected / $project->project_budget) * 100)
                            : 0;
                    @endphp
                    <tr wire:key="proj-{{ $project->id }}" class="transition hover:bg-gray-50">

                        {{-- Title + description --}}
                        <td class="px-6 py-4">
                            <p class="font-medium text-gray-900">{{ $project->project_title }}</p>
                            @if ($project->description)
                                <p class="mt-0.5 max-w-xs truncate text-xs text-gray-400">{{ $project->description }}</p>
                            @endif
                            <p class="mt-0.5 text-xs text-gray-400">{{ $project->pledges_count }} pledge(s)</p>
                        </td>

                        {{-- Budget --}}
                        <td class="px-6 py-4 font-semibold text-gray-900">
                            {{ format_currency($project->project_budget) }}
                        </td>

                        {{-- Pledged --}}
                        <td class="px-6 py-4 text-gray-700">
                            {{ format_currency($pledged) }}
                        </td>

                        {{-- Collected --}}
                        <td class="px-6 py-4 text-green-700 font-medium">
                            {{ format_currency($collected) }}
                        </td>

                        {{-- Progress bar --}}
                        <td class="px-6 py-4 w-36">
                            <div class="flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-200">
                                    <div class="h-2 rounded-full transition-all
                                        {{ $pct >= 100 ? 'bg-green-500' : ($pct >= 50 ? 'bg-blue-500' : 'bg-yellow-400') }}"
                                         style="width: {{ $pct }}%"></div>
                                </div>
                                <span class="text-xs text-gray-500 w-8 text-right">{{ number_format($pct, 0) }}%</span>
                            </div>
                        </td>

                        {{-- Actions --}}
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <x-mini-button wire:click="openView({{ $project->id }})"      icon="eye"    flat secondary sm />
                                <x-mini-button wire:click="openEdit({{ $project->id }})"      icon="pencil" flat secondary sm />
                                <x-mini-button wire:click="confirmDelete({{ $project->id }})" icon="trash"  flat negative  sm />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-2 text-gray-400">
                                <x-icon name="briefcase" class="h-10 w-10 opacity-40" />
                                <p class="text-sm font-medium">No projects found</p>
                                @if ($search)
                                    <p class="text-xs">Try a different search term</p>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($this->projects->hasPages())
            <div class="border-t border-gray-100 px-6 py-4">
                {{ $this->projects->links() }}
            </div>
        @endif
    </div>

    {{-- ── View Project Modal ───────────────────────────────────────────────── --}}
    <x-modal wire:model="viewModalOpen" max-width="3xl">
        @if ($viewingProject)
            @php
                $vPledged   = $viewingProject->pledges->sum('amount');
                $vCollected = $viewingProject->pledges->sum('fulfilled_amount');
                $vPct       = $viewingProject->project_budget > 0
                    ? min(100, ($vCollected / $viewingProject->project_budget) * 100) : 0;
                $statusColors = [
                    'pending'   => 'bg-yellow-100 text-yellow-700',
                    'partial'   => 'bg-blue-100 text-blue-700',
                    'fulfilled' => 'bg-green-100 text-green-700',
                    'cancelled' => 'bg-red-100 text-red-700',
                ];
            @endphp
            <x-card :title="$viewingProject->project_title" class="relative">

                {{-- Description --}}
                @if ($viewingProject->description)
                    <p class="mb-6 text-sm text-gray-600">{{ $viewingProject->description }}</p>
                @endif

                {{-- Summary stats --}}
                <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 text-center">
                        <p class="text-lg font-bold text-gray-900">{{ format_currency($viewingProject->project_budget) }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">Budget</p>
                    </div>
                    <div class="rounded-xl border border-blue-100 bg-blue-50 p-4 text-center">
                        <p class="text-lg font-bold text-blue-700">{{ format_currency($vPledged) }}</p>
                        <p class="mt-0.5 text-xs text-blue-600">Total Pledged</p>
                    </div>
                    <div class="rounded-xl border border-green-100 bg-green-50 p-4 text-center">
                        <p class="text-lg font-bold text-green-700">{{ format_currency($vCollected) }}</p>
                        <p class="mt-0.5 text-xs text-green-600">Collected</p>
                    </div>
                    <div class="rounded-xl border border-primary-100 bg-primary-50 p-4 text-center">
                        <p class="text-lg font-bold text-primary-700">{{ number_format($vPct, 0) }}%</p>
                        <p class="mt-0.5 text-xs text-primary-600">Of Budget</p>
                    </div>
                </div>

                {{-- Progress bar --}}
                <div class="mb-6 h-3 overflow-hidden rounded-full bg-gray-200">
                    <div class="h-3 rounded-full transition-all
                        {{ $vPct >= 100 ? 'bg-green-500' : ($vPct >= 50 ? 'bg-blue-500' : 'bg-yellow-400') }}"
                         style="width: {{ $vPct }}%"></div>
                </div>

                {{-- Pledges table --}}
                <div>
                    <p class="mb-2 text-sm font-semibold text-gray-700">
                        Pledges ({{ $viewingProject->pledges->count() }})
                    </p>
                    @if ($viewingProject->pledges->isEmpty())
                        <p class="text-sm text-gray-400">No pledges yet for this project.</p>
                    @else
                        <div class="overflow-hidden rounded-lg border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-100 text-xs">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-500">Member</th>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-500">Pledged</th>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-500">Paid</th>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-500">Balance</th>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-500">Status</th>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-500">Deadline</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 bg-white">
                                    @foreach ($viewingProject->pledges as $pledge)
                                        <tr>
                                            <td class="px-4 py-2 font-medium text-gray-900">{{ $pledge->user?->name }}</td>
                                            <td class="px-4 py-2 text-gray-700">{{ format_currency($pledge->amount) }}</td>
                                            <td class="px-4 py-2 text-green-700">{{ format_currency($pledge->fulfilled_amount) }}</td>
                                            <td class="px-4 py-2 text-orange-700">{{ format_currency($pledge->balance) }}</td>
                                            <td class="px-4 py-2">
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize {{ $statusColors[$pledge->status] ?? 'bg-gray-100 text-gray-600' }}">
                                                    {{ $pledge->status }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-gray-500">
                                                {{ $pledge->deadline?->format('M d, Y') ?? '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <x-slot name="footer">
                    <div class="flex justify-end gap-3">
                        <x-button wire:click="openEdit({{ $viewingProject->id }})" label="Edit Project" secondary />
                        <x-button wire:click="$set('viewModalOpen', false)" label="Close" flat />
                    </div>
                </x-slot>
            </x-card>
        @endif
    </x-modal>

    {{-- ── Create / Edit Modal ─────────────────────────────────────────────── --}}
    <x-modal wire:model="modalOpen" max-width="lg">
        <x-card :title="$editingId ? 'Edit Project' : 'New Project'" class="relative">
            <div class="space-y-4">
                <x-input
                    wire:model="project_title"
                    label="Project Title"
                    placeholder="e.g. Church Renovation Fund"
                    icon="briefcase"
                />
                <x-input
                    wire:model="project_budget"
                    label="Budget"
                    placeholder="0.00"
                    icon="currency-dollar"
                    type="number"
                    min="0"
                    step="0.01"
                />
                <x-textarea
                    wire:model="description"
                    label="Description (optional)"
                    placeholder="Brief description of the project…"
                    rows="3"
                />
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button wire:click="$set('modalOpen', false)" label="Cancel" flat />
                    <x-button
                        wire:click="save"
                        wire:loading.attr="disabled"
                        label="{{ $editingId ? 'Update Project' : 'Create Project' }}"
                        primary
                        spinner="save"
                    />
                </div>
            </x-slot>
        </x-card>
    </x-modal>

    {{-- ── Confirm Delete ───────────────────────────────────────────────────── --}}
    <x-modal wire:model="confirmDeleteOpen" max-width="sm">
        <x-card title="Delete Project" class="relative">
            <p class="text-sm text-gray-600">
                Are you sure you want to delete this project? All associated pledges will lose their project reference.
            </p>
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button wire:click="$set('confirmDeleteOpen', false)" label="Cancel" flat />
                    <x-button wire:click="delete" wire:loading.attr="disabled" label="Delete" negative spinner="delete" />
                </div>
            </x-slot>
        </x-card>
    </x-modal>

</div>
