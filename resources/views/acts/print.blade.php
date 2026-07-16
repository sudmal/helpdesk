<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 18mm 15mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
    h1 { text-align: center; font-size: 16px; margin: 0 0 8px; }
    .number-line { text-align: center; font-size: 13px; font-weight: bold; margin: 0 0 16px; padding-bottom: 8px; border-bottom: 1px solid #444; }
    table.meta { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    table.meta td { padding: 3px 0; vertical-align: top; }
    table.meta td.label { width: 140px; color: #333; font-weight: bold; }
    table.materials { width: 100%; border-collapse: collapse; margin: 14px 0; }
    table.materials th, table.materials td { border: 1px solid #444; padding: 5px 6px; font-size: 11px; }
    table.materials th { background: #eee; text-align: center; }
    table.materials td.num { text-align: center; }
    table.materials td.right { text-align: right; }
    .note { font-style: italic; font-size: 11px; margin: 10px 0; }
    .total { text-align: right; font-weight: bold; font-size: 13px; margin: 6px 0 18px; }
    .signline { margin: 18px 0 4px; }
    .marks { margin-top: 22px; font-size: 11px; }
    .marks table { width: 100%; border-collapse: collapse; }
    .marks td { width: 25%; vertical-align: top; padding-right: 10px; }
    .marks .m-label { font-weight: bold; margin-bottom: 14px; }
    .marks .m-value { border-top: 1px solid #444; padding-top: 3px; font-size: 10px; color: #333; }
</style>
</head>
<body>

<h1>АКТ ВЫПОЛНЕННЫХ РАБОТ</h1>
<div class="number-line">№ {{ $act->number }} от {{ $createdAt }}</div>

<table class="meta">
    <tr>
        <td class="label">ФИО заказчика</td>
        <td colspan="3">{{ $customerName ?: '—' }}</td>
    </tr>
    <tr>
        <td class="label">Адрес</td>
        <td colspan="3">{{ $address ?: '—' }}</td>
    </tr>
</table>

<table class="materials">
    <thead>
        <tr>
            <th style="width:24px">№ п/п</th>
            <th style="width:44px">Код</th>
            <th>Наименование</th>
            <th style="width:48px">Ед. изм.</th>
            <th style="width:60px">Цена, руб.</th>
            <th style="width:48px">Кол-во</th>
            <th style="width:70px">Сумма, руб.</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($act->materials as $i => $m)
        <tr>
            <td class="num">{{ $i + 1 }}</td>
            <td class="num">{{ $m->material_code ?: '—' }}</td>
            <td>{{ $m->material_name }}</td>
            <td class="num">{{ $m->material_unit }}</td>
            <td class="right">{{ number_format($m->price_at_time, 2, ',', ' ') }}</td>
            <td class="num">{{ rtrim(rtrim(number_format($m->quantity, 3, ',', ' '), '0'), ',') }}</td>
            <td class="right">{{ number_format($m->price_at_time * $m->quantity, 2, ',', ' ') }}</td>
        </tr>
    @empty
        <tr><td colspan="7" class="num">Материалы не использовались</td></tr>
    @endforelse
    </tbody>
</table>

<div class="note">Данная сумма будет списана с Вашего личного счёта в течение трёх суток!</div>
<div class="total">Итого: {{ number_format($total, 2, ',', ' ') }} руб.</div>

<div class="signline">ФИО исполнителя: {{ $installerName ?: '—' }} &nbsp;&nbsp;&nbsp;&nbsp; Подпись: ______________</div>
<div class="signline">Подпись заказчика: ______________</div>

<div class="marks">
    <table>
        <tr>
            <td>
                <div class="m-label">ОТЭ (бригадир)</div>
                <div class="m-value">{{ $markForeman }}</div>
            </td>
            <td>
                <div class="m-label">ПЭО</div>
                <div class="m-value">{{ $markPeo }}</div>
            </td>
            <td>
                <div class="m-label">ОЛ (логистика)</div>
                <div class="m-value">{{ $markLogistics }}</div>
            </td>
            <td>
                <div class="m-label">АО (абонотдел)</div>
                <div class="m-value">{{ $markSubscriberDept }}</div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
