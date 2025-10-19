// assets/controllers/modal_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["container", "content"];

    static values = {
        url: String
    };

    async show() {
        if (this.urlValue) {
            await this.fetchContent();
        }

        this.openModal();
    }

    async fetchContent() {
        try {
            this.contentTarget.innerHTML = '<p class="text-center text-gray-500 dark:text-gray-400">Laden...</p>';

            const response = await fetch(this.urlValue);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            // Extract form or content
            const content = doc.querySelector('form') || doc.querySelector('body > *');

            if (content) {
                // Clear content container
                while (this.contentTarget.firstChild) {
                    this.contentTarget.removeChild(this.contentTarget.firstChild);
                }

                // Add new content
                this.contentTarget.appendChild(content.cloneNode(true));

                // Dispatch event for other controllers
                window.dispatchContentUpdated();
            } else {
                this.contentTarget.innerHTML = '<p class="text-red-500 text-center">Fehler beim Laden des Inhalts.</p>';
            }
        } catch (error) {
            console.error('Error fetching content:', error);
            this.contentTarget.innerHTML = '<p class="text-red-500 text-center">Fehler beim Laden des Inhalts.</p>';
        }
    }

    openModal() {
        if (typeof flowbite !== 'undefined' && flowbite.Modal) {
            try {
                const modal = new flowbite.Modal(this.containerTarget);
                modal.show();
            } catch (e) {
                console.error("Could not show modal via Flowbite:", e);
                this.openModalManually();
            }
        } else {
            this.openModalManually();
        }
    }

    openModalManually() {
        this.containerTarget.classList.remove('hidden');
        this.containerTarget.setAttribute('aria-hidden', 'false');
        this.containerTarget.setAttribute('style', 'display: flex;');
        document.body.classList.add('overflow-hidden');

        // Add backdrop
        let backdrop = document.querySelector('[modal-backdrop]');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.setAttribute('modal-backdrop', '');
            backdrop.classList.add('bg-gray-900', 'bg-opacity-50', 'dark:bg-opacity-80', 'fixed', 'inset-0', 'z-40');
            document.body.appendChild(backdrop);
        }

        // Set up close handlers
        this.setupCloseHandlers(backdrop);
    }

    setupCloseHandlers(backdrop) {
        // Close buttons
        const closeButtons = this.containerTarget.querySelectorAll('[data-modal-hide], [data-modal-toggle]');
        closeButtons.forEach(button => {
            button.addEventListener('click', () => this.closeModal(backdrop));
        });

        // Click outside
        this.containerTarget.addEventListener('click', (event) => {
            if (event.target === this.containerTarget) {
                this.closeModal(backdrop);
            }
        });

        // Escape key
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                this.closeModal(backdrop);
            }
        }, { once: true });
    }

    closeModal(backdrop) {
        this.containerTarget.classList.add('hidden');
        this.containerTarget.setAttribute('aria-hidden', 'true');
        this.containerTarget.removeAttribute('style');
        document.body.classList.remove('overflow-hidden');

        if (backdrop) {
            backdrop.remove();
        }
    }
}