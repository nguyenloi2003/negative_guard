(function () {
  const $ = (s) => document.querySelector(s);
  const qEl = $('#q');
  const btn = $('#go');
  const result = $('#result');
  const answerEl = $('#answer');
  const badgesEl = $('#badges');
  const citaBlock = $('#citablock');
  const citasEl = $('#citas');
  const msgEl = $('#msg');

  function fmtDate(s) {
    if (!s) return '';
    // s có thể là "YYYY-MM-DD HH:MM:SS" hoặc ISO
    const d = new Date(s.replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return s;
    return d.toLocaleString();
  }

  function setLoading(v) {
    btn.disabled = v;
    msgEl.textContent = v ? 'Đang truy vấn…' : '';
  }

  async function run() {
    const q = (qEl.value || '').trim();
    if (!q) {
      qEl.focus();
      return;
    }
    setLoading(true);

    try {
      const res = await fetch('/answer.php?q=' + encodeURIComponent(q));
      const data = await res.json();

      if (data.error) throw new Error(data.error);

      // answer
      answerEl.textContent = data.answer || 'Không tìm thấy thông tin phù hợp. Thử câu hỏi ngắn và rõ hơn.';
      result.hidden = false;

      // badges (minh hoạ – có thể thêm logic đánh giá)
      badgesEl.innerHTML = '';
      const b1 = document.createElement('div');
      b1.className = 'badge';
      b1.textContent = 'Tóm lược từ nguồn IUH';
      badgesEl.appendChild(b1);

      // citations
      citasEl.innerHTML = '';
      const seen = new Set();
      (data.citations || []).forEach(c => {
        const url = c.url || '';
        if (!url || seen.has(url)) return;
        seen.add(url);

        const row = document.createElement('div');
        row.className = 'small';
        const date = fmtDate(c.date);
        const trust = (typeof c.trust === 'number') ? ` • độ tin cậy: ${c.trust.toFixed(2)}` : '';
        const title = (c.title && c.title.trim()) ? ` – ${c.title.trim()}` : '';
        row.innerHTML =
          `<a href="${url}" target="_blank" rel="noopener">Nguồn Facebook</a>` +
          `${title}${date ? ` • ${date}` : ''}${trust}`;
        citasEl.appendChild(row);
      });
      citaBlock.hidden = citasEl.children.length === 0;

      msgEl.textContent = '';
    } catch (e) {
      msgEl.textContent = 'Lỗi: ' + e.message;
      result.hidden = true;
      citaBlock.hidden = true;
    } finally {
      setLoading(false);
    }
  }

  // search on click
  btn.addEventListener('click', run);
  // Enter to search
  qEl.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') run();
  });

  // auto fill from ?q=
  const params = new URLSearchParams(location.search);
  if (params.get('q')) {
    qEl.value = params.get('q');
    run();
  }
})();
