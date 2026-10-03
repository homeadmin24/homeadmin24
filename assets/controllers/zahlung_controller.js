// assets/controllers/zahlung_controller.js
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
        deleteUrl: String,
        rechnungByDienstleisterUrl: String
    };

    connect() {
        console.log("Zahlung controller connected!");
        this.initDatepickers();

        // Initialize DataTable
        if (window.dispatchContentUpdated) {
            window.dispatchContentUpdated();
        }
    }

    async showCreateForm() {
        console.log("showCreateForm called");
        const url = this.newUrlValue;
        const modalId = 'createZahlungModal';

        await this.showModal(url, modalId, this.createModalContentTarget);
    }


    async showEditForm(event) {
        // This method is now used for both "Details" button and explicit "Edit" functionality
        const zahlungId = event.currentTarget.dataset.zahlungId;
        const url = this.editUrlValue.replace('ZAHLUNG_ID', zahlungId);
        const modalId = 'editZahlungModal';

        await this.showModal(url, modalId, this.editModalContentTarget);
    }

    // Optional: You can keep this method if you want separate view functionality
    async showZahlungDetails(event) {
        // This method is now just a wrapper for showEditForm
        this.showEditForm(event);
    }

    setupDeleteModal(event) {
        const zahlungId = event.currentTarget.dataset.zahlungId;
        const deleteUrl = this.deleteUrlValue.replace('ZAHLUNG_ID', zahlungId);

        this.deleteFormTarget.action = deleteUrl;

        setTimeout(() => {
            const modalElement = document.getElementById('deleteZahlungModal');
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
            const content = doc.querySelector('form') || doc.querySelector('body > *');

            if (content) {
                // Clear container
                while (contentTarget.firstChild) {
                    contentTarget.removeChild(contentTarget.firstChild);
                }

                // Add content
                contentTarget.appendChild(content.cloneNode(true));

                // Initialize form
                this.initFormDynamics();

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

    // Basic form setup
    initFormDynamics() {
        console.log("initFormDynamics called in zahlung_controller");
        
        // Ensure the form controller is properly initialized
        const forms = document.querySelectorAll('form');
        console.log("Found forms:", forms.length);
        
        forms.forEach(form => {
            console.log("Form has data-controller?", form.hasAttribute('data-controller'));
            console.log("Form data-controller value:", form.getAttribute('data-controller'));
            
            // Check if zahlung-form controller is already present
            const existingController = form.getAttribute('data-controller');
            if (!existingController || !existingController.includes('zahlung-form')) {
                const newController = existingController ? existingController + ' zahlung-form' : 'zahlung-form';
                form.setAttribute('data-controller', newController);
                form.setAttribute('data-zahlung-form-rechnung-by-dienstleister-url-value',
                    this.rechnungByDienstleisterUrlValue);
                console.log("Added zahlung-form controller to form");
            }
        });

        // Dispatch event to notify that content has been updated
        setTimeout(() => {
            const element = document.querySelector('[data-controller="zahlung-form"]');
            if (element) {
                console.log("Dispatching content update event");
                window.dispatchEvent(new CustomEvent('content-updated'));
            }
        }, 100);
    }

    // All form dynamics are now handled by zahlung_form_controller.js
}