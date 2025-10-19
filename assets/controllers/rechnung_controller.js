// assets/controllers/rechnung_controller.js
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
        console.log("Rechnung controller connected!");
        console.log("Show URL value:", this.showUrlValue);
        this.initDatepickers();

        // Initialize DataTable
        if (window.dispatchContentUpdated) {
            window.dispatchContentUpdated();
        }

        // Check if we should auto-open a modal based on URL parameters
        this.checkAutoOpenModal();
    }

    checkAutoOpenModal() {
        const urlParams = new URLSearchParams(window.location.search);
        const modalParam = urlParams.get('modal');
        
        if (modalParam === '1') {
            // Extract Rechnung ID from URL path
            const pathParts = window.location.pathname.split('/');
            const rechnungId = pathParts[pathParts.length - 1];
            
            if (rechnungId && !isNaN(rechnungId)) {
                // Determine which modal to open based on URL
                if (window.location.pathname.includes('/edit')) {
                    this.openEditModalById(rechnungId);
                } else {
                    this.openShowModalById(rechnungId);
                }
            }
        }
    }

    async openShowModalById(rechnungId) {
        const url = this.showUrlValue.replace('RECHNUNG_ID', rechnungId);
        const modalId = 'viewRechnungModal';
        await this.showModal(url, modalId, this.viewModalContentTarget);
    }

    async openEditModalById(rechnungId) {
        const url = this.editUrlValue.replace('RECHNUNG_ID', rechnungId);
        const modalId = 'editRechnungModal';
        await this.showModal(url, modalId, this.editModalContentTarget);
    }

    async showCreateForm() {
        console.log("showCreateForm called");
        const url = this.newUrlValue;
        const modalId = 'createRechnungModal';

        await this.showModal(url, modalId, this.createModalContentTarget);
    }

    async showEditForm(event) {
        const rechnungId = event.currentTarget.dataset.rechnungId;
        const url = this.editUrlValue.replace('RECHNUNG_ID', rechnungId);
        const modalId = 'editRechnungModal';

        await this.showModal(url, modalId, this.editModalContentTarget);
    }

    async showRechnungDetails(event) {
        console.log('showRechnungDetails called');
        const rechnungId = event.currentTarget.dataset.rechnungId;
        console.log('Rechnung ID:', rechnungId);
        const url = this.editUrlValue.replace('RECHNUNG_ID', rechnungId);
        console.log('URL:', url);
        const modalId = 'editRechnungModal';
        
        // Find the content target by ID instead of using Stimulus target
        const contentTarget = document.getElementById('editRechnungModalContent');
        console.log('Content target found by ID:', contentTarget);

        await this.showModal(url, modalId, contentTarget);
    }

    async showCreateFormWithData(event) {
        console.log('showCreateFormWithData called');
        const zahlungId = event.currentTarget.dataset.zahlungId;
        const dienstleisterId = event.currentTarget.dataset.dienstleisterId;
        const betrag = event.currentTarget.dataset.betrag;
        const information = event.currentTarget.dataset.information;
        
        console.log('Pre-fill data:', { zahlungId, dienstleisterId, betrag, information });
        
        const url = this.newUrlValue;
        const modalId = 'createRechnungModal';
        
        // Find the content target by ID
        const contentTarget = document.getElementById('createRechnungModalContent');
        
        // Store the pre-fill data for use after modal loads
        this.preFillData = { zahlungId, dienstleisterId, betrag, information };
        
        await this.showModal(url, modalId, contentTarget);
    }

    setupDeleteModal(event) {
        const rechnungId = event.currentTarget.dataset.rechnungId;
        const deleteUrl = this.deleteUrlValue.replace('RECHNUNG_ID', rechnungId);

        this.deleteFormTarget.action = deleteUrl;

        setTimeout(() => {
            const modalElement = document.getElementById('deleteRechnungModal');
            if (modalElement) {
                this.openModal(modalElement);
            }
        }, 50);
    }

    // Helper method to show a modal with content fetched from URL
    async showModal(url, modalId, contentTarget) {
        try {
            console.log('showModal called with:', url, modalId);
            console.log('contentTarget:', contentTarget);
            
            // Show loading state
            contentTarget.innerHTML = '<p class="text-center text-gray-500 dark:text-gray-400">Laden...</p>';

            // Fetch content
            console.log('Fetching content...');
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            console.log('Response received:', response);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            console.log('Getting response text...');
            const html = await response.text();
            console.log('HTML received, length:', html.length);
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            console.log('Parsed document');

            // Extract form or content - try multiple selectors
            const content = doc.querySelector('form') || 
                          doc.querySelector('.container') || 
                          doc.querySelector('.grid') || 
                          doc.body;
            console.log('Content found:', content);

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

                // Pre-fill form if data is available
                if (this.preFillData && modalId === 'createRechnungModal') {
                    this.preFillCreateForm();
                }

                // Show modal
                setTimeout(() => {
                    const modalElement = document.getElementById(modalId);
                    console.log('Looking for modal with ID:', modalId);
                    console.log('Modal element found:', modalElement);
                    if (modalElement) {
                        this.openModal(modalElement);
                    } else {
                        console.error('Modal element not found:', modalId);
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

    // Pre-fill the create form with data from payment
    preFillCreateForm() {
        if (!this.preFillData) return;
        
        console.log('Pre-filling form with:', this.preFillData);
        
        // Add hidden field for zahlung_id to link the payment
        if (this.preFillData.zahlungId) {
            const form = document.querySelector('#createRechnungModalContent form');
            if (form) {
                // Remove existing hidden field if it exists
                const existingZahlungInput = form.querySelector('input[name="zahlung_id"]');
                if (existingZahlungInput) {
                    existingZahlungInput.remove();
                }
                
                // Create new hidden input for zahlung_id
                const zahlungIdInput = document.createElement('input');
                zahlungIdInput.type = 'hidden';
                zahlungIdInput.name = 'zahlung_id';
                zahlungIdInput.value = this.preFillData.zahlungId;
                form.appendChild(zahlungIdInput);
                console.log('Added hidden zahlung_id field:', this.preFillData.zahlungId);
            }
        }
        
        // Pre-fill Dienstleister dropdown
        if (this.preFillData.dienstleisterId) {
            const dienstleisterSelect = document.querySelector('#rechnung_dienstleister');
            if (dienstleisterSelect) {
                dienstleisterSelect.value = this.preFillData.dienstleisterId;
                console.log('Pre-filled dienstleister:', this.preFillData.dienstleisterId);
            }
        }
        
        // Pre-fill Betrag mit Steuern
        if (this.preFillData.betrag) {
            const betragInput = document.querySelector('#rechnung_betragMitSteuern');
            if (betragInput) {
                betragInput.value = this.preFillData.betrag;
                console.log('Pre-filled betrag:', this.preFillData.betrag);
            }
        }
        
        // Pre-fill Information
        if (this.preFillData.information) {
            const informationInput = document.querySelector('#rechnung_information');
            if (informationInput) {
                informationInput.value = this.preFillData.information;
                console.log('Pre-filled information:', this.preFillData.information);
            }
        }
        
        // Clear pre-fill data after use
        this.preFillData = null;
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