const statTiles = document.querySelector('#stat-tiles');

function formatCurrency(value) {
    return `$${Number(value).toFixed(2)}`;
}

function formatDateTime(value) {
    return new Date(value).toLocaleString();
}

function renderStatTiles(stats) {
    statTiles.innerHTML = '';

    const tiles = [
        { label: 'Total Products', value: stats.total_products },
        { label: 'Total Categories', value: stats.total_categories },
        { label: 'Inventory Value', value: formatCurrency(stats.inventory_value) },
        { label: 'Low Stock Products', value: stats.low_stock_count },
    ];

    tiles.forEach((tile) => {
        const card = document.createElement('div');
        card.className = 'bg-white border border-gray-200 rounded-md p-4';

        const label = document.createElement('div');
        label.className = 'text-sm text-gray-600';
        label.textContent = tile.label;

        const value = document.createElement('div');
        value.className = 'text-2xl font-semibold mt-1';
        value.textContent = tile.value;

        card.append(label, value);
        statTiles.appendChild(card);
    });
}

async function fetchStats() {
    const response = await fetch('/api/dashboard', { headers: { Accept: 'application/json' } });
    renderStatTiles(await response.json());
}

function createPaginatedSection({ apiUrl, tableBodyId, paginationId, columnCount, emptyMessage, renderRow }) {
    const tableBody = document.querySelector(`#${tableBodyId}`);
    const pagination = document.querySelector(`#${paginationId}`);

    function renderTable(items) {
        tableBody.innerHTML = '';

        if (items.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = columnCount;
            cell.className = 'px-4 py-2 text-gray-600';
            cell.textContent = emptyMessage;
            row.appendChild(cell);
            tableBody.appendChild(row);
            return;
        }

        items.forEach((item) => tableBody.appendChild(renderRow(item)));
    }

    function renderPagination(meta) {
        pagination.innerHTML = '';

        const info = document.createElement('span');
        info.textContent = meta.total > 0 ? `Showing ${meta.from}-${meta.to} of ${meta.total}` : '';

        const controls = document.createElement('div');
        controls.className = 'flex items-center gap-3';

        const prevButton = document.createElement('button');
        prevButton.type = 'button';
        prevButton.textContent = 'Previous';
        prevButton.disabled = meta.current_page <= 1;
        prevButton.className = 'px-3 py-1.5 border border-gray-300 rounded-md disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100';
        prevButton.addEventListener('click', () => fetchPage(meta.current_page - 1));

        const pageLabel = document.createElement('span');
        pageLabel.textContent = `Page ${meta.current_page} of ${meta.last_page}`;

        const nextButton = document.createElement('button');
        nextButton.type = 'button';
        nextButton.textContent = 'Next';
        nextButton.disabled = meta.current_page >= meta.last_page;
        nextButton.className = 'px-3 py-1.5 border border-gray-300 rounded-md disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100';
        nextButton.addEventListener('click', () => fetchPage(meta.current_page + 1));

        controls.append(prevButton, pageLabel, nextButton);
        pagination.append(info, controls);
    }

    async function fetchPage(page = 1) {
        const response = await fetch(`${apiUrl}?page=${page}`, { headers: { Accept: 'application/json' } });
        const { data, meta } = await response.json();

        renderTable(data);
        renderPagination(meta);
    }

    fetchPage();
}

function buildCell(text, className) {
    const cell = document.createElement('td');
    cell.className = className;
    cell.textContent = text;
    return cell;
}

createPaginatedSection({
    apiUrl: '/api/dashboard/low-stock',
    tableBodyId: 'low-stock-table-body',
    paginationId: 'low-stock-pagination',
    columnCount: 4,
    emptyMessage: 'No low stock products',
    renderRow: (product) => {
        const row = document.createElement('tr');
        row.append(
            buildCell(product.name, 'px-4 py-2 border-b border-gray-200'),
            buildCell(product.sku ?? '', 'px-4 py-2 border-b border-gray-200 text-gray-600'),
            buildCell(product.category.name, 'px-4 py-2 border-b border-gray-200 text-gray-600'),
            buildCell(product.stock, 'px-4 py-2 border-b border-gray-200 text-red-600'),
        );
        return row;
    },
});

createPaginatedSection({
    apiUrl: '/api/dashboard/categories',
    tableBodyId: 'category-breakdown-table-body',
    paginationId: 'category-breakdown-pagination',
    columnCount: 2,
    emptyMessage: 'No categories',
    renderRow: (category) => {
        const row = document.createElement('tr');
        row.append(
            buildCell(category.name, 'px-4 py-2 border-b border-gray-200'),
            buildCell(category.product_count, 'px-4 py-2 border-b border-gray-200 text-gray-600'),
        );
        return row;
    },
});

createPaginatedSection({
    apiUrl: '/api/dashboard/recent-products',
    tableBodyId: 'recent-products-table-body',
    paginationId: 'recent-products-pagination',
    columnCount: 3,
    emptyMessage: 'No products',
    renderRow: (product) => {
        const row = document.createElement('tr');
        row.append(
            buildCell(product.name, 'px-4 py-2 border-b border-gray-200'),
            buildCell(product.category.name, 'px-4 py-2 border-b border-gray-200 text-gray-600'),
            buildCell(formatDateTime(product.updated_at), 'px-4 py-2 border-b border-gray-200 text-gray-600'),
        );
        return row;
    },
});

fetchStats();
