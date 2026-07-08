<?php

namespace App\Console\Commands;

use App\Models\ShiftDefinition;
use App\Services\ShiftReportService;
use Illuminate\Console\Command;

class GenerateShiftReports extends Command
{
    protected $signature = 'helpdesk:generate-shift-reports
        {--definition= : ID смены -- для точечного пересчёта}
        {--date= : Дата Y-m-d -- вместе с --definition принудительно пересчитать одну смену}';
    protected $description = 'Генерирует отчёты по завершившимся сменам (DND/разговор/звонки по каждому добавочному)';

    public function handle(ShiftReportService $service): int
    {
        if ($this->option('definition') && $this->option('date')) {
            $def = ShiftDefinition::findOrFail($this->option('definition'));
            $report = $service->regenerate($def, $this->option('date'));
            $this->info("Пересчитано: {$def->name} за {$this->option('date')} (id={$report->id})");
            return 0;
        }

        $count = $service->generateDue();
        if ($count > 0) {
            $this->info("Сгенерировано отчётов: {$count}");
        }
        return 0;
    }
}
