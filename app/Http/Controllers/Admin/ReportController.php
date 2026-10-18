<?php

namespace App\Http\Controllers\Admin;

use App\Exports\VotesExport;
use App\Http\Controllers\Concerns\AuthorizesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Support\Csv;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use AuthorizesOrganization;

    /** Stream an election-results CSV — native fputcsv, no export package. */
    public function csv(Election $election): StreamedResponse
    {
        $this->authorizeOrg($election);

        $election->load('candidates');
        $tallies = $election->tallies();
        $total = array_sum($tallies);

        return response()->streamDownload(function () use ($election, $tallies, $total) {
            $out = fopen('php://output', 'w');
            fputcsv($out, Csv::row(['Election', $election->title]));
            fputcsv($out, ['Status', $election->status]);
            fputcsv($out, ['Total votes', $total]);
            fputcsv($out, []);
            fputcsv($out, ['Candidate', 'Party', 'Votes', 'Percent']);

            foreach ($election->candidates as $c) {
                $votes = $tallies[$c->id] ?? 0;
                $pct = $total ? round($votes / $total * 100, 1) : 0;
                fputcsv($out, Csv::row([$c->name, $c->party, $votes, $pct.'%']));
            }
            fclose($out);
        }, 'ballotbox-'.$election->slug.'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Raw individual votes as CSV, optionally filtered by ?from=&to= date range. */
    public function votesCsv(Election $election): StreamedResponse
    {
        $this->authorizeOrg($election);

        [$from, $to] = [request('from'), request('to')];
        $query = $election->votes()->with('candidate:id,name')->latest();
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Email', 'Candidate', 'IP', 'Voted at']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $v) {
                    fputcsv($out, Csv::row([$v->voter_email, $v->candidate?->name, $v->ip, $v->created_at?->toDateTimeString()]));
                }
            });
            fclose($out);
        }, 'ballotbox-'.$election->slug.'-votes.csv', ['Content-Type' => 'text/csv']);
    }

    /** Raw individual votes as Excel (.xlsx), optionally filtered by ?from=&to=. */
    public function votesExcel(Election $election): BinaryFileResponse
    {
        $this->authorizeOrg($election);

        return Excel::download(
            new VotesExport($election, request('from'), request('to')),
            'ballotbox-'.$election->slug.'-votes.xlsx',
        );
    }
}
