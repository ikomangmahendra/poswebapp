import { csrfToken } from './csrf';

const apiUrl = '/api/users';

const form = document.querySelector('#user-form');
const nameField = document.querySelector('#name');
const emailField = document.querySelector('#email');
const passwordField = document.querySelector('#password');
const passwordConfirmationField = document.querySelector('#password_confirmation');
const errorBox = document.querySelector('#form-errors');
const userId = form.dataset.userId;

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
        email: emailField.value,
    };

    if (passwordField.value) {
        payload.password = passwordField.value;
        payload.password_confirmation = passwordConfirmationField.value;
    }

    const response = await fetch(userId ? `${apiUrl}/${userId}` : apiUrl, {
        method: userId ? 'PUT' : 'POST',
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

    window.location.href = '/users';
});
