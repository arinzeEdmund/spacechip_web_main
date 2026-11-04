// My virtual numbers: status filter, cancel, and forwarding settings.
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // Status filter
    const filter = document.getElementById('mnFilter');
    filter?.addEventListener('click', (e) => {
        const b = e.target.closest('button[data-filter]');
        if (!b) return;
        const f = b.dataset.filter;
        filter.querySelectorAll('button').forEach((x) => x.classList.toggle('is-active', x === b));
        document.querySelectorAll('.mn-card').forEach((c) => { c.hidden = f !== 'all' && c.dataset.status !== f; });
    });

    // Cancel renewal
    document.querySelectorAll('.js-cancel').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const card = btn.closest('[data-sub-id]');
            const id = card?.dataset.subId;
            if (!id || !confirm('Cancel renewal for this number? It stays active until the end of the current period.')) return;
            btn.disabled = true;
            try {
                const res = await fetch(`/api/virtual-numbers/${encodeURIComponent(id)}/cancel`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                });
                if (!res.ok) throw new Error();
                window.location.reload();
            } catch (e) {
                btn.disabled = false;
                window.spacechipToast?.('Could not cancel — please try again');
            }
        });
    });

    // Inbox drawer
    const drawer = document.getElementById('mnDrawer');
    const thread = document.getElementById('mnThread');
    const sub = document.getElementById('mnDrawerSub');
    let lastFocus = null;
    const close = () => { drawer.hidden = true; lastFocus?.focus(); };
    drawer.addEventListener('click', (e) => { if (e.target === drawer || e.target.closest('[data-close]')) close(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !drawer.hidden) close(); });

    const skeleton = () => Array.from({ length: 4 }, (_, i) => `<div class="skeleton" style="height:58px;width:${i % 2 ? 62 : 78}%;border-radius:14px;${i % 2 ? 'align-self:flex-end' : ''}"></div>`).join('');

    document.querySelectorAll('.js-inbox').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const id = btn.closest('[data-sub-id]')?.dataset.subId;
            if (!id) return;
            lastFocus = btn;
            sub.textContent = btn.dataset.number || '';
            thread.innerHTML = skeleton();
            drawer.hidden = false;
            drawer.querySelector('[data-close]').focus();
            try {
                const res = await fetch(`/api/virtual-numbers/${encodeURIComponent(id)}/messages?per_page=100`, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                const items = Array.isArray(json.items) ? json.items.slice().reverse() : [];
                if (!items.length) {
                    thread.innerHTML = `<div class="ui-empty" style="margin:auto 0"><p class="ui-empty__title">No messages yet</p><p class="ui-empty__desc">Texts and voicemails sent to this number will appear here.</p></div>`;
                    return;
                }
                thread.innerHTML = items.map((m) => {
                    const when = m.created_at ? new Date(m.created_at).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : '';
                    const out = m.direction === 'outbound' || m.direction === 'out';
                    const type = m.message_type === 'voicemail' ? '<span class="ui-chip ui-chip--accent">Voicemail</span><br>' : '';
                    const media = m.media_count ? ` · ${m.media_count} attachment${m.media_count > 1 ? 's' : ''}` : '';
                    return `<div class="mn-msg ${out ? 'out' : ''}">${type}${esc(m.body || (m.message_type === 'voicemail' ? 'Voicemail received' : '(no text)'))}<small>${out ? 'To' : 'From'} ${esc(out ? m.to : m.from)} · ${esc(when)}${media}</small></div>`;
                }).join('');
                thread.scrollTop = thread.scrollHeight;
            } catch (e) {
                thread.innerHTML = `<div class="ui-empty" style="margin:auto 0"><p class="ui-empty__title">Couldn’t load messages</p><p class="ui-empty__desc">Check your connection and try again.</p></div>`;
            }
        });
    });
})();
