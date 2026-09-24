<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Вопросы опросного листа (содержимое и количество настраиваются во вкладке
        // "Настройки опроса" раздела Акты). Не удаляются физически, если по ним
        // уже есть ответы — только деактивируются.
        Schema::create('survey_questions', function (Blueprint $t) {
            $t->id();
            $t->string('text', 500);
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // К каким актам нужен опросный лист: по типу заявки (tickets.type_id) и/или
        // по виду заявки на подключение (connection_requests.kind).
        Schema::create('survey_targets', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('ticket_type_id')->nullable()->unique();
            $t->string('connection_kind', 20)->nullable()->unique();
            $t->timestamps();
            $t->foreign('ticket_type_id')->references('id')->on('ticket_types')->cascadeOnDelete();
        });

        // Опрос по акту. Строка появляется при первой попытке дозвона/сохранении;
        // пока строки нет — акт считается "ожидает опроса" (по сроку и настройкам).
        Schema::create('act_surveys', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('act_id')->unique();
            $t->enum('status', ['pending', 'completed', 'declined'])->default('pending');
            $t->unsignedInteger('attempts')->default(0);
            $t->dateTime('last_attempt_at')->nullable();
            $t->string('last_attempt_note', 500)->nullable();
            $t->text('overall_comment')->nullable();
            $t->unsignedBigInteger('completed_by')->nullable();
            $t->dateTime('completed_at')->nullable();
            $t->timestamps();
            $t->foreign('act_id')->references('id')->on('acts')->cascadeOnDelete();
            $t->foreign('completed_by')->references('id')->on('users')->nullOnDelete();
        });

        // Ответы. Текст вопроса сохраняется снимком — вопросы можно править/удалять,
        // а история ответов должна остаться читаемой.
        Schema::create('act_survey_answers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('survey_id');
            $t->unsignedBigInteger('question_id')->nullable();
            $t->string('question_text', 500);
            $t->unsignedTinyInteger('rating')->nullable();
            $t->text('comment')->nullable();
            $t->timestamps();
            $t->foreign('survey_id')->references('id')->on('act_surveys')->cascadeOnDelete();
            $t->foreign('question_id')->references('id')->on('survey_questions')->nullOnDelete();
        });

        // Стартовые вопросы (редактируются в настройках)
        $now = now();
        foreach ([
            'Довольны ли вы качеством и скоростью интернета?',
            'Как вы оцениваете работу бригады монтажников?',
            'Насколько аккуратно выполнены работы (прокладка кабеля, уборка после монтажа)?',
            'Как вы оцениваете вежливость и общение специалистов?',
        ] as $i => $text) {
            DB::table('survey_questions')->insert([
                'text' => $text, 'sort_order' => $i + 1, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // По умолчанию опрос нужен для актов типа "Подключение" — и заявок с типом
        // "Подключение", и заявок на подключение (connection_requests, kind=connection).
        $connectionTypeId = DB::table('ticket_types')->where('name', 'Подключение')->value('id');
        if ($connectionTypeId) {
            DB::table('survey_targets')->insert(['ticket_type_id' => $connectionTypeId, 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('survey_targets')->insert(['connection_kind' => 'connection', 'created_at' => $now, 'updated_at' => $now]);

        // Опрашиваем только акты, созданные с момента запуска функции (старых
        // абонентов не обзваниваем), звонок — через 3 дня после акта.
        foreach ([
            ['survey.delay_days', '3', 'integer', 'Опросы: через сколько дней после акта звонить абоненту'],
            ['survey.start_date', $now->toDateString(), 'string', 'Опросы: акты, созданные раньше этой даты, не опрашиваются'],
        ] as [$key, $value, $type, $descr]) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'type' => $type, 'description' => $descr, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        // Права: проводить опросы — оператор, Абонотдел, начальник ТП; настраивать — начальник ТП
        // (admin покрыт "*").
        $add = [
            'operator'        => ['surveys.conduct'],
            'subscriber_dept' => ['surveys.conduct'],
            'head_support'    => ['surveys.conduct', 'surveys.manage'],
        ];
        foreach ($add as $slug => $perms) {
            $role = DB::table('roles')->where('slug', $slug)->first();
            if (!$role) continue;
            $current = json_decode($role->permissions, true) ?: [];
            DB::table('roles')->where('slug', $slug)->update([
                'permissions' => json_encode(array_values(array_unique(array_merge($current, $perms)))),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('act_survey_answers');
        Schema::dropIfExists('act_surveys');
        Schema::dropIfExists('survey_targets');
        Schema::dropIfExists('survey_questions');
        DB::table('system_settings')->whereIn('key', ['survey.delay_days', 'survey.start_date'])->delete();
    }
};
