<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Просмотрщик md-файлов из папки docs/ в корне репозитория (документация
 * разработчика — архитектура, HOWTO, API и т.д.). Доступно только роли
 * admin -- это техническая документация не для операторов/бригадиров.
 * Список файлов строится через glob('docs/*.md') и используется как
 * белый список: запрошенное имя файла принимается, только если оно
 * буквально совпадает с одним из найденных на диске basename -- так
 * запрос вида ?file=../../../etc/passwd не пройдёт (не совпадёт ни с
 * одним элементом списка), без необходимости отдельно валидировать путь.
 */
class DocsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $docsPath = base_path('docs');

        $files = collect(glob($docsPath . '/*.md'))
            ->map(function (string $path) {
                $name = basename($path);
                $firstLine = trim(strtok(file_get_contents($path), "\n"));
                $title = trim(preg_replace('/^#+\s*/', '', $firstLine)) ?: $name;
                return ['name' => $name, 'title' => $title];
            })
            ->sortBy('name')
            ->values();

        $selected = $request->string('file')->toString() ?: null;
        $content = null;

        if ($selected && $files->contains('name', $selected)) {
            $content = file_get_contents($docsPath . '/' . $selected);
        }

        return Inertia::render('Docs/Index', [
            'files'    => $files,
            'selected' => $selected,
            'content'  => $content,
        ]);
    }
}
