const board = document.querySelector('#kanban-board');
const createColumnButton = document.querySelector('.create-column-btn');
const storageKey = 'flowtree-board-state';

const priorityMeta = {
    low: {
        label: 'Baixa prioridade'
    },
    medium: {
        label: 'Média prioridade'
    },
    high: {
        label: 'Alta prioridade'
    }
};

const accentPalette = ['#d573b6', '#fea065', '#fed565', '#92a5fb', '#6ed0bc', '#8b5cf6', '#f97316'];

const defaultBoard = {
    columns: [
        {
            id: 'column-1',
            title: 'Pendente',
            accent: '#d573b6',
            cards: [
                {
                    id: 'card-1',
                    title: 'Revisar documento do projeto',
                    priority: 'medium',
                    dueDate: '2026-06-18',
                    comments: 1,
                    attachments: 2,
                    avatar: 'src/images/avatar2.png'
                }
            ]
        },
        {
            id: 'column-2',
            title: 'Em front-end',
            accent: '#fea065',
            cards: [
                {
                    id: 'card-2',
                    title: 'Revisar documento do projeto',
                    priority: 'high',
                    dueDate: '2026-06-19',
                    comments: 1,
                    attachments: 1,
                    avatar: 'src/images/avatar3.png'
                }
            ]
        },
        {
            id: 'column-3',
            title: 'Em back-end',
            accent: '#fed565',
            cards: [
                {
                    id: 'card-3',
                    title: 'Revisar documento do projeto',
                    priority: 'low',
                    dueDate: '2026-06-21',
                    comments: 1,
                    attachments: 1,
                    avatar: 'src/images/avatar.png'
                }
            ]
        },
        {
            id: 'column-4',
            title: 'Em teste',
            accent: '#92a5fb',
            cards: [
                {
                    id: 'card-4',
                    title: 'Revisar documento do projeto',
                    priority: 'high',
                    dueDate: '2026-06-22',
                    comments: 1,
                    attachments: 1,
                    avatar: 'src/images/avatar.png'
                }
            ]
        },
        {
            id: 'column-5',
            title: 'Concluído',
            accent: '#6ed0bc',
            cards: [
                {
                    id: 'card-5',
                    title: 'Revisar documento do projeto',
                    priority: 'high',
                    dueDate: '2026-06-25',
                    comments: 1,
                    attachments: 1,
                    avatar: 'src/images/avatar.png'
                }
            ]
        }
    ]
};

let boardState = loadBoardState();
let closePopoversBound = false;

function cloneBoardState(state) {
    return JSON.parse(JSON.stringify(state));
}

function formatDate(dateValue) {
    if (!dateValue) {
        return 'Sem prazo';
    }

    const [year, month, day] = dateValue.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    const monthLabel = new Intl.DateTimeFormat('pt-BR', { month: 'short' }).format(date).replace('.', '');

    return `${String(day).padStart(2, '0')} ${monthLabel} ${year}`;
}

function openNativeDatePicker(input) {
    if (typeof input.showPicker === 'function') {
        input.showPicker();
        return;
    }

    input.click();
}

function createColumnId(title) {
    const slug = title
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    return `column-${slug || Date.now()}`;
}

function createCardId() {
    return `card-${Date.now()}-${Math.random().toString(16).slice(2, 8)}`;
}

function getNextAccent(index) {
    return accentPalette[index % accentPalette.length];
}

function normalizeBoardState(rawState) {
    if (!rawState) {
        return cloneBoardState(defaultBoard);
    }

    if (Array.isArray(rawState)) {
        return {
            columns: rawState.map((columnState, index) => ({
                id: columnState.id ? `column-${columnState.id}` : `column-${index + 1}`,
                title: getDefaultColumnTitle(columnState.id, index),
                accent: getNextAccent(index),
                cards: (Array.isArray(columnState.cards) ? columnState.cards : []).map((cardState, cardIndex) => {
                    const normalizedCard = typeof cardState === 'string' ? { id: cardState } : cardState;

                    return {
                        id: normalizedCard.id || createCardId(),
                        title: normalizedCard.title || 'Revisar documento do projeto',
                        priority: normalizedCard.priority || 'medium',
                        dueDate: normalizedCard.dueDate || '',
                        comments: normalizedCard.comments || 1,
                        attachments: normalizedCard.attachments || 1,
                        avatar: normalizedCard.avatar || defaultBoard.columns[index % defaultBoard.columns.length].cards[0].avatar
                    };
                })
            }))
        };
    }

    if (rawState.columns && Array.isArray(rawState.columns)) {
        return {
            columns: rawState.columns.map((columnState, index) => ({
                id: columnState.id || `column-${index + 1}`,
                title: columnState.title || `Coluna ${index + 1}`,
                accent: columnState.accent || getNextAccent(index),
                cards: (Array.isArray(columnState.cards) ? columnState.cards : []).map((cardState, cardIndex) => ({
                    id: cardState.id || createCardId(),
                    title: cardState.title || 'Nova tarefa',
                    priority: cardState.priority || 'medium',
                    dueDate: cardState.dueDate || '',
                    comments: cardState.comments || 0,
                    attachments: cardState.attachments || 0,
                    avatar: cardState.avatar || defaultBoard.columns[index % defaultBoard.columns.length].cards[0].avatar
                }))
            }))
        };
    }

    return cloneBoardState(defaultBoard);
}

function getDefaultColumnTitle(id, index) {
    const titles = ['Pendente', 'Em front-end', 'Em back-end', 'Em teste', 'Concluído'];
    const numericId = Number(String(id).replace(/\D/g, ''));

    if (titles[index]) {
        return titles[index];
    }

    if (numericId && titles[numericId - 1]) {
        return titles[numericId - 1];
    }

    return `Coluna ${index + 1}`;
}

function loadBoardState() {
    const savedState = localStorage.getItem(storageKey);

    if (!savedState) {
        return cloneBoardState(defaultBoard);
    }

    try {
        return normalizeBoardState(JSON.parse(savedState));
    } catch {
        return cloneBoardState(defaultBoard);
    }
}

function saveBoardState() {
    localStorage.setItem(storageKey, JSON.stringify(boardState));
}

function renderPriorityLabel(priority) {
    return priorityMeta[priority]?.label || priorityMeta.medium.label;
}

function renderCard(card) {
    return `
        <div class="kanban-card" draggable="true" data-card-id="${card.id}" data-priority="${card.priority}" data-due-date="${card.dueDate}">
            <button type="button" class="badge ${card.priority}">
                <span class="priority-text">${renderPriorityLabel(card.priority)}</span>
                <i class="fa-solid fa-chevron-down badge-caret"></i>
            </button>

            <p class="card-title">${card.title}</p>

            <div class="card-infos">
                <div class="card-icons">
                    <p>
                        <i class="fa-regular fa-comment"></i>
                        ${card.comments}
                    </p>

                    <p>
                        <i class="fa-solid fa-paperclip"></i>
                        ${card.attachments}
                    </p>

                    <button type="button" class="due-date-trigger" aria-label="Selecionar data">
                        <i class="fa-regular fa-calendar"></i>
                    </button>

                    <span class="due-date-label ${card.dueDate ? '' : 'is-empty'}">${card.dueDate ? formatDate(card.dueDate) : 'Sem prazo'}</span>
                </div>

                <div class="user">
                    <img src="${card.avatar}" alt="Avatar">
                </div>
            </div>

            <div class="priority-popover">
                <button type="button" class="priority-option" data-priority="low">Baixa prioridade</button>
                <button type="button" class="priority-option" data-priority="medium">Média prioridade</button>
                <button type="button" class="priority-option" data-priority="high">Alta prioridade</button>
            </div>

            <input class="due-date-input" type="date" aria-label="Selecionar data" value="${card.dueDate}">
        </div>
    `;
}

function renderBoard() {
    board.innerHTML = boardState.columns.map(column => `
        <section class="kanban-column" data-column-id="${column.id}" style="--column-accent: ${column.accent};">
            <div class="kanban-title">
                <h2>${column.title}</h2>

                <button type="button" class="add-card" data-action="add-card" data-column-id="${column.id}">
                    <i class="fa-solid fa-plus"></i>
                </button>
            </div>

            <div class="kanban-cards" data-column-id="${column.id}">
                ${column.cards.map(renderCard).join('')}
            </div>
        </section>
    `).join('');

    attachInteractions();
}

function closePriorityPopovers(exceptCard = null) {
    document.querySelectorAll('.kanban-card').forEach(card => {
        if (exceptCard && card === exceptCard) {
            return;
        }

        card.classList.remove('priority-open');
    });
}

function syncCardDom(cardElement) {
    const priority = cardElement.dataset.priority || 'medium';
    const dueDate = cardElement.dataset.dueDate || '';
    const priorityText = cardElement.querySelector('.priority-text');
    const dueDateLabel = cardElement.querySelector('.due-date-label');

    cardElement.querySelector('.badge').className = `badge ${priority}`;
    priorityText.textContent = renderPriorityLabel(priority);
    dueDateLabel.textContent = dueDate ? formatDate(dueDate) : 'Sem prazo';
    dueDateLabel.classList.toggle('is-empty', !dueDate);

    const columnId = cardElement.closest('.kanban-cards')?.dataset.columnId;

    if (!columnId) {
        return;
    }

    const column = boardState.columns.find(item => item.id === columnId);

    if (!column) {
        return;
    }

    const cardState = column.cards.find(item => item.id === cardElement.dataset.cardId);

    if (!cardState) {
        return;
    }

    cardState.priority = priority;
    cardState.dueDate = dueDate;
}

function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.kanban-card:not(.dragging)')];

    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;

        if (offset < 0 && offset > closest.offset) {
            return {
                offset,
                element: child
            };
        }

        return closest;
    }, {
        offset: Number.NEGATIVE_INFINITY,
        element: null
    }).element;
}

function updateBoardStateFromDom() {
    const updatedColumns = [...board.querySelectorAll('.kanban-cards')].map(columnElement => {
        const columnId = columnElement.dataset.columnId;
        const column = boardState.columns.find(item => item.id === columnId);

        return {
            ...column,
            cards: [...columnElement.querySelectorAll('.kanban-card')].map(cardElement => ({
                id: cardElement.dataset.cardId,
                title: cardElement.querySelector('.card-title').textContent.trim(),
                priority: cardElement.dataset.priority || 'medium',
                dueDate: cardElement.dataset.dueDate || '',
                comments: Number(cardElement.querySelectorAll('.card-icons p')[0]?.textContent.trim().match(/\d+/)?.[0] || 0),
                attachments: Number(cardElement.querySelectorAll('.card-icons p')[1]?.textContent.trim().match(/\d+/)?.[0] || 0),
                avatar: cardElement.querySelector('.user img')?.getAttribute('src') || ''
            }))
        };
    });

    boardState = {
        ...boardState,
        columns: updatedColumns
    };
}

function handleAddColumn() {
    const title = window.prompt('Nome da nova coluna:');

    if (!title || !title.trim()) {
        return;
    }

    const nextIndex = boardState.columns.length;

    boardState.columns.push({
        id: createColumnId(title),
        title: title.trim(),
        accent: getNextAccent(nextIndex),
        cards: []
    });

    saveBoardState();
    renderBoard();
}

function attachInteractions() {
    document.querySelectorAll('.kanban-card').forEach(card => {
        card.addEventListener('dragstart', e => {
            closePriorityPopovers();
            e.currentTarget.classList.add('dragging');
        });

        card.addEventListener('dragend', e => {
            e.currentTarget.classList.remove('dragging');
            updateBoardStateFromDom();
            saveBoardState();
        });

        card.querySelector('.badge').addEventListener('click', e => {
            e.stopPropagation();
            const isOpen = card.classList.contains('priority-open');
            closePriorityPopovers(card);
            card.classList.toggle('priority-open', !isOpen);
        });

        card.querySelectorAll('.priority-option').forEach(option => {
            option.addEventListener('click', e => {
                e.stopPropagation();
                card.dataset.priority = option.dataset.priority;
                card.classList.remove('priority-open');
                syncCardDom(card);
                saveBoardState();
            });
        });

        card.querySelector('.due-date-trigger').addEventListener('click', e => {
            e.stopPropagation();
            const input = card.querySelector('.due-date-input');

            if (typeof input.showPicker === 'function') {
                input.showPicker();
            } else {
                input.click();
            }
        });

        card.querySelector('.due-date-input').addEventListener('change', e => {
            card.dataset.dueDate = e.currentTarget.value;
            syncCardDom(card);
            saveBoardState();
        });
    });

    document.querySelectorAll('.kanban-cards').forEach(column => {
        column.addEventListener('dragover', e => {
            e.preventDefault();

            const draggingCard = document.querySelector('.kanban-card.dragging');

            if (!draggingCard) {
                return;
            }

            column.classList.add('cards-hover');

            const afterElement = getDragAfterElement(column, e.clientY);

            if (afterElement == null) {
                column.appendChild(draggingCard);
            } else {
                column.insertBefore(draggingCard, afterElement);
            }
        });

        column.addEventListener('dragleave', e => {
            e.currentTarget.classList.remove('cards-hover');
        });

        column.addEventListener('drop', e => {
            e.currentTarget.classList.remove('cards-hover');
            updateBoardStateFromDom();
            saveBoardState();
        });
    });

}

createColumnButton.addEventListener('click', handleAddColumn);

if (!closePopoversBound) {
    document.addEventListener('click', () => {
        closePriorityPopovers();
    });

    closePopoversBound = true;
}

renderBoard();