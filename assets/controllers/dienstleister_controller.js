// assets/controllers/dienstleister_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        "createModalContent",
        "viewModalContent",
        "editModalContent",
        "deleteForm",
        "deleteToken"
    ];

    static values = {
        newUrl: String,
        editUrl: String,
        showUrl: String,
        deleteUrl: String
    };

    connect() {
        console.log("Dienstleister controller connected!");
        this.initDatepickers();

        // Initialize DataTable
        if (window.dispatchContentUpdated) {
            window.dispatchContentUpdated();
        }
    }

    async showCreateForm() {
        console.log("showCreateForm called");
        const url = this.newUrlValue;
        const modalId = 'createDienstleisterModal';

        await this.showModal(url, modalId, this.createModalContentTarget);
    }

    async showEditForm(event) {
        const dienstleisterId = event.currentTarget.dataset.dienstleisterId;
        const url = this.editUrlValue.replace('DIENSTLEISTER_ID', dienstleisterId);
        const modalId = 'editDienstleisterModal';

        await this.showModal(url, modalId, this.editModalContentTarget);
    }

    async showDienstleisterDetails(event) {
        const dienstleisterId = event.currentTarget.dataset.dienstleisterId;
        const url = this.showUrlValue.replace('DIENSTLEISTER_ID', dienstleisterId);
        const modalId = 'viewDienstleisterModal';

        await this.showModal(url, modalId, this.viewModalContentTarget);
    }

    setupDeleteModal(event) {
        const dienstleisterId = event.currentTarget.dataset.dienstleisterId;
        const deleteUrl = this.deleteUrlValue.replace('DIENSTLEISTER_ID', dienstleisterId);

        this.deleteFormTarget.action = deleteUrl;

        setTimeout(() => {
            const modalElement = document.getElementById('deleteDienstleisterModal');
            if (modalElement) {
                this.openModal(modalElement);
            }
        }, 50);
    }

    // Helper method to show a modal with content fetched from URL
    async showModal(url, modalId, contentTarget) {
        try {
            // Show loading state
            contentTarget.innerHTML = '<p class="text-center text-gray-500 dark:text-gray-400">Laden...</p>';

            // Fetch content
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            // Extract form or content
            const content = doc.querySelector('form') || doc.querySelector('.container');

            if (content) {
                // Clear container
                while (contentTarget.firstChild) {
                    contentTarget.removeChild(contentTarget.firstChild);
                }

                // Add content
                contentTarget.appendChild(content.cloneNode(true));

                // Reinitialize components
                if (window.dispatchContentUpdated) {
                    window.dispatchContentUpdated();
                }

                // Initialize datepickers for the new content
                this.initDatepickers();

                // Show modal
                setTimeout(() => {
                    const modalElement = document.getElementById(modalId);
                    if (modalElement) {
                        this.openModal(modalElement);
                    }
                }, 50);
            } else {
                contentTarget.innerHTML = '<p class="text-red-500 text-center">Fehler beim Laden des Inhalts.</p>';
            }
        } catch (error) {
            console.error('Error loading modal content:', error);
            contentTarget.innerHTML = '<p class="text-red-500 text-center">Fehler beim Laden des Inhalts.</p>';
        }
    }

    // Helper to open a modal
    openModal(modalElement) {
        if (typeof flowbite !== 'undefined' && flowbite.Modal) {
            try {
                const modal = new flowbite.Modal(modalElement);
                modal.show();
            } catch (e) {
                this.showModalManually(modalElement);
            }
        } else {
            this.showModalManually(modalElement);
        }
    }

    showModalManually(modalElement) {
        // Add classes to show the modal
        modalElement.classList.remove('hidden');
        modalElement.setAttribute('aria-hidden', 'false');
        modalElement.setAttribute('style', 'display: flex;');
        document.body.classList.add('overflow-hidden');

        // Add backdrop
        let backdrop = document.querySelector('[modal-backdrop]');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.setAttribute('modal-backdrop', '');
            backdrop.classList.add('bg-gray-900', 'bg-opacity-50', 'dark:bg-opacity-80', 'fixed', 'inset-0', 'z-40');
            document.body.appendChild(backdrop);
        }

        // Add event listeners to close buttons
        const closeButtons = modalElement.querySelectorAll('[data-modal-hide], [data-modal-toggle]');
        closeButtons.forEach(button => {
            button.addEventListener('click', () => {
                this.closeModalManually(modalElement, backdrop);
            });
        });

        // Add click outside to close
        modalElement.addEventListener('click', (event) => {
            if (event.target === modalElement) {
                this.closeModalManually(modalElement, backdrop);
            }
        });

        // Add escape key to close
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                this.closeModalManually(modalElement, backdrop);
            }
        }, { once: true });
    }

    closeModalManually(modalElement, backdrop) {
        modalElement.classList.add('hidden');
        modalElement.setAttribute('aria-hidden', 'true');
        modalElement.removeAttribute('style');
        document.body.classList.remove('overflow-hidden');

        if (backdrop) {
            backdrop.remove();
        }
    }

    // Initialize datepickers
    initDatepickers() {
        if (typeof flatpickr === 'function') {
            const dateInputs = document.querySelectorAll('input[type="date"]');
            dateInputs.forEach(input => {
                if (!input._flatpickr) {
                    flatpickr(input, {
                        dateFormat: 'd.m.Y',
                        locale: 'de',
                        allowInput: true
                    });
                }
            });
        }
    }
}