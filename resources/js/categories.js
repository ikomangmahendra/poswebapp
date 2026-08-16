const apiUrl = '/api/categories';

const tableBody = document.querySelector('#category-table-body');
const form = document.querySelector('#category-form');
const formTitle = document.querySelector('#form-title');
const cancelEditButton = document.querySelector('#cancel-edit');
const idField = document.querySelector('#category-id');
const nameField = document.querySelector('#name');
const descriptionField = document.querySelector('#description');
const errorBox = document.querySelector('#form-errors');

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

        const editButton = document.createElement('button');
        editButton.type = 'button';
        editButton.textContent = 'Edit';
        editButton.dataset.id = category.id;
        editButton.dataset.action = 'edit';
        editButton.className = 'edit-btn text-blue-600 hover:underline';

        const deleteButton = document.createElement('button');
        deleteButton.type = 'button';
        deleteButton.textContent = 'Delete';
        deleteButton.dataset.id = category.id;
        deleteButton.dataset.action = 'delete';
        deleteButton.className = 'delete-btn text-red-600 hover:underline';

        actionsCell.append(editButton, deleteButton);
        row.append(nameCell, descriptionCell, actionsCell);
        tableBody.appendChild(row);
    });
}

function showErrors(errors) {
    errorBox.innerHTML = '';
    Object.values(errors).flat().forEach((message) => {
        const paragraph = document.createElement('p');
        paragraph.textContent = message;
        errorBox.appendChild(paragraph);
    });
    errorBox.classList.remove('hidden');
}

function resetForm() {
    form.reset();
    idField.value = '';
    formTitle.textContent = 'Add Category';
    cancelEditButton.classList.add('hidden');
    errorBox.classList.add('hidden');
    errorBox.innerHTML = '';
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const id = idField.value;
    const payload = {
        name: nameField.value,
        description: descriptionField.value || null,
    };

    const response = await fetch(id ? `${apiUrl}/${id}` : apiUrl, {
        method: id ? 'PUT' : 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
        },
        body: JSON.stringify(payload),
    });

    if (response.status === 422) {
        const { errors } = await response.json();
        showErrors(errors);
        return;
    }

    resetForm();
    fetchCategories();
});

tableBody.addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-id]');
    if (!button) return;

    const id = button.dataset.id;

    if (button.dataset.action === 'edit') {
        const response = await fetch(`${apiUrl}/${id}`, { headers: { Accept: 'application/json' } });
        const { data } = await response.json();

        idField.value = data.id;
        nameField.value = data.name;
        descriptionField.value = data.description ?? '';
        formTitle.textContent = 'Edit Category';
        cancelEditButton.classList.remove('hidden');
    }

    if (button.dataset.action === 'delete') {
        if (!window.confirm('Delete this category?')) return;

        await fetch(`${apiUrl}/${id}`, {
            method: 'DELETE',
            headers: { Accept: 'application/json' },
        });

        fetchCategories();
    }
});

cancelEditButton.addEventListener('click', resetForm);

fetchCategories();
