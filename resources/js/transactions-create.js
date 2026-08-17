import { csrfToken } from './csrf';

const productSearchForm = document.querySelector('#product-search-form');
const productSearchInput = document.querySelector('#product-search');
const productSearchResults = document.querySelector('#product-search-results');
const selectedProductBlock = document.querySelector('#selected-product');
const selectedProductLabel = document.querySelector('#selected-product-label');
const changeProductButton = document.querySelector('#change-product-button');
const quantityInput = document.querySelector('#quantity-input');
const addItemButton = document.querySelector('#add-item-button');
const addItemError = document.querySelector('#add-item-error');
const cartTableBody = document.querySelector('#cart-table-body');
const cartTotal = document.querySelector('#cart-total');
const submitButton = document.querySelector('#submit-button');
const submitErrors = document.querySelector('#submit-errors');

const SEARCH_MIN_LENGTH = 3;
const SEARCH_DEBOUNCE_MS = 300;

const cart = [];
let selectedProduct = null;
let searchDebounceTimer = null;

function formatCurrency(value) {
    return `$${Number(value).toFixed(2)}`;
}

function selectProduct(product) {
    selectedProduct = product;
    selectedProductLabel.textContent = product.sku
        ? `${product.name} (SKU: ${product.sku}) — $${product.price}, ${product.stock} in stock`
        : `${product.name} — $${product.price}, ${product.stock} in stock`;

    productSearchResults.classList.add('hidden');
    productSearchResults.innerHTML = '';
    productSearchForm.classList.add('hidden');

    selectedProductBlock.classList.remove('hidden');
    selectedProductBlock.classList.add('flex');
    quantityInput.value = 1;
    quantityInput.focus();
}

function resetProductPicker() {
    selectedProduct = null;
    selectedProductBlock.classList.add('hidden');
    selectedProductBlock.classList.remove('flex');

    productSearchForm.classList.remove('hidden');
    productSearchInput.value = '';
    productSearchResults.classList.add('hidden');
    productSearchResults.innerHTML = '';
    productSearchInput.focus();
}

function renderSearchResults(products, meta) {
    productSearchResults.innerHTML = '';

    if (products.length === 0) {
        const empty = document.createElement('li');
        empty.className = 'px-3 py-2 text-gray-500';
        empty.textContent = 'No matching products.';
        productSearchResults.appendChild(empty);
        productSearchResults.classList.remove('hidden');
        return;
    }

    products.forEach((product) => {
        const item = document.createElement('li');
        item.className = 'px-3 py-2 hover:bg-gray-100 cursor-pointer';
        item.textContent = product.sku
            ? `${product.name} (SKU: ${product.sku}) — $${product.price}, ${product.stock} in stock`
            : `${product.name} — $${product.price}, ${product.stock} in stock`;
        item.addEventListener('click', () => selectProduct(product));
        productSearchResults.appendChild(item);
    });

    if (meta.total > products.length) {
        const hint = document.createElement('li');
        hint.className = 'px-3 py-2 text-gray-400 text-xs';
        hint.textContent = `Showing ${products.length} of ${meta.total} matches — refine your search to narrow the list.`;
        productSearchResults.appendChild(hint);
    }

    productSearchResults.classList.remove('hidden');
}

async function searchProducts(term) {
    const params = new URLSearchParams({ search: term, sort: 'name', direction: 'asc' });
    const response = await fetch(`/api/products?${params}`, { headers: { Accept: 'application/json' } });
    const { data, meta } = await response.json();

    renderSearchResults(data, meta);
}

function applySearch(value) {
    if (value.length < SEARCH_MIN_LENGTH) {
        productSearchResults.classList.add('hidden');
        productSearchResults.innerHTML = '';
        return;
    }

    searchProducts(value);
}

productSearchInput.addEventListener('input', () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => applySearch(productSearchInput.value.trim()), SEARCH_DEBOUNCE_MS);
});

productSearchForm.addEventListener('submit', (event) => {
    event.preventDefault();
    clearTimeout(searchDebounceTimer);
    applySearch(productSearchInput.value.trim());
});

changeProductButton.addEventListener('click', resetProductPicker);

function showAddItemError(message) {
    addItemError.textContent = message;
    addItemError.classList.remove('hidden');
}

function clearAddItemError() {
    addItemError.classList.add('hidden');
    addItemError.textContent = '';
}

function renderCart() {
    cartTableBody.innerHTML = '';

    let total = 0;

    cart.forEach((item, index) => {
        const subtotal = item.price * item.quantity;
        total += subtotal;

        const row = document.createElement('tr');

        const nameCell = document.createElement('td');
        nameCell.className = 'px-4 py-2 border-b border-gray-200';
        nameCell.textContent = item.name;

        const quantityCell = document.createElement('td');
        quantityCell.className = 'px-4 py-2 border-b border-gray-200 text-gray-600';
        quantityCell.textContent = item.quantity;

        const priceCell = document.createElement('td');
        priceCell.className = 'px-4 py-2 border-b border-gray-200 text-gray-600';
        priceCell.textContent = formatCurrency(item.price);

        const subtotalCell = document.createElement('td');
        subtotalCell.className = 'px-4 py-2 border-b border-gray-200 text-gray-600';
        subtotalCell.textContent = formatCurrency(subtotal);

        const actionsCell = document.createElement('td');
        actionsCell.className = 'px-4 py-2 border-b border-gray-200 text-right';

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.textContent = 'Remove';
        removeButton.className = 'text-red-600 hover:underline';
        removeButton.addEventListener('click', () => {
            cart.splice(index, 1);
            renderCart();
        });

        actionsCell.appendChild(removeButton);
        row.append(nameCell, quantityCell, priceCell, subtotalCell, actionsCell);
        cartTableBody.appendChild(row);
    });

    cartTotal.textContent = formatCurrency(total);
    submitButton.disabled = cart.length === 0;
}

addItemButton.addEventListener('click', () => {
    clearAddItemError();

    if (!selectedProduct) {
        showAddItemError('Search for and select a product first.');
        return;
    }

    const productId = selectedProduct.id;
    const name = selectedProduct.name;
    const price = Number(selectedProduct.price);
    const stock = Number(selectedProduct.stock);
    const quantity = Number(quantityInput.value);

    if (!Number.isInteger(quantity) || quantity < 1) {
        showAddItemError('Enter a quantity of at least 1.');
        return;
    }

    const existing = cart.find((item) => item.productId === productId);
    const alreadyInCart = existing ? existing.quantity : 0;

    if (alreadyInCart + quantity > stock) {
        showAddItemError(`Only ${stock} in stock for "${name}" (${alreadyInCart} already added).`);
        return;
    }

    if (existing) {
        existing.quantity += quantity;
    } else {
        cart.push({ productId, name, price, stock, quantity });
    }

    renderCart();
    resetProductPicker();
});

function showSubmitErrors(messages) {
    submitErrors.innerHTML = '';
    messages.forEach((message) => {
        const paragraph = document.createElement('p');
        paragraph.textContent = message;
        submitErrors.appendChild(paragraph);
    });
    submitErrors.classList.remove('hidden');
}

submitButton.addEventListener('click', async () => {
    submitErrors.classList.add('hidden');
    submitErrors.innerHTML = '';

    const payload = {
        items: cart.map((item) => ({ product_id: item.productId, quantity: item.quantity })),
    };

    const response = await fetch('/api/transactions', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(payload),
    });

    if (response.status === 422) {
        const body = await response.json();
        const messages = body.errors ? Object.values(body.errors).flat() : [body.message];
        showSubmitErrors(messages);
        return;
    }

    const { data } = await response.json();
    window.location.href = `/transactions/${data.id}`;
});
