import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["einheitenContainer", "submitButton", "wegSelect"]
    static values = { 
        updateEinheitenUrl: String 
    }

    connect() {
        console.log("Abrechnung controller connected")
        this.updateSubmitButtonState()
        
        // Check if a WEG is already selected on page load
        if (this.wegSelectTarget && this.wegSelectTarget.value) {
            // Trigger einheiten loading for preselected WEG
            this.updateEinheiten({ target: this.wegSelectTarget })
        }
    }

    updateEinheiten(event) {
        const wegId = event.target.value
        
        if (!wegId) {
            this.clearEinheiten()
            return
        }

        // Show loading state
        this.showLoading()

        // Fetch einheiten for selected WEG
        fetch(`/abrechnung/einheiten/${wegId}`)
            .then(response => response.json())
            .then(data => {
                this.populateEinheiten(data.einheiten)
                this.updateSubmitButtonState()
            })
            .catch(error => {
                console.error('Error fetching einheiten:', error)
                this.showError('Fehler beim Laden der Einheiten')
            })
            .finally(() => {
                this.hideLoading()
            })
    }

    populateEinheiten(einheiten) {
        const container = this.einheitenContainerTarget
        
        // Create the complete section with label and checkboxes
        let html = `
            <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Einheiten</label>
        `
        
        einheiten.forEach((einheit, index) => {
            html += `
                <div class="flex items-center mb-2">
                    <input type="checkbox" 
                           id="abrechnung_generate_einheiten_${index}" 
                           name="abrechnung_generate[einheiten][]" 
                           value="${einheit.id}"
                           class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600">
                    <label for="abrechnung_generate_einheiten_${index}" 
                           class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                        ${einheit.nummer} ${einheit.bezeichnung} - ${einheit.miteigentuemer}
                    </label>
                </div>
            `
        })
        
        // Add submit button after checkboxes
        html += `
            <div class="flex items-center space-x-4 mt-4">
                <button type="submit" 
                        class="text-white bg-blue-700 font-medium rounded-lg text-sm px-5 py-2.5 hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 disabled:opacity-50 disabled:cursor-not-allowed"
                        data-abrechnung-target="submitButton"
                        disabled>
                    Mindestens eine Einheit auswählen
                </button>
            </div>
        `
        
        container.innerHTML = html

        // Add event listeners for submit button state
        this.addCheckboxListeners()
    }

    addCheckboxListeners() {
        const checkboxes = this.einheitenContainerTarget.querySelectorAll('input[type="checkbox"]')
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                this.updateSubmitButtonState()
            })
        })
    }

    updateSubmitButtonState() {
        const checkboxes = this.einheitenContainerTarget.querySelectorAll('input[type="checkbox"]:checked')
        const submitButton = this.einheitenContainerTarget.querySelector('[data-abrechnung-target="submitButton"]')
        
        if (!submitButton) return // No submit button yet
        
        if (checkboxes.length === 0) {
            submitButton.disabled = true
            submitButton.classList.add('opacity-50', 'cursor-not-allowed')
            submitButton.textContent = 'Mindestens eine Einheit auswählen'
        } else {
            submitButton.disabled = false
            submitButton.classList.remove('opacity-50', 'cursor-not-allowed')
            submitButton.innerHTML = `🔄 Abrechnungen Generieren (${checkboxes.length})`
        }
    }

    clearEinheiten() {
        this.einheitenContainerTarget.innerHTML = ''
        this.updateSubmitButtonState()
    }

    showLoading() {
        this.einheitenContainerTarget.innerHTML = '<p class="text-gray-500 text-sm">Lade Einheiten...</p>'
    }

    hideLoading() {
        // Loading state will be replaced by populateEinheiten or error
    }

    showError(message) {
        this.einheitenContainerTarget.innerHTML = `<p class="text-red-500 text-sm">${message}</p>`
    }

}