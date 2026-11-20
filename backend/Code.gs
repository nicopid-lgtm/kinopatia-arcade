/**
 * Kinopatia Arcade · classifica condivisa
 * Google Apps Script collegato a un Google Sheet.
 * Installazione: vedi backend/ISTRUZIONI.md
 *
 * Il foglio "Classifica" contiene una riga per giocatore e per edizione.
 * Nessun dato personale (email) viene mai restituito al gioco: solo nome abbreviato, azienda e punteggio.
 */
const SHEET = 'Classifica';
const MAX_SCORE = 300;           // punteggi oltre questa soglia vengono scartati
const TOP_N = 20;
const H = ['id','chiave','edizione','nome','azienda','email','consenso','record','partite','iscritto_il','aggiornato_il','hubspot'];

function sheet_() {
  const ss = SpreadsheetApp.getActive();
  let s = ss.getSheetByName(SHEET);
  if (!s) { s = ss.insertSheet(SHEET); s.appendRow(H); s.setFrozenRows(1); s.getRange(1,1,1,H.length).setFontWeight('bold'); }
  return s;
}
function out_(o) { return ContentService.createTextOutput(JSON.stringify(o)).setMimeType(ContentService.MimeType.JSON); }
function clean_(v, n) { return String(v == null ? '' : v).replace(/[<>]/g, '').trim().slice(0, n); }
function short_(name) { const p = String(name).trim().split(/\s+/); return p.length > 1 ? p[0] + ' ' + p[p.length-1][0].toUpperCase() + '.' : p[0]; }

function board_(edition, id) {
  const rows = sheet_().getDataRange().getValues().slice(1)
    .filter(r => r[2] === edition && Number(r[7]) > 0)
    .sort((a, b) => (b[7] - a[7]) || (new Date(a[10]) - new Date(b[10])));
  const me = id ? rows.findIndex(r => r[0] === id) + 1 : 0;
  const list = rows.slice(0, TOP_N).map(r => ({ name: short_(r[3]), company: r[4], score: Number(r[7]), me: r[0] === id }));
  if (me > TOP_N) list.push({ name: short_(rows[me-1][3]), company: rows[me-1][4], score: Number(rows[me-1][7]), me: true, pos: me });
  return { ok: true, rows: list, me: me, total: rows.length };
}

function doGet(e) {
  const p = e.parameter || {};
  return out_(board_(clean_(p.edition || 'natale', 20), clean_(p.id, 40)));
}

function doPost(e) {
  let b; try { b = JSON.parse(e.postData.contents); } catch (err) { return out_({ ok: false, error: 'bad_json' }); }
  const lock = LockService.getScriptLock(); lock.waitLock(10000);
  try {
    const s = sheet_(), data = s.getDataRange().getValues();
    const id = clean_(b.id, 40), key = clean_(b.key, 60), edition = clean_(b.edition || 'natale', 20);
    let score = Math.floor(Number(b.score) || 0); if (score < 0 || score > MAX_SCORE) score = 0;
    const now = new Date();
    const idx = data.findIndex((r, i) => i > 0 && r[0] === id && r[2] === edition);
    const any = data.findIndex((r, i) => i > 0 && r[0] === id);

    if (b.action === 'register') {
      const name = clean_(b.name, 60), company = clean_(b.company, 80), email = clean_(b.email, 120).toLowerCase();
      if (!id || !key || !name || !company || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email) || b.consent !== true) return out_({ ok: false, error: 'invalid' });
      if (any > 0 && data[any][1] !== key) return out_({ ok: false, error: 'forbidden' });
      if (idx > 0) {
        s.getRange(idx + 1, 4, 1, 4).setValues([[name, company, email, true]]);
        if (score > Number(data[idx][7])) s.getRange(idx + 1, 8).setValue(score);
        s.getRange(idx + 1, 11).setValue(now);
      } else {
        s.appendRow([id, key, edition, name, company, email, true, score, 1, now, now, '']);
      }
      return out_(board_(edition, id));
    }

    if (b.action === 'score') {
      if (any < 0) return out_({ ok: false, error: 'unknown' });
      if (data[any][1] !== key) return out_({ ok: false, error: 'forbidden' });
      if (idx > 0) {
        const r = idx + 1;
        if (score > Number(data[idx][7])) s.getRange(r, 8).setValue(score);
        s.getRange(r, 9).setValue(Number(data[idx][8] || 0) + 1);
        s.getRange(r, 11).setValue(now);
      } else { // stesso giocatore, nuova edizione
        const o = data[any];
        s.appendRow([id, key, edition, o[3], o[4], o[5], o[6], score, 1, now, now, '']);
      }
      return out_(board_(edition, id));
    }
    return out_({ ok: false, error: 'unknown_action' });
  } finally { lock.releaseLock(); }
}
