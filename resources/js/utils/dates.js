import dayjs from 'dayjs'
import 'dayjs/locale/ru'
dayjs.locale('ru')

// 06 мая 2026, 13:00
export function formatDate(d) {
  if (!d) return '—'
  return dayjs(d).format('DD MMM YYYY, HH:mm')
}

// 06 мая, 13:00 (без года если текущий)
export function formatDateShort(d) {
  if (!d) return '—'
  const dt = dayjs(d)
  const fmt = dt.year() === dayjs().year() ? 'DD MMM, HH:mm' : 'DD MMM YY, HH:mm'
  return dt.format(fmt)
}

// Только время
export function formatTime(d) {
  return d ? dayjs(d).format('HH:mm') : '—'
}

// Только дата
export function formatDay(d) {
  return d ? dayjs(d).format('DD.MM.YYYY') : '—'
}

// Полная дата с месяцем словом
export function formatDateTime(d) {
  if (!d) return '—'
  return dayjs(d).format('DD MMM YYYY HH:mm')
}
