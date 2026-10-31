// Rozbaľovacie štatistiky pod dlaždicami Teplota a Zrážky. Dáta: index.php?stats=1.
'use strict';

const UNITS = {
  day:   { tab: 'Dni',     count: 7 },
  week:  { tab: 'Týždne',  count: 8 },
  month: { tab: 'Mesiace', count: 12 },
  year:  { tab: 'Roky',    count: 5 },
};
const MONTHS = ['január', 'február', 'marec', 'apríl', 'máj', 'jún', 'júl', 'august', 'september', 'október', 'november', 'december'];
const WEEKDAYS = ['ne', 'po', 'ut', 'st', 'št', 'pi', 'so'];

let stats = null;
let loading = null;
const open = {}; // 'temp'/'rain' => prvok panelu

const toDate = (s) => new Date(s + 'T00:00:00Z');
const iso = (d) => d.toISOString().slice(0, 10);
const addDays = (d, n) => new Date(d.getTime() + n * 86400000);
const shortDate = (d) => d.getUTCDate() + '.' + (d.getUTCMonth() + 1) + '.';
const longDate = (s) => { const d = toDate(s); return shortDate(d) + d.getUTCFullYear(); };
const num = (x, dec = 1) => x.toFixed(dec).replace('.', ',').replace('-', '−');
const temp = (x, dec = 1) => (x === null ? '–' : num(x, dec) + ' °C');
const mm = (x) => (x === null ? '–' : num(x) + ' mm');
const monday = (d) => addDays(d, -((d.getUTCDay() + 6) % 7));

function keyOf(date, unit) {
  if (unit === 'day') return date;
  if (unit === 'week') return iso(monday(toDate(date)));
  return unit === 'month' ? date.slice(0, 7) : date.slice(0, 4);
}

/** Spojí denné súhrny do období: kľúč => {min, max, rain}. */
function aggregate(unit, filter) {
  const out = {};
  for (const [date, [min, max, rain]] of Object.entries(stats.days)) {
    if (filter && !filter(date)) continue;
    const k = keyOf(date, unit);
    const b = out[k] ??= { min: null, max: null, rain: null };
    if (min !== null) b.min = b.min === null ? min : Math.min(b.min, min);
    if (max !== null) b.max = b.max === null ? max : Math.max(b.max, max);
    if (rain !== null) b.rain = Math.round(((b.rain ?? 0) + rain) * 10) / 10;
  }
  return out;
}

/** Kľúče období na danej strane; strana 0 končí dneškom. */
function periods(unit, page) {
  const n = UNITS[unit].count;
  const t = toDate(stats.today);
  const keys = [];
  for (let i = n * (page + 1) - 1; i >= n * page; i--) {
    if (unit === 'day') keys.push(iso(addDays(t, -i)));
    if (unit === 'week') keys.push(iso(addDays(monday(t), -7 * i)));
    if (unit === 'month') keys.push(iso(new Date(Date.UTC(t.getUTCFullYear(), t.getUTCMonth() - i, 1))).slice(0, 7));
    if (unit === 'year') keys.push(String(t.getUTCFullYear() - i));
  }
  return keys;
}

function axisLabel(key, unit) {
  if (unit === 'day' || unit === 'week') return shortDate(toDate(key));
  if (unit === 'month') return MONTHS[+key.slice(5) - 1].slice(0, 3);
  return key;
}

function fullLabel(key, unit) {
  if (unit === 'day') { const d = toDate(key); return WEEKDAYS[d.getUTCDay()] + ' ' + longDate(key); }
  if (unit === 'week') return shortDate(toDate(key)) + '–' + longDate(iso(addDays(toDate(key), 6)));
  if (unit === 'month') return MONTHS[+key.slice(5) - 1] + ' ' + key.slice(0, 4);
  return key;
}

function rangeLabel(keys, unit) {
  const a = keys[0], b = keys[keys.length - 1];
  if (unit === 'day') return shortDate(toDate(a)) + '–' + longDate(b);
  if (unit === 'week') return shortDate(toDate(a)) + '–' + longDate(iso(addDays(toDate(b), 6)));
  if (unit === 'month') return MONTHS[+a.slice(5) - 1].slice(0, 3) + ' ' + a.slice(0, 4) + ' – ' + MONTHS[+b.slice(5) - 1].slice(0, 3) + ' ' + b.slice(0, 4);
  return a + '–' + b;
}

function niceStep(span, maxTicks) {
  for (const s of [0.5, 1, 2, 5, 10, 20, 50, 100, 200, 500, 1000]) {
    if (span / s <= maxTicks) return s;
  }
  return 2000;
}

/** Stĺpcový graf. Teplota: stĺpec od minima po maximum. Zrážky: stĺpec od nuly. */
function chart(kind, keys, data, width, unit) {
  const H = 220, L = 30, R = 4, T = 20, B = kind === 'temp' ? 36 : 22;
  const has = (b) => b && (kind === 'temp' ? b.min !== null : b.rain !== null);
  const shown = keys.map((k) => data[k]).filter(has);
  let lo, hi;
  if (kind === 'temp') {
    lo = shown.length ? Math.min(...shown.map((b) => b.min)) : 0;
    hi = shown.length ? Math.max(...shown.map((b) => b.max)) : 10;
    if (hi - lo < 4) { lo -= 2; hi += 2; }
  } else {
    lo = 0;
    hi = Math.max(1, ...shown.map((b) => b.rain));
  }
  const step = niceStep(hi - lo, 4);
  lo = Math.floor(lo / step) * step;
  hi = Math.ceil(hi / step) * step;
  const y = (v) => T + (hi - v) / (hi - lo) * (H - T - B);
  const slot = (width - L - R) / keys.length;
  const w = Math.min(26, slot * 0.55);
  const dec = keys.length > 8 ? 0 : 1;
  let s = `<svg class="chart" width="${width}" height="${H}" viewBox="0 0 ${width} ${H}" role="img" aria-label="${kind === 'temp' ? 'Minimálna a maximálna teplota' : 'Súhrn zrážok'}, ${rangeLabel(keys, unit)}">`;
  for (let v = lo; v <= hi + 1e-9; v += step) {
    s += `<line class="${Math.abs(v) < 1e-9 ? 'zero' : 'grid'}" x1="${L}" x2="${width - R}" y1="${y(v)}" y2="${y(v)}"/>`;
    s += `<text x="${L - 5}" y="${y(v) + 4}" text-anchor="end">${num(v, step < 1 ? 1 : 0)}</text>`;
  }
  keys.forEach((k, i) => {
    const b = data[k];
    const cx = L + slot * (i + 0.5), x = cx - w / 2;
    s += `<text x="${cx}" y="${H - 5}" text-anchor="middle">${axisLabel(k, unit)}</text>`;
    if (has(b)) {
      if (kind === 'temp') {
        const top = y(b.max), h = Math.max(4, y(b.min) - top);
        s += `<rect class="bar" x="${x}" y="${top}" width="${w}" height="${h}" rx="4"/>`;
        s += `<text class="val" x="${cx}" y="${top - 5}" text-anchor="middle">${num(b.max, dec)}</text>`;
        s += `<text class="val" x="${cx}" y="${top + h + 13}" text-anchor="middle">${num(b.min, dec)}</text>`;
      } else {
        const top = y(b.rain), h = y(0) - top;
        if (h > 0) {
          const r = Math.min(4, h, w / 2);
          s += `<path class="bar" d="M${x} ${y(0)}V${top + r}Q${x} ${top} ${x + r} ${top}H${x + w - r}Q${x + w} ${top} ${x + w} ${top + r}V${y(0)}Z"/>`;
        }
        s += `<text class="val" x="${cx}" y="${top - 5}" text-anchor="middle">${num(b.rain, dec)}</text>`;
      }
    }
    s += `<rect class="hit" data-key="${k}" x="${L + slot * i}" y="0" width="${slot}" height="${H}"/>`;
  });
  return s + '</svg>';
}

function readoutText(kind, key, unit, b) {
  const v = !b ? 'bez záznamov'
    : kind === 'temp' ? (b.min === null ? 'bez záznamov' : `min ${temp(b.min)}, max ${temp(b.max)}`)
    : (b.rain === null ? 'bez záznamov' : mm(b.rain));
  return fullLabel(key, unit) + ': ' + v;
}

function table(kind, keys, data, unit) {
  const head = kind === 'temp' ? '<th>Min</th><th>Max</th>' : '<th>Zrážky</th>';
  const rows = [...keys].reverse().map((k) => {
    const b = data[k] || { min: null, max: null, rain: null };
    const cells = kind === 'temp' ? `<td>${temp(b.min)}</td><td>${temp(b.max)}</td>` : `<td>${mm(b.rain)}</td>`;
    return `<tr><td>${fullLabel(k, unit)}</td>${cells}</tr>`;
  }).join('');
  return `<details><summary>Tabuľka</summary><table><tr><th>Obdobie</th>${head}</tr>${rows}</table></details>`;
}

const fact = (label, value) => `<div><dt>${label}</dt><dd>${value}</dd></div>`;

function extreme(index, better) {
  let best = null;
  for (const [date, row] of Object.entries(stats.days)) {
    if (row[index] !== null && (best === null || better(row[index], stats.days[best][index]))) best = date;
  }
  return best;
}

/** Tento mesiac do dnešného dňa oproti rovnakému obdobiu minulý rok. */
function comparison(kind) {
  const y = +stats.today.slice(0, 4), md = stats.today.slice(5, 7), dd = stats.today.slice(8);
  const span = (yr) => {
    const from = `${yr}-${md}-01`, to = `${yr}-${md}-${dd}`;
    return aggregate('year', (d) => d >= from && d <= to)[String(yr)];
  };
  const show = (b) => {
    if (!b) return 'bez záznamov';
    return kind === 'temp' ? (b.min === null ? 'bez záznamov' : `${temp(b.min)} až ${temp(b.max)}`) : mm(b.rain);
  };
  return `<h3>${MONTHS[+md - 1]}, 1.–${+dd}. deň</h3>` + fact(String(y), show(span(y))) + fact(String(y - 1), show(span(y - 1)));
}

function facts(kind) {
  const days = Object.entries(stats.days);
  const year = stats.today.slice(0, 4);
  let html = '<dl class="facts">';
  if (kind === 'temp') {
    const frost = days.filter(([, r]) => r[0] !== null && r[0] <= 0).map(([d]) => d).sort();
    const winterFrom = stats.today.slice(5, 7) >= '07' ? `${year}-07-01` : `${+year - 1}-07-01`;
    const spring = frost.filter((d) => d >= `${year}-01-01` && d <= `${year}-06-30`).pop();
    const autumn = frost.find((d) => d >= `${year}-07-01` && d <= `${year}-12-31`);
    html += '<h3>Mráz</h3>'
      + fact('Posledný jarný mráz', spring ? longDate(spring) : 'nezaznamenaný')
      + fact('Prvý jesenný mráz', autumn ? longDate(autumn) : 'nezaznamenaný')
      + fact('Mrazové dni od 1. 7.', frost.filter((d) => d >= winterFrom).length);
    const hot = extreme(1, (a, b) => a > b), cold = extreme(0, (a, b) => a < b);
    html += '<h3>Rekordy</h3>'
      + fact('Najteplejší deň', hot ? `${temp(stats.days[hot][1])}, ${longDate(hot)}` : '–')
      + fact('Najchladnejší deň', cold ? `${temp(stats.days[cold][0])}, ${longDate(cold)}` : '–');
  } else {
    const wet = extreme(2, (a, b) => a > b);
    const thisYear = days.filter(([d, r]) => d.startsWith(year) && r[2] !== null);
    html += '<h3>Rekordy</h3>'
      + fact('Najviac zrážok za deň', wet && stats.days[wet][2] > 0 ? `${mm(stats.days[wet][2])}, ${longDate(wet)}` : 'zatiaľ žiadne')
      + fact(`Dni bez dažďa v ${year}`, thisYear.filter(([, r]) => r[2] === 0).length)
      + fact(`Spolu v ${year}`, mm(Math.round(thisYear.reduce((s, [, r]) => s + r[2], 0) * 10) / 10));
  }
  return html + comparison(kind) + '</dl>';
}

function render(panel) {
  const { kind, unit, page } = panel.dataset.kind ? { kind: panel.dataset.kind, unit: panel.dataset.unit, page: +panel.dataset.page } : {};
  const keys = periods(unit, page);
  const data = aggregate(unit);
  const dates = Object.keys(stats.days).sort();
  const first = dates[0];
  const canOlder = first !== undefined && keyOf(first, unit) < keys[0];
  const width = Math.max(240, panel.clientWidth - 32);
  const tabs = Object.entries(UNITS).map(([u, d]) => `<button type="button" data-unit="${u}" aria-pressed="${u === unit}">${d.tab}</button>`).join('');
  panel.innerHTML = `<div class="tabs">${tabs}</div>
    <div class="nav"><button type="button" data-go="1" aria-label="Staršie"${canOlder ? '' : ' disabled'}>‹</button>
    <span>${rangeLabel(keys, unit)}</span>
    <button type="button" data-go="-1" aria-label="Novšie"${page > 0 ? '' : ' disabled'}>›</button></div>
    ${chart(kind, keys, data, width, unit)}
    <div class="readout" aria-live="polite">Ťukni na stĺpec pre detail.</div>
    ${table(kind, keys, data, unit)}
    ${facts(kind)}
    <p class="since">${first ? 'Záznamy od ' + longDate(first) + '.' : 'Zatiaľ bez záznamov.'}${kind === 'temp' ? ' Hodnoty sú z meraní približne každých 5 minút.' : ''}</p>`;
  panel.onclick = (e) => {
    const t = e.target;
    if (t.dataset.unit) { panel.dataset.unit = t.dataset.unit; panel.dataset.page = 0; render(panel); }
    else if (t.dataset.go && !t.disabled) { panel.dataset.page = +panel.dataset.page + +t.dataset.go; render(panel); }
    else if (t.dataset.key) {
      panel.querySelectorAll('.hit.on').forEach((h) => h.classList.remove('on'));
      t.classList.add('on');
      panel.querySelector('.readout').textContent = readoutText(kind, t.dataset.key, unit, data[t.dataset.key]);
    }
  };
}

function load() {
  loading ??= fetch('?stats=1', { cache: 'no-store' }).then((r) => {
    if (!r.ok) throw new Error(r.status);
    return r.json();
  });
  return loading;
}

document.querySelectorAll('button[data-panel]').forEach((btn) => {
  btn.addEventListener('click', async () => {
    const kind = btn.dataset.panel;
    if (open[kind]) {
      open[kind].remove();
      delete open[kind];
      btn.setAttribute('aria-expanded', 'false');
      btn.querySelector('.more').textContent = '▾';
      return;
    }
    const panel = document.createElement('div');
    panel.className = 'panel ' + kind;
    panel.dataset.kind = kind;
    panel.dataset.unit = 'day';
    panel.dataset.page = 0;
    panel.textContent = 'Načítavam…';
    btn.after(panel);
    open[kind] = panel;
    btn.setAttribute('aria-expanded', 'true');
    btn.querySelector('.more').textContent = '▴';
    try {
      stats = await load();
      if (open[kind] === panel) render(panel);
    } catch (e) {
      loading = null;
      panel.textContent = 'Štatistiky sa nepodarilo načítať.';
    }
  });
});

let resizeTimer;
let lastWidth = window.innerWidth;
window.addEventListener('resize', () => {
  if (window.innerWidth === lastWidth) return;
  lastWidth = window.innerWidth;
  clearTimeout(resizeTimer);
  resizeTimer = setTimeout(() => {
    if (stats) Object.values(open).forEach(render);
  }, 150);
});

// Obnova stránky každých 5 minút, ale nie počas prezerania štatistík.
setInterval(() => {
  if (Object.keys(open).length === 0) location.reload();
}, 300000);
