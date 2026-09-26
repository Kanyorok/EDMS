(() => {
    const cards = document.querySelectorAll('[data-pending-approvals]');
    cards.forEach(card => {
        let loading = false;
        let expired = false;
        const button = card.querySelector('[data-approvals-refresh]');
        const status = card.querySelector('[data-approvals-status]');
        async function refresh() {
            if (loading || expired || document.hidden || !card.isConnected) return;
            loading = true;
            button.disabled = true;
            try {
                const response = await fetch(card.dataset.endpoint, {
                    credentials: 'same-origin', cache: 'no-store',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (response.status === 401 || response.status === 419 || response.redirected) {
                    expired = true;
                    card.querySelector('[data-approvals-items]').replaceChildren();
                    document.querySelectorAll('[data-approval-count]').forEach(el => { el.textContent = '—'; });
                    status.textContent = 'Your session has expired. Reload the page to sign in.';
                    return;
                }
                if (!response.ok) throw new Error('Refresh failed');
                const data = await response.json();
                const items = card.querySelector('[data-approvals-items]');
                if (items.innerHTML !== data.html) items.innerHTML = data.html;
                document.querySelectorAll('[data-approval-count]').forEach(el => { el.textContent = data.count; });
                status.textContent = 'Updated ' + new Date().toLocaleTimeString();
            } catch (error) {
                status.textContent = 'Could not refresh approvals. Showing the last loaded list. Try Refresh.';
            } finally {
                loading = false;
                button.disabled = expired;
            }
        }
        button.addEventListener('click', refresh);
        window.addEventListener('focus', refresh);
        window.addEventListener('pageshow', refresh);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
        window.setInterval(refresh, 30000);
    });
})();
