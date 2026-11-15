<div class="space-y-4 text-sm">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Item Sumber ({{ $match->sourceDepartment?->name ?? '-' }})</div>
            <div class="mt-2 space-y-1">
                <div><span class="text-gray-500">Kode Rekening:</span> <strong>{{ $match->sourceAdjustment?->account?->code ?? '—' }}</strong></div>
                <div><span class="text-gray-500">Uraian:</span> {{ $match->sourceAdjustment?->description ?? '—' }}</div>
                <div><span class="text-gray-500">Volume / Satuan:</span> {{ number_format((float) ($match->sourceAdjustment?->proposed_volume ?? 0), 2, ',', '.') }} {{ $match->sourceAdjustment?->unit_symbol ?? '' }}</div>
                <div><span class="text-gray-500">Nilai Usulan:</span> Rp {{ number_format((float) ($match->sourceAdjustment?->proposed_amount ?? 0), 0, ',', '.') }}</div>
                <div><span class="text-gray-500">Nilai Disetujui:</span> Rp {{ number_format((float) ($match->sourceAdjustment?->approved_amount ?? 0), 0, ',', '.') }}</div>
                <div><span class="text-gray-500">Ref Sumber:</span> {{ $match->sourceAdjustment?->source_ref_id ?? '—' }}</div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Item Lawan ({{ $match->targetDepartment?->name ?? 'Belum Terpetakan' }})</div>
            @if($match->counterpartAdjustment)
                <div class="mt-2 space-y-1">
                    <div><span class="text-gray-500">Kode Rekening:</span> <strong>{{ $match->counterpartAdjustment->account?->code ?? '—' }}</strong></div>
                    <div><span class="text-gray-500">Uraian:</span> {{ $match->counterpartAdjustment->description }}</div>
                    <div><span class="text-gray-500">Volume / Satuan:</span> {{ number_format((float) $match->counterpartAdjustment->proposed_volume, 2, ',', '.') }} {{ $match->counterpartAdjustment->unit_symbol }}</div>
                    <div><span class="text-gray-500">Nilai Usulan:</span> Rp {{ number_format((float) $match->counterpartAdjustment->proposed_amount, 0, ',', '.') }}</div>
                    <div><span class="text-gray-500">Nilai Disetujui:</span> Rp {{ number_format((float) $match->counterpartAdjustment->approved_amount, 0, ',', '.') }}</div>
                    <div><span class="text-gray-500">Ref Lawan:</span> {{ $match->counterpartAdjustment->source_ref_id ?? '—' }}</div>
                </div>
            @else
                <div class="mt-2 text-gray-500 dark:text-gray-400">Belum ada pasangan item lawan yang terhubung.</div>
            @endif
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
        <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Penjelasan Pencocokan</div>
        <div class="mt-2 space-y-1">
            <div><span class="text-gray-500">Status:</span> <strong>{{ \App\Models\RkaAdjustmentMatch::statusLabels()[$match->reconciliation_status] ?? $match->reconciliation_status }}</strong> ({{ $match->matching_level }})</div>
            <div><span class="text-gray-500">Aturan:</span> {{ $match->matching_rule ?? '—' }}</div>
            <div><span class="text-gray-500">Alasan:</span> {{ $match->match_details['reason'] ?? '—' }}</div>
            @if(!empty($match->match_details['differing_attributes']))
                <div class="text-danger-600 dark:text-danger-400">
                    <span class="font-semibold">Atribut Berbeda:</span> {{ implode(', ', $match->match_details['differing_attributes']) }}
                </div>
            @endif
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
        <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Histori Rekonsiliasi & Koreksi</div>
        <div class="mt-2 space-y-2">
            @forelse($match->histories as $history)
                <div class="rounded border border-gray-100 bg-gray-50 p-2.5 text-xs dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-semibold">{{ $history->action }} ({{ $history->source }})</span>
                        <span class="text-gray-500">{{ $history->created_at?->format('d/m/Y H:i:s') }}</span>
                    </div>
                    <div class="mt-1 text-gray-700 dark:text-gray-300">
                        Aktor: {{ $history->actor?->name ?? 'Sistem' }} — {{ $history->reason ?? '-' }}
                    </div>
                </div>
            @empty
                <div class="text-gray-500">Belum ada histori tercatat.</div>
            @endforelse
        </div>
    </div>
</div>
