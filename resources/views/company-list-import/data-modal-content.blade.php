<div class="grid gap-3">
    <p class="text-sm text-slate-600">{{ count($data['rows']) }} ligne(s) importée(s) — {{ $data['title'] }}</p>
    <div class="max-h-[70vh] overflow-auto rounded-xl border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
            <thead class="sticky top-0 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                <tr>
                    <th scope="col" class="whitespace-nowrap px-4 py-3">#</th>
                    @foreach ($data['columns'] as $label)
                        <th scope="col" class="whitespace-nowrap px-4 py-3">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @foreach ($data['rows'] as $row)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-500">{{ $loop->iteration }}</td>
                        @foreach ($data['columns'] as $field => $label)
                            <td class="max-w-80 break-words px-4 py-3 text-slate-800">{{ $row[$field] !== '' ? $row[$field] : '—' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
