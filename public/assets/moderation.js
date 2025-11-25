// public/assets/moderation.js
(function () {  
  'use strict';  
  
  // ============================================================  
  // 1. CONSTANTS & CONFIGURATION  
  // ============================================================  
  const CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';  
  
  // ============================================================  
  // 2. UTILITY FUNCTIONS  
  // ============================================================  
    
  /**  
   * Lấy giá trị window (phút) từ form filter  
   * @returns {string} Số phút dưới dạng string, mặc định '30'  
   */  
  function getWindowMinutes() {  
    const el = document.querySelector('input[name="window"]');  
    const v = el ? parseInt(el.value, 10) : NaN;  
    return Number.isFinite(v) ? String(Math.max(0, v)) : '30';  
  }  
  
  /**  
   * Gọi action.php với xử lý lỗi rõ ràng  
   * @param {FormData|Object} payload - FormData hoặc object chứa dữ liệu  
   * @returns {Promise<Object>} JSON response từ server  
   */  
  async function callAction(payload) {  
    // Hỗ trợ cả FormData và Object  
    const fd = payload instanceof FormData  
      ? payload  
      : Object.entries(payload).reduce((form, [k, v]) => {  
          form.append(k, v);  
          return form;  
        }, new FormData());  
  
    // Đảm bảo CSRF token luôn có  
    if (!fd.has('csrf')) {  
      fd.append('csrf', CSRF);  
    }  
  
    const res = await fetch('/admin/action.php', {  
      method: 'POST',  
      body: fd,  
      credentials: 'same-origin',  
      headers: { 'Accept': 'application/json' }  
    });  
  
    const text = await res.text();  
    let data;  
  
    try {  
      data = JSON.parse(text);  
    } catch {  
      data = null;  
    }  
  
    if (!res.ok) {  
      const errorMsg = (data && data.error) || text || `HTTP ${res.status}`;  
      throw new Error(errorMsg);  
    }  
  
    return data || {};  
  }  
  
  // ============================================================  
  // 3. UI INTERACTION HANDLERS  
  // ============================================================  
  
  /**  
   * Khởi tạo toggle mở rộng/thu gọn nội dung comment  
   */  
  function initToggleContent() {  
    document.querySelectorAll('[data-toggle]').forEach(btn => {  
      btn.addEventListener('click', () => {  
        const msg = btn.closest('.card').querySelector('.msg');  
        const collapsed = msg.getAttribute('data-collapsed') === '1';  
        msg.style.maxHeight = collapsed ? 'unset' : '4.5em';  
        msg.style.overflow = collapsed ? 'visible' : 'hidden';  
        msg.setAttribute('data-collapsed', collapsed ? '0' : '1');  
        btn.textContent = collapsed ? 'Thu gọn' : 'Hiện đầy đủ';  
      });  
    });  
  }  
  
  // ============================================================  
  // 4. COMMENT ACTION HANDLERS  
  // ============================================================  
  
  /**  
   * Xử lý trả lời comment  
   */  
  async function handleReply(id, replyBtn) {  
    const message = prompt('Nhập nội dung trả lời:');  
    if (!message) return;  
  
    const old = replyBtn.textContent;  
    replyBtn.disabled = true;  
    replyBtn.textContent = 'Đang gửi…';  
  
    try {  
      await callAction({  
        action: 'comment',  
        id: id,  
        message: message  
      });  
      alert('Đã gửi phản hồi.');  
    } catch (e) {  
      alert('Lỗi trả lời: ' + e.message);  
    } finally {  
      replyBtn.disabled = false;  
      replyBtn.textContent = old;  
    }  
  }  
  
  /**  
   * Xử lý ẩn/hiện comment  
   */  
  async function handleHideUnhide(id, btn, shouldHide) {  
    const old = btn.textContent;  
    btn.disabled = true;  
    btn.textContent = shouldHide ? 'Đang ẩn…' : 'Đang hiện…';  
  
    try {  
      await callAction({  
        action: 'hide_comment',  
        id: id,  
        hide: shouldHide ? '1' : '0'  
      });  
      alert(shouldHide ? 'Đã ẩn bình luận.' : 'Đã hiện bình luận.');  
    } catch (e) {  
      alert((shouldHide ? 'Lỗi ẩn: ' : 'Lỗi hiện: ') + e.message);  
    } finally {  
      btn.disabled = false;  
      btn.textContent = old;  
    }  
  }  
  
  /**  
   * Khởi tạo các nút action cho từng comment card  
   */  
  function initCommentActions() {  
    document.querySelectorAll('.card').forEach(card => {  
      const id = card.getAttribute('data-id');  
      const replyBtn = card.querySelector('[data-reply]');  
      const hideBtn = card.querySelector('[data-hide]');  
      const unhideBtn = card.querySelector('[data-unhide]');  
  
      if (replyBtn) {  
        replyBtn.addEventListener('click', () => handleReply(id, replyBtn));  
      }  
  
      if (hideBtn) {  
        hideBtn.addEventListener('click', () => handleHideUnhide(id, hideBtn, true));  
      }  
  
      if (unhideBtn) {  
        unhideBtn.addEventListener('click', () => handleHideUnhide(id, unhideBtn, false));  
      }  
    });  
  }  
  
  // ============================================================  
  // 5. SCAN OPERATION HANDLERS  
  // ============================================================  
  
  /**  
   * Xử lý quét toàn bộ (reply + hide)  
   */  
  async function handleFullScan() {  
    const scanBtn = document.getElementById('scanBtn');  
    const scanText = document.getElementById('scanText');  
    if (!scanBtn) return;  
  
    const old = scanText ? scanText.textContent : '';  
    scanBtn.disabled = true;  
    if (scanText) scanText.textContent = 'Đang quét…';  
  
    try {  
      const data = await callAction({  
        action: 'scan_now',  
        window: getWindowMinutes()  
      });  
      alert(`Đã quét: ${data.scanned}\nVượt ngưỡng: ${data.high_risk}\nĐã trả lời: ${data.replied}\nĐã ẩn: ${data.hidden}`);  
      location.reload();  
    } catch (e) {  
      alert('Lỗi quét: ' + e.message);  
      scanBtn.disabled = false;  
      if (scanText) scanText.textContent = old;  
    }  
  }  
  
  /**  
   * Xử lý quét và reply only  
   */  
  async function handleScanReply() {  
    const scanReplyBtn = document.getElementById('scanReplyBtn');  
    if (!scanReplyBtn) return;  
  
    scanReplyBtn.disabled = true;  
  
    try {  
      const data = await callAction({  
        action: 'scan_reply_only',  
        window: getWindowMinutes()  
      });  
      alert(`Đã quét: ${data.scanned}\nVượt ngưỡng: ${data.high_risk}\nĐã trả lời: ${data.replied}`);  
      location.reload();  
    } catch (e) {  
      alert('Lỗi: ' + e.message);  
      scanReplyBtn.disabled = false;  
    }  
  }  
  
  /**  
   * Xử lý quét và hide only  
   */  
  async function handleScanHide() {  
    const scanHideBtn = document.getElementById('scanHideBtn');  
    if (!scanHideBtn) return;  
  
    scanHideBtn.disabled = true;  
  
    try {  
      const data = await callAction({  
        action: 'scan_hide_only',  
        window: getWindowMinutes()  
      });  
      alert(`Đã quét: ${data.scanned}\nVượt ngưỡng: ${data.high_risk}\nĐã ẩn: ${data.hidden}`);  
      location.reload();  
    } catch (e) {  
      alert('Lỗi: ' + e.message);  
      scanHideBtn.disabled = false;  
    }  
  }  
  
  /**  
   * Xử lý quét bài viết  
   */  
  async function handleScanPosts() {  
    const scanPostsBtn = document.getElementById('scanPostsBtn');  
    const scanPostsText = document.getElementById('scanPostsText');  
    if (!scanPostsBtn) return;  
  
    scanPostsBtn.disabled = true;  
    if (scanPostsText) scanPostsText.textContent = 'Đang quét bài viết…';  
  
    try {  
      const data = await callAction({  
        action: 'scan_posts_now',  
        window: getWindowMinutes()  
      });  
      alert(`Bài viết đã quét: ${data.scanned}\nĐã cảnh báo: ${data.warned}\nBỏ qua: ${data.skipped}\nLỗi: ${data.errors}`);  
      location.reload();  
    } catch (e) {  
      alert('Lỗi quét bài viết: ' + e.message);  
      scanPostsBtn.disabled = false;  
      if (scanPostsText) scanPostsText.textContent = '';  
    }  
  }  
  
  /**  
   * Khởi tạo tất cả các nút scan  
   */  
  function initScanButtons() {  
    const scanBtn = document.getElementById('scanBtn');  
    const scanReplyBtn = document.getElementById('scanReplyBtn');  
    const scanHideBtn = document.getElementById('scanHideBtn');  
    const scanPostsBtn = document.getElementById('scanPostsBtn');  
  
    if (scanBtn) {  
      scanBtn.addEventListener('click', handleFullScan);  
    }  
  
    if (scanReplyBtn) {  
      scanReplyBtn.addEventListener('click', handleScanReply);  
    }  
  
    if (scanHideBtn) {  
      scanHideBtn.addEventListener('click', handleScanHide);  
    }  
  
    if (scanPostsBtn) {  
      scanPostsBtn.addEventListener('click', (ev) => {  
        ev.preventDefault();  
        handleScanPosts();  
      });  
    }  
  }  

  async function handleHideUnhide(id, btn, shouldHide) {  
  const old = btn.textContent;  
  btn.disabled = true;  
  btn.textContent = shouldHide ? 'Đang ẩn…' : 'Đang hiện…';  
  
  try {  
    await callAction({  
      action: 'hide_comment',  
      id: id,  
      hide: shouldHide ? '1' : '0'  
    });  
    alert(shouldHide ? 'Đã ẩn bình luận.' : 'Đã hiện bình luận.');  
      
    // Toggle button  
    if (shouldHide) {  
      btn.textContent = 'Hiện';  
      btn.setAttribute('data-unhide', '');  
      btn.removeAttribute('data-hide');  
    } else {  
      btn.textContent = 'Ẩn';  
      btn.setAttribute('data-hide', '');  
      btn.removeAttribute('data-unhide');  
    }  
      
    // Re-bind event listener  
    btn.onclick = () => handleHideUnhide(id, btn, !shouldHide);  
      
  } catch (e) {  
    alert((shouldHide ? 'Lỗi ẩn: ' : 'Lỗi hiện: ') + e.message);  
    btn.textContent = old;  
  } finally {  
    btn.disabled = false;  
  }  
}
  
  // ============================================================  
  // 6. INITIALIZATION  
  // ============================================================  
  
  /**  
   * Khởi tạo tất cả các chức năng khi DOM ready  
   */  
  function init() {  
    initToggleContent();  
    initCommentActions();  
    initScanButtons();  
  }  
  
  // Chạy khi DOM đã sẵn sàng  
  if (document.readyState === 'loading') {  
    document.addEventListener('DOMContentLoaded', init);  
  } else {  
    init();  
  }  
  
})();
