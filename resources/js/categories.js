const apiUrl = '/api/categories';

const tableBody = document.querySelector('#category-table-body');

async function fetchCategories() {
    const response = await fetch(apiUrl, { headers: { Accept: 'application/json' } });
    const { data } = await response.json();
    renderTable(data);
}

function renderTable(categories) {
    tableBody.innerHTML = '';

    categories.forEach((category) => {
        const row = document.createElement('tr');

        const nameCell = document.createElement('td');
        nameCell.className = 'px-4 py-2 border-b border-gray-200';
        nameCell.textContent = category.name;

        const descriptionCell = document.createElement('td');
        descriptionCell.className = 'px-4 py-2 border-b border-gray-200 text-gray-600';
        descriptionCell.textContent = category.description ?? '';

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

tableBody.addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-id]');
    if (!button) return;

    if (!window.confirm('Delete this category?')) return;

    await fetch(`${apiUrl}/${button.dataset.id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
    });

    fetchCategories();
});

fetchCategories();
