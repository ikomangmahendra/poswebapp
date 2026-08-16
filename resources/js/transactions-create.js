const productSelect = document.querySelector('#product-select');
const quantityInput = document.querySelector('#quantity-input');
const addItemButton = document.querySelector('#add-item-button');
const addItemError = document.querySelector('#add-item-error');
const cartTableBody = document.querySelector('#cart-table-body');
const cartTotal = document.querySelector('#cart-total');
const submitButton = document.querySelector('#submit-button');
const submitErrors = document.querySelector('#submit-errors');

const cart = [];

function formatCurrency(value) {
    return `$${Number(value).toFixed(2)}`;
}

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

    const option = productSelect.options[productSelect.selectedIndex];
    const productId = Number(option.value);
    const name = option.dataset.name;
    const price = Number(option.dataset.price);
    const stock = Number(option.dataset.stock);
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

    quantityInput.value = 1;
    renderCart();
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
