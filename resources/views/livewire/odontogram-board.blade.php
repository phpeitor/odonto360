<div x-data="{ hover: null }" x-on:beforeunload.window="if ($wire.get('pending').length > 0) event.preventDefault()" x-on:open-pdf.window="window.location.href = event.detail.url" class="space-y-6">

    {{-- Toolbar: paciente, dentición, numeración, guardar --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold">{{ $patient->fullName() }}</h2>
            <p class="h-4 text-xs text-gray-500"
               x-text="hover ? ('Pieza ' + hover.code + ' · ' + hover.face) : ''"></p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="inline-flex overflow-hidden rounded-lg border border-gray-300">
                @foreach ([
                    'adult' => ['label' => 'Adulto · 32', 'blocked' => 'Paciente menor de 6 años: solo dentición infantil'],
                    'mixed' => ['label' => 'Mixta · 52', 'blocked' => $patient->isAdult() ? 'Paciente mayor de edad: solo dentición adulta' : 'Paciente menor de 6 años: solo dentición infantil'],
                    'child' => ['label' => 'Niño · 20', 'blocked' => 'Paciente mayor de edad: solo dentición adulta'],
                ] as $option => $meta)
                    @php($allowed = $patient->allowsDentition($option))
                    <span @unless ($allowed) x-tooltip="{ content: '{{ $meta['blocked'] }}', theme: $store.theme }" @endunless class="inline-flex">
                        <button type="button" wire:click="setDentition('{{ $option }}')"
                            @disabled(! $allowed)
                            @class([
                                'px-3 py-1.5 text-xs font-semibold transition-colors',
                                'border-l border-gray-300' => $option !== 'adult',
                                'bg-primary-600 text-white' => $dentition === $option,
                                'bg-white text-gray-700 hover:bg-gray-50' => $dentition !== $option && $allowed,
                                'cursor-not-allowed bg-gray-100 text-gray-400' => ! $allowed && $dentition !== $option,
                            ])>{{ $meta['label'] }}</button>
                    </span>
                @endforeach
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

    {{-- Fecha de examen, especificaciones (NTS 188, 5.14) y observaciones (5.15) --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[12rem_1fr_1fr]">
        <div>
            <label for="examinedAt" class="mb-1 block text-xs font-semibold text-gray-600">Fecha de examen</label>
            <input type="date" wire:model="examinedAt" id="examinedAt"
                class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm" />
        </div>
        <div>
            <label for="specifications" class="mb-1 block text-xs font-semibold text-gray-600">Especificaciones</label>
            <textarea wire:model="specifications" id="specifications" rows="2"
                class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm"
                placeholder="Hallazgos que no entran en los recuadros: color del metal de la corona, fluorosis, material..."></textarea>
        </div>
        <div>
            <label for="notes" class="mb-1 block text-xs font-semibold text-gray-600">Observaciones</label>
            <textarea wire:model="notes" id="notes" rows="2"
                class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm"
                placeholder="Hallazgos no contemplados en la nomenclatura del odontograma..."></textarea>
        </div>
    </div>

    {{-- Paleta de hallazgos (nomenclatura NTS N° 188-MINSA/DGIESP-2022) --}}
    <div class="space-y-3">
        @foreach ($this->conditionGroups as $category => $group)
            <div class="flex flex-wrap items-center gap-2">
                @unless ($category === 'sano')
                    <span class="w-full text-[11px] font-semibold uppercase tracking-wide text-gray-400 sm:w-48">
                        {{ \App\Models\ToothCondition::CATEGORY_LABELS[$category] ?? $category }}
                    </span>
                @endunless
                @foreach ($group as $condition)
                    @php($chipColor = $condition->code === \App\Models\ToothCondition::CODE_SANO ? '#6B7280' : $condition->color)
                    <button type="button"
                        wire:click="pickCondition('{{ $condition->code }}')"
                        class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-all"
                        style="border-color: {{ $chipColor }};
                               {{ $activeCondition === $condition->code ? 'background:'.$chipColor.'; color:#fff;' : 'color:'.$chipColor.';' }}">
                        {{ $condition->label }}
                    </button>
                @endforeach
            </div>
        @endforeach

        {{-- Sigla y estado con los que se registra la condición activa --}}
        @if ($this->activeConditionModel && $this->activeConditionModel->code !== \App\Models\ToothCondition::CODE_SANO)
            @php($active = $this->activeConditionModel)
            <div class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs">
                <span class="font-semibold text-gray-700">{{ $active->label }}</span>

                @if (count($active->abbreviationOptions()) > 0)
                    <div class="inline-flex flex-wrap items-center gap-1">
                        <span class="text-gray-500">Sigla:</span>
                        @foreach ($active->abbreviationOptions() as $abbreviation)
                            <button type="button" wire:click="pickAbbreviation('{{ $abbreviation }}')"
                                @class([
                                    'rounded border px-2 py-0.5 font-mono font-semibold',
                                    'border-gray-800 bg-gray-800 text-white' => $activeAbbreviation === $abbreviation,
                                    'border-gray-300 bg-white text-gray-700 hover:bg-gray-100' => $activeAbbreviation !== $abbreviation,
                                ])>{{ $abbreviation }}</button>
                        @endforeach
                    </div>
                @endif

                <div class="inline-flex items-center gap-1">
                    <span class="text-gray-500">Estado:</span>
                    @foreach ($active->states() as $state)
                        @php($stateColor = \App\Models\ToothCondition::colorForState($state))
                        <button type="button" wire:click="pickState('{{ $state }}')"
                            class="rounded-full border px-2 py-0.5 font-semibold"
                            style="border-color: {{ $stateColor }}; {{ $activeState === $state ? 'background:'.$stateColor.'; color:#fff;' : 'color:'.$stateColor.';' }}">
                            {{ \App\Models\ToothCondition::STATE_LABELS[$state] }}
                        </button>
                    @endforeach
                </div>

                <span class="ml-auto text-gray-500">
                    {{ $active->target === 'face' ? 'Clic en la cara afectada' : 'Clic en la pieza' }}
                </span>
            </div>
        @endif

        <p class="text-[11px] text-gray-500">
            <span class="font-semibold" style="color: {{ \App\Models\ToothCondition::COLOR_GOOD }}">Azul</span>: buen estado ·
            <span class="font-semibold" style="color: {{ \App\Models\ToothCondition::COLOR_BAD }}">Rojo</span>: mal estado, temporal o patológico
            — NTS N° 188-MINSA/DGIESP-2022
        </p>
    </div>

    {{-- Arcos superior e inferior (en dentición mixta: permanente + temporal por arcada) --}}
    @foreach (['upper', 'lower'] as $arch)
        @if ($arch === 'lower')
            <div class="flex items-center gap-3">
                <div class="h-px flex-1 bg-gray-200"></div>
                <span class="text-[10px] font-mono text-gray-400">LÍNEA MEDIA</span>
                <div class="h-px flex-1 bg-gray-200"></div>
            </div>
        @endif

        <div @class(['space-y-2', 'pb-1' => $arch === 'upper'])>
            @foreach ($this->archRows[$arch] as $row)
                <div class="flex justify-center overflow-x-auto px-2 pt-3">
                    <div class="flex gap-1 sm:gap-2">
                        @foreach ($row as $definition)
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
            @endforeach
        </div>
    @endforeach

    {{-- Hallazgos --}}
    <div class="rounded-xl border border-gray-200 bg-white">
        <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold">Hallazgos</div>
        <div class="space-y-2 p-4">
            @forelse ($this->findings as $finding)
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $finding['color'] }}"></span>
                    <span class="font-mono font-semibold">{{ $finding['fdi_code'] }}</span>
                    @if ($finding['abbreviation'])
                        <span class="font-mono text-xs font-bold" style="color: {{ $finding['color'] }}">{{ $finding['abbreviation'] }}</span>
                    @endif
                    <span>{{ $finding['label'] }} ({{ $finding['face_label'] }})</span>
                    <span class="text-xs" style="color: {{ $finding['color'] }}">{{ \App\Models\ToothCondition::STATE_LABELS[$finding['state']] ?? '' }}</span>
                    <button type="button"
                        wire:click="removeFinding({{ $finding['fdi_code'] }}, {{ $finding['face'] ? "'".$finding['face']."'" : 'null' }}, '{{ $finding['code'] }}')"
                        class="ml-auto rounded-lg border border-gray-300 px-2 py-0.5 text-[10px] font-semibold text-gray-600 hover:bg-gray-50">
                        Quitar
                    </button>
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
                @php($struck = $entry->voided_at ? 'text-gray-400 line-through' : '')
                <div @class(['flex flex-wrap items-center gap-2 text-sm', 'text-gray-400' => $entry->voided_at])>
                    <span class="font-mono font-semibold {{ $struck }}">{{ $entry->fdi_code }}</span>
                    <span class="text-xs text-gray-500 {{ $struck }}">{{ $this->faceLabel($entry->face) }}</span>
                    @if ($entry->condition)
                        <span class="inline-flex items-center gap-1.5 text-xs {{ $struck }}">
                            <span class="h-2.5 w-2.5 rounded-full" style="background: {{ \App\Models\ToothCondition::colorForState($entry->state ?? $entry->condition->defaultState()) }}"></span>
                            @if ($entry->abbreviation ?? null)
                                <span class="font-mono font-bold">{{ $entry->abbreviation }}</span>
                            @endif
                            {{ $entry->condition->label }}
                        </span>
                    @endif
                    @if ($entry->observation)
                        <span @class(['text-xs italic', 'text-gray-600' => ! $entry->voided_at, 'text-gray-400 line-through' => $entry->voided_at])>“{{ $entry->observation }}”</span>
                    @endif
                    @if (($entry->action ?? null) === 'clear')
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600">Borrado</span>
                    @endif
                    @if ($entry->voided_at)
                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-700">
                            Anulado{{ $entry->voidedBy?->name ? ' por '.$entry->voidedBy->name : '' }}
                        </span>
                    @endif
                    @if ($entry->pending ?? false)
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">Sin guardar</span>
                    @endif
                    <span class="ml-auto text-xs text-gray-400 {{ $struck }}">
                        @if ($entry->user?->name)
                            {{ $entry->user->name }} ·
                        @endif
                        {{ $entry->registered_at->format('d/m/Y H:i') }}
                    </span>
                </div>
            @empty
                <p class="text-sm text-gray-500">Sin historial registrado todavía.</p>
            @endforelse
        </div>
    </div>

    @if (count($pending) > 0)
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
