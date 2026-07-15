<?php

namespace App\Console\Commands;

use App\Models\Act;
use App\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Одноразовая миграция данных: старые ticket_materials -> acts/act_materials.
// Тип акта для этих записей неизвестен (поля "тип акта" тогда не существовало) —
// оставляем type=null, статус сразу completed (в архиве), т.к. заявки уже давно закрыты.
class BackfillActsFromTicketMaterials extends Command
{
    protected $signature = 'acts:backfill-from-ticket-materials {--dry-run : Только показать, что будет сделано, ничего не менять}';
    protected $description = 'Переносит существующие ticket_materials в новую структуру acts/act_materials';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $tickets = Ticket::has('materials')->with('materials')->get();
        $this->info('Найдено заявок с материалами: ' . $tickets->count());

        $created = 0;
        foreach ($tickets as $ticket) {
            if ($ticket->act) {
                $this->warn("Заявка #{$ticket->number} (id={$ticket->id}) уже имеет акт, пропускаю");
                continue;
            }

            $number = ($ticket->act_number && $ticket->act_number !== 'б/а')
                ? $ticket->act_number
                : 'legacy-' . $ticket->id;

            $baseNumber = $number;
            $suffix = 1;
            while (Act::where('number', $number)->exists()) {
                $number = $baseNumber . '-' . (++$suffix);
            }

            $this->line("Заявка #{$ticket->number} (id={$ticket->id}): создаю акт '{$number}', материалов: {$ticket->materials->count()}");

            if ($dryRun) {
                $created++;
                continue;
            }

            DB::transaction(function () use ($ticket, $number, &$created) {
                // Сохраняем оригинальные даты создания — иначе отчёты по периодам
                // (расход за месяц/квартал, помесячная таблица, прогноз) увидят все
                // перенесённые записи как будто они возникли сегодня, а не когда
                // материал реально был списан.
                $act = new Act([
                    'ticket_id'                    => $ticket->id,
                    'number'                       => $number,
                    'type'                         => null,
                    'status'                       => 'completed',
                    'created_by'                   => $ticket->closed_by,
                    'subscriber_dept_completed_by' => $ticket->closed_by,
                    'subscriber_dept_completed_at' => $ticket->closed_at,
                ]);
                $act->created_at = $ticket->closed_at ?? $ticket->updated_at;
                $act->updated_at = $ticket->closed_at ?? $ticket->updated_at;
                $act->save();

                foreach ($ticket->materials as $tm) {
                    $am = new \App\Models\ActMaterial([
                        'act_id'        => $act->id,
                        'material_id'   => $tm->material_id,
                        'material_name' => $tm->material_name,
                        'material_code' => $tm->material_code,
                        'material_unit' => $tm->material_unit,
                        'price_at_time' => $tm->price_at_time,
                        'quantity'      => $tm->quantity,
                        'created_by'    => $tm->created_by,
                    ]);
                    $am->created_at = $tm->created_at;
                    $am->updated_at = $tm->updated_at;
                    $am->save();
                }

                $created++;
            });
        }

        $this->info(($dryRun ? '[dry-run] Будет создано' : 'Создано') . " актов: {$created}");
        return self::SUCCESS;
    }
}
