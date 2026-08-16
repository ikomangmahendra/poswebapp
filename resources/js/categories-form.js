const apiUrl = '/api/categories';

const form = document.querySelector('#category-form');
const nameField = document.querySelector('#name');
const descriptionField = document.querySelector('#description');
const errorBox = document.querySelector('#form-errors');
const categoryId = form.dataset.categoryId;

function showErrors(errors) {
    errorBox.innerHTML = '';
    Object.values(errors).flat().forEach((message) => {
        const paragraph = document.createElement('p');
        paragraph.textContent = message;
        errorBox.appendChild(paragraph);
    });
    errorBox.classList.remove('hidden');
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const payload = {
        name: nameField.value,
        description: descriptionField.value || null,
    };

    const response = await fetch(categoryId ? `${apiUrl}/${categoryId}` : apiUrl, {
        method: categoryId ? 'PUT' : 'POST',
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

    window.location.href = '/categories';
});
