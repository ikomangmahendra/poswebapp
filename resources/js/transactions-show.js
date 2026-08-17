const container = document.querySelector('[data-transaction-id]');
const transactionId = container.dataset.transactionId;

const itemTableBody = document.querySelector('#item-table-body');
const createdAtField = document.querySelector('#transaction-created-at');
const cashierField = document.querySelector('#transaction-cashier');
const totalField = document.querySelector('#transaction-total');

function formatCurrency(value) {
    return `$${Number(value).toFixed(2)}`;
}

function formatDateTime(value) {
    return new Date(value).toLocaleString();
}

async function fetchTransaction() {
    const response = await fetch(`/api/transactions/${transactionId}`, { headers: { Accept: 'application/json' } });
    const { data: transaction } = await response.json();

    createdAtField.textContent = `Created ${formatDateTime(transaction.created_at)}`;
    cashierField.textContent = `Cashier: ${transaction.user.name}`;
    totalField.textContent = formatCurrency(transaction.total);

    itemTableBody.innerHTML = '';
    transaction.items.forEach((item) => {
        const row = document.createElement('tr');

        const productCell = document.createElement('td');
        productCell.className = 'px-4 py-2 border-b border-gray-200';
        productCell.textContent = item.product.name;

        const quantityCell = document.createElement('td');
        quantityCell.className = 'px-4 py-2 border-b border-gray-200 text-gray-600';
        quantityCell.textContent = item.quantity;

        const unitPriceCell = document.createElement('td');
        unitPriceCell.className = 'px-4 py-2 border-b border-gray-200 text-gray-600';
        unitPriceCell.textContent = formatCurrency(item.unit_price);

        const subtotalCell = document.createElement('td');
        subtotalCell.className = 'px-4 py-2 border-b border-gray-200 text-gray-600';
        subtotalCell.textContent = formatCurrency(item.subtotal);

        row.append(productCell, quantityCell, unitPriceCell, subtotalCell);
        itemTableBody.appendChild(row);
    });
}

fetchTransaction();
