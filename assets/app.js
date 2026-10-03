import './bootstrap.js';
import './styles/app.css';

import './bootstrap';
import 'flowbite';
import { initFlowbite } from 'flowbite';

import './data-table.js';

// Reinitialize Flowbite after Turbo navigations
document.addEventListener('turbo:load', () => {
    initFlowbite();
});

document.addEventListener('turbo:render', () => {
    initFlowbite();
});