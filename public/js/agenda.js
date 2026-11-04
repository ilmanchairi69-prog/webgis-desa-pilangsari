document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-agenda]');
    if (!root) return;
    const events = JSON.parse(document.getElementById('agenda-data').textContent);
    const grid = root.querySelector('[data-calendar-grid]');
    const title = root.querySelector('[data-calendar-title]');
    const selectedTitle = root.querySelector('[data-selected-title]');
    const selectedList = root.querySelector('[data-selected-list]');
    const dialog = root.querySelector('[data-agenda-dialog]');
    const todayParts = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date());
    const part = type => todayParts.find(value => value.type === type).value;
    const today = `${part('year')}-${part('month')}-${part('day')}`;
    let selected = today;
    let current = new Date(Date.UTC(Number(part('year')), Number(part('month')) - 1, 1));
    const dateKey = date => date.toISOString().slice(0, 10);
    const fullDate = date => new Intl.DateTimeFormat('id-ID', { timeZone: 'UTC', weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(date);
    const dateFromKey = key => new Date(`${key}T00:00:00Z`);
    const byDate = key => events.filter(event => event.date === key);
    let lastFocus = null;

    root.querySelector('[data-stat="upcoming"]').textContent = events.filter(event => event.date >= today).length;
    root.querySelector('[data-stat="month"]').textContent = events.filter(event => event.date.slice(0, 7) === today.slice(0, 7)).length;

    function openDetails(event, trigger) {
        lastFocus = trigger;
        for (const [name, value] of Object.entries({ title: event.title, category: event.category, date: fullDate(dateFromKey(event.date)), time: `${event.start} - ${event.end} WIB`, location: event.location, organizer: event.organizer, description: event.description, notice: event.notice })) {
            root.querySelector(`[data-dialog-${name}]`).textContent = value;
        }
        root.querySelector('[data-dialog-category]').className = `agenda-category category-${event.category_key}`;
        dialog.showModal();
    }
    function eventButton(event, className) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = className;
        button.textContent = event.title;
        button.addEventListener('click', () => openDetails(event, button));
        return button;
    }
    function renderSelected() {
        selectedTitle.textContent = fullDate(dateFromKey(selected));
        selectedList.replaceChildren();
        const matches = byDate(selected);
        if (!matches.length) {
            const empty = document.createElement('p');
            empty.textContent = 'Tidak ada kegiatan pada tanggal ini.';
            selectedList.append(empty);
        }
        matches.forEach(event => selectedList.append(eventButton(event, `agenda-selected-event category-${event.category_key}`)));
    }
    function render() {
        title.textContent = new Intl.DateTimeFormat('id-ID', { timeZone: 'UTC', month: 'long', year: 'numeric' }).format(current);
        grid.replaceChildren();
        const first = new Date(Date.UTC(current.getUTCFullYear(), current.getUTCMonth(), 1));
        const start = new Date(first);
        start.setUTCDate(1 - first.getUTCDay());
        for (let i = 0; i < 42; i++) {
            const date = new Date(start);
            date.setUTCDate(start.getUTCDate() + i);
            const key = dateKey(date);
            const day = document.createElement('div');
            day.className = 'agenda-day';
            if (date.getUTCMonth() !== current.getUTCMonth()) day.classList.add('is-outside');
            if (date.getUTCDay() === 0 || date.getUTCDay() === 6) day.classList.add('is-weekend');
            if (key === selected) day.classList.add('is-selected');
            if (key === today) day.classList.add('is-today');
            const select = document.createElement('button');
            select.type = 'button';
            select.className = 'agenda-date-button';
            select.textContent = date.getUTCDate();
            select.setAttribute('aria-label', `${fullDate(date)}${byDate(key).length ? `, ${byDate(key).length} agenda` : ''}`);
            select.setAttribute('aria-pressed', String(key === selected));
            select.addEventListener('click', () => { selected = key; current = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1)); render(); });
            day.append(select);
            byDate(key).forEach(event => day.append(eventButton(event, `agenda-calendar-event category-${event.category_key}`)));
            grid.append(day);
        }
        renderSelected();
    }
    root.querySelector('[data-calendar-prev]').addEventListener('click', () => { current = new Date(Date.UTC(current.getUTCFullYear(), current.getUTCMonth() - 1, 1)); render(); });
    root.querySelector('[data-calendar-next]').addEventListener('click', () => { current = new Date(Date.UTC(current.getUTCFullYear(), current.getUTCMonth() + 1, 1)); render(); });
    root.querySelector('[data-calendar-today]').addEventListener('click', () => { selected = today; current = new Date(Date.UTC(Number(part('year')), Number(part('month')) - 1, 1)); render(); });
    root.querySelectorAll('[data-agenda-event]').forEach(button => button.addEventListener('click', () => { const event = events.find(item => item.id === button.dataset.agendaEvent); if (event) openDetails(event, button); }));
    root.querySelector('[data-dialog-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener('close', () => lastFocus?.focus());
    render();
});
