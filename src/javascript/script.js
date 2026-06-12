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

const accentPalette = ['#4e735b', '#6f6448', '#4d6f6c', '#7c8060', '#5c7b5d', '#7b654b', '#4a6d63'];

const attachmentInputMap = new Map();

const defaultBoard = {
    columns: [
        {
            id: 'column-1',
            title: 'Pendente',
            accent: '#4e735b',
            cards: [
                {
                    id: 'card-1',
                    title: 'Revisar documento do projeto',
                    priority: 'medium',
                    dueDate: '2026-06-18',
                    comments: 1,
                    attachments: [
                        {
                            name: 'briefing.pdf',
                            type: 'application/pdf',
                            dataUrl: ''
                        }
                    ],
                    avatar: 'src/images/avatar2.png'
                }
            ]
        },
        {
            id: 'column-2',
            title: 'Em front-end',
            accent: '#6f6448',
            cards: [
                {
                    id: 'card-2',
                    title: 'Revisar documento do projeto',
                    priority: 'high',
                    dueDate: '2026-06-19',
                    comments: 1,
                    attachments: [
                        {
                            name: 'layout.png',
                            type: 'image/png',
                            dataUrl: ''
                        }
                    ],
                    avatar: 'src/images/avatar3.png'
                }
            ]
        },
        {
            id: 'column-3',
            title: 'Em back-end',
            accent: '#4d6f6c',
            cards: [
                {
                    id: 'card-3',
                    title: 'Revisar documento do projeto',
                    priority: 'low',
                    dueDate: '2026-06-21',
                    comments: 1,
                    attachments: [],
                    avatar: 'src/images/avatar.png'
                }
            ]
        },
        {
            id: 'column-4',
            title: 'Em teste',
            accent: '#7c8060',
            cards: [
                {
                    id: 'card-4',
                    title: 'Revisar documento do projeto',
                    priority: 'high',
                    dueDate: '2026-06-22',
                    comments: 1,
                    attachments: [],
                    avatar: 'src/images/avatar.png'
                }
            ]
        },
        {
            id: 'column-5',
            title: 'Concluído',
            accent: '#5c7b5d',
            cards: [
                {
                    id: 'card-5',
                    title: 'Revisar documento do projeto',
                    priority: 'high',
                    dueDate: '2026-06-25',
                    comments: 1,
                    attachments: [],
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

function normalizeAttachments(attachments) {
    if (Array.isArray(attachments)) {
        return attachments.map(item => ({
            name: item?.name || 'anexo',
            type: item?.type || '',
            dataUrl: item?.dataUrl || ''
        }));
    }

    const count = Number(attachments) || 0;

    return Array.from({ length: count }, (_, index) => ({
        name: `anexo-${index + 1}`,
        type: '',
        dataUrl: ''
    }));
}

function getAttachmentCount(card) {
    return Array.isArray(card.attachments) ? card.attachments.length : Number(card.attachments) || 0;
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
                        attachments: normalizeAttachments(normalizedCard.attachments),
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
                    attachments: normalizeAttachments(cardState.attachments),
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
    const attachmentCount = getAttachmentCount(card);

    return `
        <div class="kanban-card" draggable="true" data-card-id="${card.id}" data-priority="${card.priority}" data-due-date="${card.dueDate}">
            <div class="card-topbar">
                <button type="button" class="badge ${card.priority}">
                    <span class="priority-text">${renderPriorityLabel(card.priority)}</span>
                    <i class="fa-solid fa-chevron-down badge-caret"></i>
                </button>

                <div class="card-actions">
                    <button type="button" class="edit-card-btn" aria-label="Editar tarefa">
                        <i class="fa-regular fa-pen-to-square"></i>
                    </button>

                    <button type="button" class="delete-card-btn" aria-label="Excluir tarefa">
                        <i class="fa-regular fa-trash-can"></i>
                    </button>
                </div>
            </div>

            <p class="card-title">${card.title}</p>

            <div class="card-footer">
                <button type="button" class="attachment-btn" aria-label="Adicionar anexo">
                    <i class="fa-solid fa-paperclip"></i>
                    <span class="attachment-count">${attachmentCount}</span>
                </button>

                <input type="file" class="attachment-input" multiple hidden>
            </div>

            <div class="priority-popover">
                <button type="button" class="priority-option" data-priority="low">Baixa prioridade</button>
                <button type="button" class="priority-option" data-priority="medium">Média prioridade</button>
                <button type="button" class="priority-option" data-priority="high">Alta prioridade</button>
            </div>
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

function saveCardTitle(cardElement, newTitle) {
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

    cardState.title = newTitle.trim() || 'Nova tarefa';
    saveBoardState();
}

function updateAttachmentCount(cardElement, count) {
    const attachmentCount = cardElement.querySelector('.attachment-count');

    if (attachmentCount) {
        attachmentCount.textContent = String(count);
    }
}

function addAttachmentsToCard(cardElement, files) {
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

    const readFile = file => new Promise(resolve => {
        const reader = new FileReader();

        reader.onload = () => {
            resolve({
                name: file.name,
                type: file.type,
                dataUrl: String(reader.result || '')
            });
        };

        reader.readAsDataURL(file);
    });

    Promise.all([...files].map(readFile)).then(attachments => {
        cardState.attachments = [...(cardState.attachments || []), ...attachments];
        saveBoardState();
        updateAttachmentCount(cardElement, cardState.attachments.length);
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

function deleteCardFromState(cardId) {
    boardState.columns = boardState.columns.map(column => ({
        ...column,
        cards: column.cards.filter(card => card.id !== cardId)
    }));
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
                title: cardElement.querySelector('.card-title')?.textContent.trim() || 'Nova tarefa',
                priority: cardElement.dataset.priority || 'medium',
                dueDate: cardElement.dataset.dueDate || '',
                comments: 0,
                attachments: boardState.columns
                    .find(item => item.id === columnId)
                    ?.cards.find(item => item.id === cardElement.dataset.cardId)
                    ?.attachments || [],
                avatar: ''
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
    // Event listeners para adicionar cards
    document.querySelectorAll('[data-action="add-card"]').forEach(button => {
        button.addEventListener('click', (e) => {
            e.stopPropagation();
            const columnId = button.dataset.columnId;
            openTaskModal(columnId);
        });
    });

    document.querySelectorAll('.kanban-card').forEach(card => {
        card.addEventListener('dragstart', e => {
            if (e.target.closest('.attachment-btn, .attachment-input, .edit-card-btn')) {
                e.preventDefault();
                return;
            }

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

        card.querySelector('.delete-card-btn').addEventListener('click', e => {
            e.stopPropagation();

            const shouldDelete = window.confirm('Deseja excluir esta tarefa?');

            if (!shouldDelete) {
                return;
            }

            deleteCardFromState(card.dataset.cardId);
            saveBoardState();
            renderBoard();
        });

        card.querySelector('.edit-card-btn').addEventListener('click', e => {
            e.stopPropagation();

            const currentTitle = card.querySelector('.card-title')?.textContent.trim() || 'Nova tarefa';
            const nextTitle = window.prompt('Editar tarefa:', currentTitle);

            if (!nextTitle || !nextTitle.trim()) {
                return;
            }

            const normalizedTitle = nextTitle.trim();
            card.querySelector('.card-title').textContent = normalizedTitle;
            saveCardTitle(card, normalizedTitle);
        });

        card.querySelector('.attachment-btn').addEventListener('click', e => {
            e.stopPropagation();
            const input = card.querySelector('.attachment-input');

            if (!attachmentInputMap.has(card.dataset.cardId)) {
                attachmentInputMap.set(card.dataset.cardId, input);
            }

            input.value = '';
            input.click();
        });

        card.querySelector('.attachment-input').addEventListener('change', e => {
            const files = e.currentTarget.files;

            if (!files || !files.length) {
                return;
            }

            addAttachmentsToCard(card, files);
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

// Menu interativo no header
const menuToggle = document.getElementById('menu-toggle');
const headerMenu = document.getElementById('header-menu');

menuToggle.addEventListener('click', (e) => {
    e.stopPropagation();
    headerMenu.classList.toggle('active');
});

document.addEventListener('click', () => {
    headerMenu.classList.remove('active');
});

// Toggle tema escuro
const themeToggle = document.getElementById('theme-toggle');
themeToggle.addEventListener('click', () => {
    document.body.classList.toggle('dark-mode');
    const isDarkMode = document.body.classList.contains('dark-mode');
    localStorage.setItem('flowtree-dark-mode', isDarkMode);
    themeToggle.querySelector('i').classList.toggle('fa-moon');
    themeToggle.querySelector('i').classList.toggle('fa-sun');
});

// Carregar preferência de tema ao iniciar
if (localStorage.getItem('flowtree-dark-mode') === 'true') {
    document.body.classList.add('dark-mode');
    const icon = themeToggle.querySelector('i');
    icon.classList.remove('fa-moon');
    icon.classList.add('fa-sun');
}

// Busca de tarefas
const searchInput = document.getElementById('search-input');
searchInput.addEventListener('input', (e) => {
    const searchTerm = e.target.value.toLowerCase();
    const cards = document.querySelectorAll('.kanban-card');
    
    cards.forEach(card => {
        const title = card.querySelector('.card-title-input')?.value.toLowerCase() || '';
        const isVisible = title.includes(searchTerm);
        card.style.display = isVisible ? 'flex' : 'none';
    });

    if (searchTerm === '') {
        cards.forEach(card => card.style.display = 'flex');
    }
});

// Modal para criar novas tarefas
const modalOverlay = document.getElementById('task-modal-overlay');
const taskForm = document.getElementById('task-form');
const modalClose = document.getElementById('modal-close');
const modalCancel = document.getElementById('modal-cancel');

let currentColumnId = null;

function generateCardId() {
    return 'card-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
}

function openTaskModal(columnId) {
    currentColumnId = columnId;
    taskForm.reset();
    document.getElementById('task-priority').value = 'medium';
    modalOverlay.classList.add('active');
    document.getElementById('task-title').focus();
}

function closeTaskModal() {
    modalOverlay.classList.remove('active');
    currentColumnId = null;
}

function createNewTask(data) {
    const column = boardState.columns.find(col => col.id === currentColumnId);
    
    if (!column) return;

    const newCard = {
        id: generateCardId(),
        title: data.title,
        priority: data.priority,
        dueDate: data.dueDate || '',
        comments: 0,
        attachments: data.attachment ? [
            {
                name: data.attachment,
                type: '',
                dataUrl: ''
            }
        ] : [],
        avatar: 'src/images/avatar2.png'
    };

    column.cards.push(newCard);
    saveBoardState();
    renderBoard();
    closeTaskModal();
}

// Event listeners do modal
modalClose.addEventListener('click', closeTaskModal);
modalCancel.addEventListener('click', closeTaskModal);

modalOverlay.addEventListener('click', (e) => {
    if (e.target === modalOverlay) {
        closeTaskModal();
    }
});

taskForm.addEventListener('submit', (e) => {
    e.preventDefault();
    
    const title = document.getElementById('task-title').value.trim();
    const priority = document.getElementById('task-priority').value;
    const dueDate = document.getElementById('task-date').value;
    const attachment = document.getElementById('task-attachment').value.trim();

    if (!title) {
        alert('Por favor, digite um título para a tarefa');
        return;
    }

    createNewTask({
        title,
        priority,
        dueDate,
        attachment
    });
});

// Evento para o botão "+" em cada coluna

renderBoard();