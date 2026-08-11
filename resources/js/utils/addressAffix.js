// Курируемые списки типов/префиксов для форм создания/переименования адресов.
// НЕ путать с широким списком в Address::normalizeStreet() (backend) и с
// STREET_PREFIXES в Addresses/Index.vue (A-Z группировка улиц) — те служат
// для распознавания уже существующих исторических вариантов написания и
// умышленно шире. Эти списки — курируемый набор того, что вводить дальше.
// При добавлении нового варианта сюда — синхронизировать также с
// Address::normalizeStreet() и STREET_PREFIXES, иначе новые записи будут
// неправильно группироваться по буквам на уровне улиц.

export const EMPTY_PREFIX = '' // сентинел "без префикса" — не совпадает ни с одним реальным префиксом

export const CITY_PREFIXES = [
  { value: EMPTY_PREFIX, label: '(без типа)' },
  { value: 'Город', label: 'Город' },
  { value: 'пос.', label: 'пос.' },
  { value: 'с.', label: 'с.' },
  { value: 'пгт', label: 'пгт' },
  { value: 'х.', label: 'х.' },
  { value: 'ст-ца', label: 'ст-ца' },
]

export const STREET_PREFIXES_SELECT = [
  { value: EMPTY_PREFIX, label: '(без типа)' },
  { value: 'ул.', label: 'ул.' },
  { value: 'пр.', label: 'пр.' },
  { value: 'пер.', label: 'пер.' },
  { value: 'кв-л', label: 'кв-л' },
  { value: 'б-р', label: 'б-р' },
  { value: 'ш.', label: 'ш.' },
]

export const BUILDING_PREFIXES = [
  { value: EMPTY_PREFIX, label: '(без типа)' },
  { value: 'стр.', label: 'стр.' },
  { value: 'корп.', label: 'корп.' },
  { value: 'лит.', label: 'лит.' },
  { value: 'склад', label: 'склад' },
]

export const APARTMENT_PREFIXES = [
  { value: EMPTY_PREFIX, label: '(без типа)' },
  { value: 'кв.', label: 'кв.' },
  { value: 'офис', label: 'офис' },
  { value: 'помещение', label: 'помещение' },
  { value: 'кабинет', label: 'кабинет' },
]

export const PREFIX_LISTS = {
  city: CITY_PREFIXES,
  street: STREET_PREFIXES_SELECT,
  building: BUILDING_PREFIXES,
  apartment: APARTMENT_PREFIXES,
}

/**
 * Лучшее совпадение: ищет префикс из prefixList в начале value (без учёта
 * регистра, по границе слова — после префикса должен идти пробел или конец
 * строки). Проверяет от длинных префиксов к коротким, чтобы более длинный
 * вариант не терялся за более коротким совпадающим началом. Если совпадения
 * нет — безопасный фолбэк: пустой префикс + вся строка целиком в название
 * (ничего не портит, оператор может вручную поправить тип).
 */
export function splitPrefix(value, prefixList) {
  const s = (value ?? '').toString().trim()
  if (!s) return { type: EMPTY_PREFIX, name: '' }

  const candidates = prefixList
    .map(p => p.value)
    .filter(v => v !== EMPTY_PREFIX)
    .sort((a, b) => b.length - a.length)

  for (const prefix of candidates) {
    const escaped = prefix.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    const re = new RegExp('^' + escaped + '(?=\\s|$)', 'i')
    if (re.test(s)) {
      const name = s.slice(prefix.length).replace(/^\s+/, '')
      return { type: prefix, name }
    }
  }
  return { type: EMPTY_PREFIX, name: s }
}

/** Обратная операция к splitPrefix — используется при сборке значения перед submit. */
export function composeValue(type, name) {
  const n = (name ?? '').toString().trim()
  const t = (type ?? '').toString().trim()
  return t ? `${t} ${n}`.trim() : n
}
