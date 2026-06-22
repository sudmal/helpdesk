<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AggregateCallStats extends Command
{
    protected $signature = 'helpdesk:aggregate-call-stats
        {date? : Дата Y-m-d, по умолчанию вчера}
        {--backfill : Заполнить все доступные дни}';
    protected $description = 'Агрегирует почасовую статистику звонков в call_daily_stats';

    public function handle(): int
    {
        if ($this->option('backfill')) {
            return $this->backfill();
        }
        $date = $this->argument('date')
            ? Carbon::parse($this->argument('date'))->toDateString()
            : Carbon::yesterday()->toDateString();
        $this->aggregate($date);
        $this->info("Aggregated: $date");
        return 0;
    }

    private function aggregate(string $date): void
    {
        $callRows = DB::table('calls')
            ->selectRaw("
                HOUR(called_at) as hour,
                COUNT(*) as total_calls,
                SUM(queue_status = 'answered') as answered,
                SUM(queue_status = 'missed')   as missed,
                ROUND(AVG(CASE WHEN wait_seconds IS NOT NULL THEN wait_seconds END), 1) as avg_wait_sec,
                MAX(wait_seconds) as max_wait_sec
            ")
            ->whereDate('called_at', $date)
            ->whereNotNull('queue_status')
            ->groupByRaw('HOUR(called_at)')
            ->get()
            ->keyBy('hour');

        $queueRows = DB::table('queue_stats')
            ->selectRaw("
                HOUR(recorded_at) as hour,
                MAX(waiting)              as max_queue_depth,
                ROUND(AVG(waiting), 1)    as avg_queue_depth,
                ROUND(AVG(active_members), 1) as avg_operators
            ")
            ->whereDate('recorded_at', $date)
            ->groupByRaw('HOUR(recorded_at)')
            ->get()
            ->keyBy('hour');

        $hours = $callRows->keys()->merge($queueRows->keys())->unique()->sort();
        if ($hours->isEmpty()) {
            DB::table('call_daily_stats')->where('stat_date', $date)->delete();
            return;
        }

        $rows = [];
        foreach ($hours as $h) {
            $c = $callRows->get($h);
            $q = $queueRows->get($h);
            $rows[] = [
                'stat_date'       => $date,
                'hour'            => (int)$h,
                'total_calls'     => (int)($c?->total_calls  ?? 0),
                'answered'        => (int)($c?->answered     ?? 0),
                'missed'          => (int)($c?->missed       ?? 0),
                'avg_wait_sec'    => $c?->avg_wait_sec,
                'max_wait_sec'    => $c?->max_wait_sec,
                'max_queue_depth' => $q?->max_queue_depth,
                'avg_queue_depth' => $q?->avg_queue_depth,
                'avg_operators'   => $q?->avg_operators,
            ];
        }

        DB::table('call_daily_stats')->where('stat_date', $date)->delete();
        DB::table('call_daily_stats')->insert($rows);
    }

    private function backfill(): int
    {
        $minDate = DB::table('calls')->whereNotNull('queue_status')->min('called_at');
        if (!$minDate) { $this->warn('Нет звонков с queue_status'); return 0; }
        $current = Carbon::parse($minDate)->startOfDay();
        $end     = Carbon::yesterday();
        $count   = 0;
        while ($current->lte($end)) {
            $date = $current->toDateString();
            $this->aggregate($date);
            $this->line("  $date");
            $current->addDay();
            $count++;
        }
        $this->info("Backfilled $count days");
        return 0;
    }
}
