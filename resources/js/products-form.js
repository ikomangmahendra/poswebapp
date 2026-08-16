import { csrfToken } from './csrf';

const apiUrl = '/api/products';

const form = document.querySelector('#product-form');
const categoryField = document.querySelector('#category_id');
const nameField = document.querySelector('#name');
const skuField = document.querySelector('#sku');
const priceField = document.querySelector('#price');
const stockField = document.querySelector('#stock');
const errorBox = document.querySelector('#form-errors');
const productId = form.dataset.productId;

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
        category_id: categoryField.value,
        name: nameField.value,
        sku: skuField.value || null,
        price: priceField.value,
        stock: stockField.value || 0,
    };

    const response = await fetch(productId ? `${apiUrl}/${productId}` : apiUrl, {
        method: productId ? 'PUT' : 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(payload),
    });

    if (response.status === 422) {
        const { errors } = await response.json();
        showErrors(errors);
        return;
    }

    window.location.href = '/products';
});
