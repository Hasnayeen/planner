<x-filament-panels::page>
    <div class="yiag flex justify-end space-x-4">
        <x-filament::button tooltip="Hide past months" color="gray" size="xs" wire:click="$toggle('hidePastMonths')">
            @if ($hidePastMonths)
                <x-heroicon-o-eye class="w-5 h-5"/>
            @else
                <x-heroicon-o-eye-slash class="w-5 h-5"/>
            @endif
        </x-filament::button>
        <x-filament::button color="gray" size="xs" @click="document.getElementById('yiag-ctn').scrollLeft -= 500">
            <x-heroicon-m-arrow-long-left class="w-5 h-5"/>
        </x-filament::button>
        <x-filament::button color="gray" size="xs" @click="document.getElementById('yiag-ctn').scrollLeft += 500">
            <x-heroicon-m-arrow-long-right class="w-5 h-5"/>
        </x-filament::button>
    </div>
    <div id="yiag-ctn" class="overflow-x-auto">
        <div class="w-max">
            {{ $this->infolist }}
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
