import { csrfToken } from './csrf';

const apiUrl = '/api/categories';

const tableBody = document.querySelector('#category-table-body');
const pagination = document.querySelector('#pagination');
const searchForm = document.querySelector('#search-form');
const searchField = document.querySelector('#search');
const sortNameButton = document.querySelector('#sort-name');
const sortNameIndicator = document.querySelector('#sort-name-indicator');

const DESCRIPTION_MAX_LENGTH = 100;
const SEARCH_MIN_LENGTH = 3;
const SEARCH_DEBOUNCE_MS = 300;

let currentPage = 1;
let currentSearch = '';
let searchDebounceTimer = null;
let currentSort = 'updated_at';
let currentDirection = 'desc';

function truncate(text, maxLength) {
    return text.length > maxLength ? `${text.slice(0, maxLength)}...` : text;
}

async function fetchCategories(page = 1) {
    const params = new URLSearchParams({ page, sort: currentSort, direction: currentDirection });
    if (currentSearch) {
        params.set('search', currentSearch);
    }

    const response = await fetch(`${apiUrl}?${params}`, { headers: { Accept: 'application/json' } });
    const { data, meta } = await response.json();

    currentPage = meta.current_page;
    renderTable(data);
    renderPagination(meta);
}

function renderTable(categories) {
    tableBody.innerHTML = '';

    categories.forEach((category) => {
        const row = document.createElement('tr');

        const nameCell = document.createElement('td');
        nameCell.className = 'px-4 py-2 border-b border-gray-200';
        nameCell.textContent = category.name;

        const description = category.description ?? '';
        const descriptionCell = document.createElement('td');
        descriptionCell.className = 'px-4 py-2 border-b border-gray-200 text-gray-600';
        descriptionCell.textContent = truncate(description, DESCRIPTION_MAX_LENGTH);
        if (description.length > DESCRIPTION_MAX_LENGTH) {
            descriptionCell.title = description;
        }

        const actionsCell = document.createElement('td');
        actionsCell.className = 'px-4 py-2 border-b border-gray-200 text-right space-x-3';

        const editLink = document.createElement('a');
        editLink.textContent = 'Edit';
        editLink.href = `/categories/${category.id}/edit`;
        editLink.className = 'text-blue-600 hover:underline';

        const deleteButton = document.createElement('button');
        deleteButton.type = 'button';
        deleteButton.textContent = 'Delete';
        deleteButton.dataset.id = category.id;
        deleteButton.className = 'delete-btn text-red-600 hover:underline';

        actionsCell.append(editLink, deleteButton);
        row.append(nameCell, descriptionCell, actionsCell);
        tableBody.appendChild(row);
    });
}

function renderPagination(meta) {
    pagination.innerHTML = '';

    const info = document.createElement('span');
    info.textContent = meta.total > 0
        ? `Showing ${meta.from}-${meta.to} of ${meta.total}`
        : currentSearch ? 'No categories match your search' : 'No categories';

    const controls = document.createElement('div');
    controls.className = 'flex items-center gap-3';

    const prevButton = document.createElement('button');
    prevButton.type = 'button';
    prevButton.textContent = 'Previous';
    prevButton.disabled = meta.current_page <= 1;
    prevButton.className = 'px-3 py-1.5 border border-gray-300 rounded-md disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100';
    prevButton.addEventListener('click', () => fetchCategories(meta.current_page - 1));

    const pageLabel = document.createElement('span');
    pageLabel.textContent = `Page ${meta.current_page} of ${meta.last_page}`;

    const nextButton = document.createElement('button');
    nextButton.type = 'button';
    nextButton.textContent = 'Next';
    nextButton.disabled = meta.current_page >= meta.last_page;
    nextButton.className = 'px-3 py-1.5 border border-gray-300 rounded-md disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100';
    nextButton.addEventListener('click', () => fetchCategories(meta.current_page + 1));

    controls.append(prevButton, pageLabel, nextButton);
    pagination.append(info, controls);
}

tableBody.addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-id]');
    if (!button) return;

    if (!window.confirm('Delete this category?')) return;

    await fetch(`${apiUrl}/${button.dataset.id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    });

    fetchCategories(currentPage);
});

function applySearch(value) {
    if (value.length > 0 && value.length < SEARCH_MIN_LENGTH) {
        return;
    }

    currentSearch = value;
    fetchCategories(1);
}

searchField.addEventListener('input', () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => applySearch(searchField.value.trim()), SEARCH_DEBOUNCE_MS);
});

searchForm.addEventListener('submit', (event) => {
    event.preventDefault();
    clearTimeout(searchDebounceTimer);
    applySearch(searchField.value.trim());
});

function updateSortIndicator() {
    sortNameIndicator.textContent = currentSort === 'name' ? (currentDirection === 'asc' ? '▲' : '▼') : '';
}

sortNameButton.addEventListener('click', () => {
    currentDirection = currentSort === 'name' && currentDirection === 'asc' ? 'desc' : 'asc';
    currentSort = 'name';
    updateSortIndicator();
    fetchCategories(1);
});

fetchCategories();
