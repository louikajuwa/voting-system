<?php

namespace App\Exports;

use App\Models\Election;
use App\Support\Csv;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/** Raw votes for an election, optionally filtered to a created_at date range. */
class VotesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private Election $election,
        private ?string $from = null,
        private ?string $to = null,
    ) {}

    public function collection()
    {
        $q = $this->election->votes()->with('candidate:id,name')->latest();
        if ($this->from) {
            $q->whereDate('created_at', '>=', $this->from);
        }
        if ($this->to) {
            $q->whereDate('created_at', '<=', $this->to);
        }

        return $q->get();
    }

    public function headings(): array
    {
        return ['Email', 'Candidate', 'IP', 'Voted at'];
    }

    public function map($vote): array
    {
        return Csv::row([
            $vote->voter_email,
            $vote->candidate?->name ?? '—',
            $vote->ip,
            $vote->created_at?->toDateTimeString(),
        ]);
    }
}
