import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static values = {
        newUrl: String,
        editUrl: String,
        showUrl: String,
        deleteUrl: String
    }

    showCreateForm() {
        console.log('showCreateForm called with URL:', this.newUrlValue);
        this.fetchAndShowModal(this.newUrlValue, 'dokument-create-modal', 'dokument-create-content');
    }

    showEditForm(event) {
        const dokumentId = event.target.dataset.dokumentId;
        const url = this.editUrlValue.replace('DOKUMENT_ID', dokumentId);
        this.fetchAndShowModal(url, 'dokument-edit-modal', 'dokument-edit-content');
    }

    showViewModal(event) {
        const dokumentId = event.target.dataset.dokumentId;
        const url = this.showUrlValue.replace('DOKUMENT_ID', dokumentId);
        this.fetchAndShowModal(url, 'dokument-view-modal', 'dokument-view-content');
    }

    setupDeleteModal(event) {
        const dokumentId = event.target.dataset.dokumentId;
        const modal = document.getElementById('dokument-delete-modal');
        const form = document.getElementById('dokument-delete-form');
        
        // Set form action
        const deleteUrl = this.deleteUrlValue.replace('DOKUMENT_ID', dokumentId);
        form.action = deleteUrl;
        
        console.log('Setting up delete modal for document:', dokumentId);
        console.log('Delete URL:', deleteUrl);
        
        // Remove any existing event listeners
        const newForm = form.cloneNode(true);
        form.parentNode.replaceChild(newForm, form);
        
        // Add event listener to the new form
        newForm.addEventListener('submit', (e) => this.submitDeleteForm(e));
        
        this.showModal('dokument-delete-modal');
    }

    async fetchAndShowModal(url, modalId, contentId) {
        try {
            console.log('Fetching modal content from:', url);
            const response = await fetch(url);
            if (response.ok) {
                const html = await response.text();
                console.log('Modal content loaded, setting innerHTML');
                document.getElementById(contentId).innerHTML = html;
                this.showModal(modalId);
            } else {
                console.error('Failed to fetch modal content:', response.status, response.statusText);
            }
        } catch (error) {
            console.error('Error fetching modal content:', error);
        }
    }

    showModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    closeModal() {
        const modals = [
            'dokument-create-modal',
            'dokument-edit-modal', 
            'dokument-view-modal',
            'dokument-delete-modal'
        ];
        
        modals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        });
        
        document.body.style.overflow = 'auto';
    }

    async submitForm(event) {
        console.log('submitForm called!', event);
        event.preventDefault();
        const form = event.target;
        
        console.log('Form action:', form.action);
        console.log('Form method:', form.method);
        
        try {
            const formData = new FormData(form);
            console.log('Form data entries:', Array.from(formData.entries()));
            
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData
            });
            
            console.log('Response status:', response.status);
            console.log('Response URL:', response.url);
            
            if (response.ok) {
                console.log('Form submission successful');
                this.closeModal();
                // Reload the page to show updated data
                window.location.reload();
            } else {
                console.error('Form submission failed:', response.status, response.statusText);
                // Handle validation errors
                const html = await response.text();
                console.log('Error response HTML:', html.substring(0, 200) + '...');
                
                // Update the modal content with the form containing errors
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newContent = doc.body.innerHTML;
                
                // Find which modal is currently open and update its content
                if (!document.getElementById('dokument-create-modal').classList.contains('hidden')) {
                    document.getElementById('dokument-create-content').innerHTML = newContent;
                } else if (!document.getElementById('dokument-edit-modal').classList.contains('hidden')) {
                    document.getElementById('dokument-edit-content').innerHTML = newContent;
                }
            }
        } catch (error) {
            console.error('Error submitting form:', error);
        }
    }

    async submitDeleteForm(event) {
        console.log('submitDeleteForm called!', event);
        event.preventDefault();
        const form = event.target;
        
        console.log('Submitting delete form to:', form.action);
        console.log('Form element:', form);
        
        try {
            const formData = new FormData(form);
            console.log('Form data:', Array.from(formData.entries()));
            
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData
            });
            
            console.log('Delete response status:', response.status);
            
            if (response.ok) {
                console.log('Delete successful, closing modal and reloading');
                this.closeModal();
                // Reload the page to show updated data
                window.location.reload();
            } else {
                const responseText = await response.text();
                console.error('Delete failed:', response.status, responseText);
                alert('Delete failed: ' + response.status);
            }
        } catch (error) {
            console.error('Error deleting document:', error);
            alert('Error deleting document: ' + error.message);
        }
    }

    // Close modal when clicking outside
    connect() {
        document.addEventListener('click', (event) => {
            const modals = [
                'dokument-create-modal',
                'dokument-edit-modal',
                'dokument-view-modal',
                'dokument-delete-modal'
            ];
            
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal && !modal.classList.contains('hidden')) {
                    if (event.target === modal) {
                        this.closeModal();
                    }
                }
            });
        });
    }
}