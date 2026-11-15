<?php

use Livewire\Component;
use App\Services\Context\Contracts\ContextManagerInterface;

new class extends Component {
    public ?int $selectedDepartmentId = null;
    public ?string $selectedRole = null;

    public function mount(ContextManagerInterface $context)
    {
        // Ambil Departemen dan Role aktif awal melalui ContextManager
        $activeDept = $context->getActiveDepartment();
        $this->selectedDepartmentId = $activeDept ? $activeDept->id : null;

        $this->selectedRole = $context->getActiveRole();
    }

    public function selectDepartment(int $deptId)
    {
        $this->selectedDepartmentId = $deptId;
    }

    public function selectRole(string $roleName)
    {
        $this->selectedRole = $roleName;
    }

    public function applyContextSwitch(ContextManagerInterface $context)
    {
        // Set Department aktif di ContextManager
        if ($this->selectedDepartmentId) {
            $context->setActiveDepartment((int) $this->selectedDepartmentId);
        }

        // Set Role aktif di ContextManager
        if ($this->selectedRole) {
            $context->setActiveRole($this->selectedRole);
        }

        return redirect(request()->header('Referer', '/'));
    }

    public function with(ContextManagerInterface $context)
    {
        $departments = $context->getAvailableDepartments();
        $roles = $context->getAvailableRoles();
        
        $currentDept = $departments->firstWhere('id', $this->selectedDepartmentId);

        return [
            'availableDepartments' => $departments,
            'availableRoles' => $roles,
            'activeAssignmentType' => $context->getActiveAssignmentType(),
            'currentDepartmentName' => $currentDept->name ?? 'Pilih Departemen',
            'currentRoleName' => $this->selectedRole ? ucwords(str_replace('_', ' ', $this->selectedRole)) : 'Pilih Peran',
        ];
    }
}; ?>

<div x-data="{ open: false }">
    <!-- Trigger Button -->
    <button 
        type="button"
        @click="open = true"
        class="inline-flex items-center gap-x-2 px-3 py-1.5 rounded-lg border border-gray-900/10 dark:border-white/10 bg-white dark:bg-gray-900 shadow-sm hover:bg-gray-50 dark:hover:bg-white/5 text-xs font-medium text-gray-700 dark:text-gray-200 transition focus:outline-none focus:ring-2 focus:ring-primary-500/50"
    >
        <!-- Department Badge/Text -->
        <div class="flex items-center gap-1.5">
            <svg class="w-4 h-4 text-gray-400 dark:text-gray-500 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6h1.5m-1.5 3h1.5m-1.5 3h1.5M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
            </svg>
            <span class="truncate max-w-[120px]">{{ $currentDepartmentName }}</span>
            
            @if($activeAssignmentType)
                <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold tracking-wide uppercase ring-1 ring-inset {{ $activeAssignmentType === 'plt' ? 'bg-amber-500/10 text-amber-700 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-400 dark:ring-amber-400/30' : 'bg-primary-500/10 text-primary-700 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30' }}">
                    {{ $activeAssignmentType }}
                </span>
            @endif
        </div>

        <span class="text-gray-300 dark:text-gray-700">|</span>

        <!-- Role Badge/Text -->
        <div class="flex items-center gap-1.5">
            <svg class="w-4 h-4 text-gray-400 dark:text-gray-500 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span class="truncate max-w-[120px]">{{ $currentRoleName }}</span>
        </div>

        <!-- Chevron Icon -->
        <svg class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500 ml-1 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
        </svg>
    </button>

    <!-- Modal Backdrop & Window -->
    <template x-teleport="body">
        <div 
            x-show="open" 
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
        >
            <!-- Overlay Backdrop -->
            <div 
                x-show="open"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="open = false" 
                class="fixed inset-0 bg-gray-950/50 dark:bg-gray-950/75 backdrop-blur-sm"
            ></div>

            <!-- Modal Content Card -->
            <div 
                x-show="open"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative w-full max-w-lg rounded-xl bg-white dark:bg-gray-900 border border-gray-900/10 dark:border-white/10 shadow-xl overflow-hidden z-10"
            >
                <!-- Modal Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-white/5">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                            Ganti Konteks Kerja
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Pilih departemen dan peran untuk menyesuaikan akses aplikasi Anda.
                        </p>
                    </div>
                    <button 
                        type="button" 
                        @click="open = false" 
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1 rounded-lg transition"
                    >
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 space-y-6 max-h-[70vh] overflow-y-auto">
                    <!-- Department Selection -->
                    @if($availableDepartments->isNotEmpty())
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2.5">
                                Pilih Departemen
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach($availableDepartments as $dept)
                                    <button 
                                        type="button"
                                        wire:click="selectDepartment({{ $dept->id }})"
                                        class="flex items-center justify-between p-3 rounded-lg border text-left text-xs transition {{ $selectedDepartmentId === $dept->id ? 'border-primary-600 bg-primary-50/50 dark:bg-primary-500/10 text-primary-700 dark:text-primary-400 font-semibold ring-1 ring-primary-600' : 'border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5' }}"
                                    >
                                        <span class="truncate">{{ $dept->name }}</span>
                                        @if($selectedDepartmentId === $dept->id)
                                            <svg class="w-4 h-4 text-primary-600 dark:text-primary-400 shrink-0 ml-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Role Selection -->
                    @if($availableRoles->isNotEmpty())
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2.5">
                                Pilih Peran (Role)
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach($availableRoles as $role)
                                    <button 
                                        type="button"
                                        wire:click="selectRole('{{ $role->name }}')"
                                        class="flex items-center justify-between p-3 rounded-lg border text-left text-xs transition {{ $selectedRole === $role->name ? 'border-primary-600 bg-primary-50/50 dark:bg-primary-500/10 text-primary-700 dark:text-primary-400 font-semibold ring-1 ring-primary-600' : 'border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5' }}"
                                    >
                                        <span class="truncate">{{ ucwords(str_replace('_', ' ', $role->name)) }}</span>
                                        @if($selectedRole === $role->name)
                                            <svg class="w-4 h-4 text-primary-600 dark:text-primary-400 shrink-0 ml-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-end gap-3 px-6 py-4 bg-gray-50/50 dark:bg-white/[0.02] border-t border-gray-100 dark:border-white/5">
                    <button 
                        type="button" 
                        @click="open = false" 
                        class="px-3.5 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/10 rounded-lg transition"
                    >
                        Batal
                    </button>
                    <button 
                        type="button" 
                        wire:click="applyContextSwitch" 
                        class="px-4 py-2 text-xs font-semibold text-white bg-primary-600 hover:bg-primary-500 rounded-lg shadow-sm transition focus:outline-none focus:ring-2 focus:ring-primary-500/50"
                    >
                        Terapkan Perubahan
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>