<div x-data="{ hover: null }" x-on:schedule-clear-save-message.window="setTimeout(() => $wire.clearSaveMessage(), 3000)" x-on:beforeunload.window="if ($wire.get('pending').length > 0) event.preventDefault()" x-on:open-pdf.window="window.location.href = event.detail.url" class="space-y-6">

    {{-- Toolbar: paciente, dentición, numeración, guardar --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold">{{ $patient->fullName() }}</h2>
            <p class="h-4 text-xs text-gray-500"
               x-text="hover ? ('Pieza ' + hover.code + ' · ' + hover.face) : ''"></p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="inline-flex overflow-hidden rounded-lg border border-gray-300">
                <button type="button" wire:click="setDentition('adult')"
                    @class([
                        'px-3 py-1.5 text-xs font-semibold transition-colors',
                        'bg-primary-600 text-white' => $dentition === 'adult',
                        'bg-white text-gray-700 hover:bg-gray-50' => $dentition !== 'adult',
                    ])>Adulto · 32</button>
                <span @if ($patient->isAdult()) x-tooltip="{ content: 'Paciente mayor de edad: solo dentición adulta', theme: $store.theme }" @endif class="inline-flex">
                    <button type="button" wire:click="setDentition('child')"
                        @disabled($patient->isAdult())
                        @class([
                            'border-l border-gray-300 px-3 py-1.5 text-xs font-semibold transition-colors',
                            'bg-primary-600 text-white' => $dentition === 'child',
                            'bg-white text-gray-700 hover:bg-gray-50' => $dentition !== 'child' && ! $patient->isAdult(),
                            'cursor-not-allowed bg-gray-100 text-gray-400' => $patient->isAdult(),
                        ])>Niño · 20</button>
                </span>
            </div>

            <div class="inline-flex overflow-hidden rounded-lg border border-gray-300">
                <button type="button" wire:click="setNumberingSystem('fdi')"
                    @class([
                        'px-3 py-1.5 text-xs font-semibold transition-colors',
                        'bg-primary-600 text-white' => $numberingSystem === 'fdi',
                        'bg-white text-gray-700 hover:bg-gray-50' => $numberingSystem !== 'fdi',
                    ])>FDI</button>
                <button type="button" wire:click="setNumberingSystem('universal')"
                    @class([
                        'border-l border-gray-300 px-3 py-1.5 text-xs font-semibold transition-colors',
                        'bg-primary-600 text-white' => $numberingSystem === 'universal',
                        'bg-white text-gray-700 hover:bg-gray-50' => $numberingSystem !== 'universal',
                    ])>Universal</button>
            </div>

            <button type="button" wire:click="save"
                class="inline-flex items-center gap-1.5 rounded-lg bg-success-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-success-500">
                Guardar
            </button>

            <button type="button" wire:click="exportPdf"
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50">
                Exportar PDF
            </button>
        </div>
    </div>

    {{-- Notas del odontograma y fecha de examen --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="examinedAt" class="mb-1 block text-xs font-semibold text-gray-600">Fecha de examen</label>
            <input type="date" wire:model="examinedAt" id="examinedAt"
                class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm" />
        </div>
        <div>
            <label for="notes" class="mb-1 block text-xs font-semibold text-gray-600">Notas del odontograma</label>
            <textarea wire:model="notes" id="notes" rows="2"
                class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm"
                placeholder="Observaciones generales del examen..."></textarea>
        </div>
    </div>

    {{-- Paleta de condiciones --}}
    <div class="flex flex-wrap gap-2">
        @foreach ($this->conditions as $condition)
            <button type="button"
                wire:click="pickCondition('{{ $condition->code }}')"
                class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold transition-all"
                style="border-color: {{ $condition->color }};
                       {{ $activeCondition === $condition->code ? 'background:'.$condition->color.'; color:#fff;' : 'color:'.$condition->color.';' }}">
                <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $condition->color }}"></span>
                {{ $condition->label }}
            </button>
        @endforeach
    </div>

    {{-- Arco superior --}}
    <div class="flex justify-center overflow-x-auto px-2 pb-1 pt-3">
        <div class="flex gap-1 sm:gap-2">
            @foreach ($this->definitions['upper'] ?? [] as $definition)
                <div class="tooth-wrap group relative">
                    {!! $this->toothSvg($definition) !!}
                    <button type="button"
                        wire:click="openToothNote({{ $definition->fdi_code }})"
                        class="absolute -top-1 -right-1 hidden h-4 w-4 items-center justify-center rounded-full bg-amber-400 text-[8px] font-bold text-white shadow group-hover:flex"
                        x-tooltip="{ content: 'Nota del diente', theme: $store.theme }">N</button>
                </div>
            @endforeach
        </div>
    </div>

    <div class="flex items-center gap-3">
        <div class="h-px flex-1 bg-gray-200"></div>
        <span class="text-[10px] font-mono text-gray-400">LÍNEA MEDIA</span>
        <div class="h-px flex-1 bg-gray-200"></div>
    </div>

    {{-- Arco inferior --}}
    <div class="flex justify-center overflow-x-auto px-2 pt-3">
        <div class="flex gap-1 sm:gap-2">
            @foreach ($this->definitions['lower'] ?? [] as $definition)
                <div class="tooth-wrap group relative">
                    {!! $this->toothSvg($definition) !!}
                    <button type="button"
                        wire:click="openToothNote({{ $definition->fdi_code }})"
                        class="absolute -top-1 -right-1 hidden h-4 w-4 items-center justify-center rounded-full bg-amber-400 text-[8px] font-bold text-white shadow group-hover:flex"
                        x-tooltip="{ content: 'Nota del diente', theme: $store.theme }">N</button>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Hallazgos --}}
    <div class="rounded-xl border border-gray-200 bg-white">
        <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold">Hallazgos</div>
        <div class="space-y-2 p-4">
            @forelse ($this->findings as $finding)
                <div class="flex items-center gap-2 text-sm">
                    <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $finding['color'] }}"></span>
                    <span class="font-mono font-semibold">{{ $finding['fdi_code'] }}</span>
                    <span>{{ $finding['label'] }} ({{ $finding['face_label'] }})</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">Sin hallazgos registrados todavía.</p>
            @endforelse
        </div>
    </div>

    {{-- Historial de tratamientos --}}
    <div class="rounded-xl border border-gray-200 bg-white">
        <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold">Historial de tratamientos</div>
        <div class="space-y-2 p-4">
            @forelse ($this->history as $entry)
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="font-mono font-semibold">{{ $entry->fdi_code }}</span>
                    <span class="text-xs text-gray-500">{{ $this->faceLabel($entry->face) }}</span>
                    @if ($entry->condition)
                        <span class="inline-flex items-center gap-1.5 text-xs">
                            <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $entry->condition->color }}"></span>
                            {{ $entry->condition->label }}
                        </span>
                    @endif
                    @if ($entry->observation)
                        <span class="text-xs italic text-gray-600">“{{ $entry->observation }}”</span>
                    @endif
                    @if ($entry->pending ?? false)
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">Sin guardar</span>
                    @endif
                    <span class="ml-auto text-xs text-gray-400">{{ $entry->registered_at->format('d/m/Y H:i') }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">Sin historial registrado todavía.</p>
            @endforelse
        </div>
    </div>

    @if ($saveMessage)
        <div wire:transition class="text-xs font-semibold text-success-600">{{ $saveMessage }}</div>
    @elseif (count($pending) > 0)
        <div wire:transition class="flex items-center gap-3 text-xs font-semibold text-amber-600">
            <span>Hay {{ count($pending) }} cambio(s) sin guardar</span>
            <button type="button" wire:click="undoPending"
                class="rounded-lg border border-amber-300 px-2 py-0.5 text-[10px] font-semibold text-amber-700 hover:bg-amber-50">
                Deshacer
            </button>
            <button type="button" wire:click="discardPending"
                class="rounded-lg border border-red-300 px-2 py-0.5 text-[10px] font-semibold text-red-700 hover:bg-red-50">
                Descartar todo
            </button>
        </div>
    @endif

    {{-- Modal de observación al re-tratar una zona ya tratada --}}
    @if ($showObservationModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
                <h3 class="text-sm font-semibold">Observación del re-tratamiento</h3>
                <p class="mt-1 text-xs text-gray-500">
                    Pieza {{ $observationFdiCode }} · {{ $this->observationZoneLabel() }}
                </p>
                <textarea
                    wire:model="observationText"
                    rows="3"
                    class="mt-3 w-full rounded-lg border border-gray-300 p-2 text-sm"
                    placeholder="Describa la observación del re-tratamiento..."
                ></textarea>
                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        wire:click="cancelObservation"
                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                    >Cancelar</button>
                    <button
                        type="button"
                        wire:click="submitObservation"
                        class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500"
                    >Guardar observación</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de nota por diente --}}
    @if ($showToothNoteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
                <h3 class="text-sm font-semibold">Nota del diente</h3>
                <p class="mt-1 text-xs text-gray-500">Pieza {{ $toothNoteFdiCode }}</p>
                <textarea
                    wire:model="toothNoteText"
                    rows="4"
                    class="mt-3 w-full rounded-lg border border-gray-300 p-2 text-sm"
                    placeholder="Escriba una nota para esta pieza dental..."
                ></textarea>
                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        wire:click="cancelToothNote"
                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                    >Cancelar</button>
                    <button
                        type="button"
                        wire:click="saveToothNote"
                        class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500"
                    >Guardar nota</button>
                </div>
            </div>
        </div>
    @endif
</div>
