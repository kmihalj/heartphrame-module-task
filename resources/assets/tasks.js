(() => {
    'use strict';

    /**
     * HR: Prikazuje malu lokaliziranu grešku bez pomicanja sadržaja stranice.
     * EN: Shows a compact localized error without shifting page content.
     */
    const toast = (message, closeLabel) => {
        let container = document.querySelector('.editor-task-toast-container');
        if (!(container instanceof HTMLElement)) {
            container = document.createElement('div');
            container.className = 'editor-task-toast-container';
            container.setAttribute('aria-live', 'assertive');
            document.body.appendChild(container);
        }

        const item = document.createElement('div');
        item.className = 'editor-task-toast';
        item.setAttribute('role', 'alert');
        const text = document.createElement('span');
        text.textContent = message;
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'editor-task-toast-close';
        close.setAttribute('aria-label', closeLabel);
        close.textContent = '×';
        close.addEventListener('click', () => item.remove());
        item.append(text, close);
        container.appendChild(item);
    };

    /**
     * HR: Dohvaća svježi CSRF token prije svake promjene checkboxa.
     * EN: Fetches a fresh CSRF token before every checkbox change.
     */
    const csrf = async (list) => {
        const response = await fetch(list.dataset.taskCsrfUrl || '', {
            credentials: 'same-origin',
            headers: {'Accept': 'application/json'},
        });
        const payload = await response.json();
        if (!response.ok || !payload.csrf) {
            throw new Error(payload.error || list.dataset.taskCsrfError);
        }

        return payload.csrf;
    };

    /**
     * HR: Dodaje jedan gumb praćenja cijeloj listi kada ga je aplikacijski modul oglasio.
     * EN: Adds one follow button to the whole list when advertised by the application module.
     */
    const enhanceFollowing = (list) => {
        if (!list.dataset.taskFollowToggleUrl || !list.dataset.taskFollowStatusUrl) {
            return;
        }

        const meta = list.querySelector('.editor-task-list-meta');
        const listUuid = list.dataset.taskListUuid || '';
        if (!(meta instanceof HTMLElement) || !listUuid) {
            return;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-secondary btn-sm editor-task-list-follow';
        button.textContent = list.dataset.taskFollowLabel || 'Follow task list';
        button.setAttribute('aria-pressed', 'false');
        meta.hidden = false;
        meta.appendChild(button);

        const statusUrl = new URL(list.dataset.taskFollowStatusUrl, window.location.origin);
        statusUrl.searchParams.set('target_type', 'task_list');
        statusUrl.searchParams.set('target_id', listUuid);
        fetch(statusUrl, {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
            .then((response) => response.ok ? response.json() : null)
            .then((payload) => {
                if (!payload) return;
                const following = payload.following === true;
                button.classList.toggle('is-following', following);
                button.textContent = following
                    ? (list.dataset.taskUnfollowLabel || 'Unfollow task list')
                    : (list.dataset.taskFollowLabel || 'Follow task list');
                button.setAttribute('aria-pressed', following ? 'true' : 'false');
            })
            .catch(() => {});

        button.addEventListener('click', async () => {
            button.disabled = true;
            try {
                const token = await csrf(list);
                const data = new URLSearchParams();
                data.set(token.name, token.token);
                data.set('target_type', 'task_list');
                data.set('target_id', listUuid);
                data.set('document_id', list.dataset.taskDocument || '');
                data.set('label', list.dataset.taskListLabel || '');
                data.set('return_url', `${window.location.pathname}${window.location.search}`);
                const response = await fetch(list.dataset.taskFollowToggleUrl, {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                    headers: {'Accept': 'text/html'},
                    redirect: 'follow',
                });
                if (!response.ok) {
                    throw new Error(list.dataset.taskSaveError || 'Follow could not be saved.');
                }

                window.location.reload();
            } catch (error) {
                toast(
                    error instanceof Error ? error.message : list.dataset.taskSaveError,
                    list.dataset.taskCloseLabel || 'Close',
                );
                button.disabled = false;
            }
        });
    };

    document.querySelectorAll('.editor-task-list').forEach((list) => {
        if (list instanceof HTMLElement) {
            enhanceFollowing(list);
        }
    });

    /**
     * HR: Formatira audit vrijeme prema jeziku dokumenta u pregledniku.
     * EN: Formats the audit timestamp for the document language in the browser.
     */
    const localizedDate = (value, language) => {
        if (!value) {
            return '';
        }

        const parsed = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(parsed.getTime())) {
            return String(value);
        }

        const locale = language.toLowerCase().startsWith('hr') ? 'hr-HR' : 'en-GB';
        return new Intl.DateTimeFormat(locale, {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        }).format(parsed);
    };

    /**
     * HR: Automatski sprema promjenu zadatka i osvježava zajednički audit liste.
     * EN: Automatically saves a task change and refreshes the list's shared audit.
     */
    document.addEventListener('change', async (event) => {
        const checkbox = event.target;
        if (!(checkbox instanceof HTMLInputElement)
            || !checkbox.classList.contains('editor-task-checkbox')
            || checkbox.disabled) {
            return;
        }

        const list = checkbox.closest('.editor-task-list');
        const item = checkbox.closest('.editor-task-item');
        if (!(list instanceof HTMLElement) || !(item instanceof HTMLElement)) {
            return;
        }

        const desired = checkbox.checked;
        checkbox.disabled = true;
        try {
            const token = await csrf(list);
            const data = new URLSearchParams();
            data.set(token.name, token.token);
            data.set('task_uuid', checkbox.dataset.taskUuid || '');
            data.set('document_id', checkbox.dataset.taskDocument || '');
            data.set('language', checkbox.dataset.taskLanguage || '');
            data.set('completed', desired ? '1' : '0');
            const response = await fetch(list.dataset.taskStateUrl || '', {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: {'Accept': 'application/json'},
            });
            const payload = await response.json();
            if (!response.ok || payload.ok !== true) {
                throw new Error(payload.error || list.dataset.taskSaveError);
            }

            item.classList.toggle('editor-task-item-completed', desired);
            let audit = list.querySelector('.editor-task-list-audit');
            const state = payload.state || {};
            if (!(audit instanceof HTMLElement)) {
                audit = document.createElement('small');
                audit.className = 'editor-task-list-audit';
                const meta = list.querySelector('.editor-task-list-meta');
                if (meta instanceof HTMLElement) {
                    meta.prepend(audit);
                }
            }
            const language = (checkbox.dataset.taskLanguage || '').toLowerCase();
            const label = list.dataset.taskLastChangedLabel || 'Last changed';
            const parts = [
                state.updated_by_display_name,
                localizedDate(state.updated_at, language),
            ].filter(Boolean);
            audit.textContent = `${label}: ${parts.join(' · ')}`;
            audit.hidden = parts.length === 0;
        } catch (error) {
            checkbox.checked = !desired;
            toast(
                error instanceof Error ? error.message : list.dataset.taskSaveError,
                list.dataset.taskCloseLabel || 'Close',
            );
        } finally {
            checkbox.disabled = false;
        }
    });
})();
