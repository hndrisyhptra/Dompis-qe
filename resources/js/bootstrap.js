import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// CSRF untuk request XHR (upload evidence async). Form Blade tetap pakai @csrf.
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
if (csrf) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf;
    window.CSRF_TOKEN = csrf;
}
