const apiUrl = '/api/transactions';

const tableBody = document.querySelector('#transaction-table-body');
const pagination = document.querySelector('#pagination');

const SORTABLE_COLUMNS = ['total', 'created_at'];

let currentPage = 1;
let currentSort = 'created_at';
let currentDirection = 'desc';

function formatCurrency(value) {
    return `$${Number(value).toFixed(2)}`;
}

function formatDateTime(value) {
    return new Date(value).toLocaleString();
}

async function fetchTransactions(page = 1) {
    const params = new URLSearchParams({ page, sort: currentSort, direction: currentDirection });

    const response = await fetch(`${apiUrl}?${params}`, { headers: { Accept: 'application/json' } });
    const { data, meta } = await response.json();

    currentPage = meta.current_page;
    renderTable(data);
    renderPagination(meta);
}

function renderTable(transactions) {
    tableBody.innerHTML = '';

    transactions.forEach((transaction) => {
        const row = document.createElement('tr');

        const idCell = document.createElement('td');
        idCell.className = 'px-4 py-2 border-b border-gray-200';
        idCell.textContent = transaction.id;

        const itemsCell = document.createElement('td');
        itemsCell.className = 'px-4 py-2 border-b border-gray-200 text-gray-600';
        itemsCell.textContent = transaction.items.length;

        const totalCell = document.createElement('td');
        totalCell.className = 'px-4 py-2 border-b border-gray-200 text-gray-600';
        totalCell.textContent = formatCurrency(transaction.total);

        const createdCell = document.createElement('td');
        createdCell.className = 'px-4 py-2 border-b border-gray-200 text-gray-600';
        createdCell.textContent = formatDateTime(transaction.created_at);

        const actionsCell = document.createElement('td');
        actionsCell.className = 'px-4 py-2 border-b border-gray-200 text-right';

        const viewLink = document.createElement('a');
        viewLink.textContent = 'View';
        viewLink.href = `/transactions/${transaction.id}`;
        viewLink.className = 'text-blue-600 hover:underline';

        actionsCell.appendChild(viewLink);
        row.append(idCell, itemsCell, totalCell, createdCell, actionsCell);
        tableBody.appendChild(row);
    });
}

function renderPagination(meta) {
    pagination.innerHTML = '';

    const info = document.createElement('span');
    info.textContent = meta.total > 0 ? `Showing ${meta.from}-${meta.to} of ${meta.total}` : 'No transactions';

    const controls = document.createElement('div');
    controls.className = 'flex items-center gap-3';

    const prevButton = document.createElement('button');
    prevButton.type = 'button';
    prevButton.textContent = 'Previous';
    prevButton.disabled = meta.current_page <= 1;
    prevButton.className = 'px-3 py-1.5 border border-gray-300 rounded-md disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100';
    prevButton.addEventListener('click', () => fetchTransactions(meta.current_page - 1));

    const pageLabel = document.createElement('span');
    pageLabel.textContent = `Page ${meta.current_page} of ${meta.last_page}`;

    const nextButton = document.createElement('button');
    nextButton.type = 'button';
    nextButton.textContent = 'Next';
    nextButton.disabled = meta.current_page >= meta.last_page;
    nextButton.className = 'px-3 py-1.5 border border-gray-300 rounded-md disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100';
    nextButton.addEventListener('click', () => fetchTransactions(meta.current_page + 1));

    controls.append(prevButton, pageLabel, nextButton);
    pagination.append(info, controls);
}

function updateSortIndicators() {
    SORTABLE_COLUMNS.forEach((column) => {
        const indicator = document.querySelector(`#sort-${column}-indicator`);
        indicator.textContent = column === currentSort ? (currentDirection === 'asc' ? '▲' : '▼') : '';
    });
}

SORTABLE_COLUMNS.forEach((column) => {
    document.querySelector(`#sort-${column}`).addEventListener('click', () => {
        currentDirection = currentSort === column && currentDirection === 'asc' ? 'desc' : 'asc';
        currentSort = column;
        updateSortIndicators();
        fetchTransactions(1);
    });
});

fetchTransactions();
