import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["modal"]

    connect() {
        // Bind modal close events
        document.addEventListener('click', this.handleOutsideClick.bind(this))
        document.addEventListener('keydown', this.handleEscapeKey.bind(this))
    }

    disconnect() {
        document.removeEventListener('click', this.handleOutsideClick.bind(this))
        document.removeEventListener('keydown', this.handleEscapeKey.bind(this))
    }

    async showPreview(event) {
        const dokumentId = event.currentTarget.dataset.dokumentId
        
        try {
            // Show loading state
            this.showModal()
            this.showLoadingState()
            
            // Fetch CSV preview data with timeout
            const controller = new AbortController()
            const timeoutId = setTimeout(() => controller.abort(), 30000) // 30 second timeout
            
            const response = await fetch(`/dokument/${dokumentId}/csv-preview`, {
                signal: controller.signal
            })
            
            clearTimeout(timeoutId)
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`)
            }
            
            const data = await response.json()
            
            if (data.error) {
                this.showError(data.message)
                return
            }
            
            // Update modal content
            this.updatePreviewData(data, dokumentId)
            
        } catch (error) {
            console.error('Error loading CSV preview:', error)
            if (error.name === 'AbortError') {
                this.showError('Timeout: CSV-Analyse dauert zu lange (> 30 Sekunden)')
            } else {
                this.showError('Fehler beim Laden der CSV-Vorschau: ' + error.message)
            }
        }
    }
    
    showModal() {
        const modal = document.getElementById('csvPreviewModal')
        if (modal) {
            modal.classList.remove('hidden')
            document.body.style.overflow = 'hidden'
        }
    }
    
    hideModal() {
        const modal = document.getElementById('csvPreviewModal')
        if (modal) {
            modal.classList.add('hidden')
            document.body.style.overflow = 'auto'
        }
    }

    showLoadingState() {
        const content = document.getElementById('modal-content')
        if (content) {
            content.innerHTML = `
                <div class="flex items-center justify-center p-8">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-600"></div>
                    <span class="ml-3 text-gray-600">CSV wird analysiert...</span>
                </div>
            `
        }
    }

    showError(message) {
        const content = document.getElementById('modal-content')
        if (content) {
            content.innerHTML = `
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex">
                        <i class="fas fa-exclamation-triangle text-red-500 mt-1"></i>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">Fehler</h3>
                            <p class="mt-2 text-sm text-red-700">${message}</p>
                        </div>
                    </div>
                </div>
                <div class="mt-4 flex justify-end">
                    <button type="button" onclick="this.closest('.fixed').classList.add('hidden')" 
                            class="bg-gray-200 text-gray-700 px-4 py-2 rounded">
                        Schließen
                    </button>
                </div>
            `
        }
    }
    
    updatePreviewData(data, dokumentId) {
        // Update summary cards
        this.updateElement('transaction-count', data.totalCount)
        this.updateElement('date-range', `${data.dateFrom} - ${data.dateTo}`)
        this.updateElement('income-count', data.incomeCount)
        this.updateElement('income-amount', data.incomeAmount)
        this.updateElement('expense-count', data.expenseCount)
        this.updateElement('expense-amount', data.expenseAmount)
        this.updateElement('new-providers-count', data.newProvidersCount)
        this.updateElement('duplicate-count', data.duplicateCount)
        this.updateElement('all-count', data.totalCount)
        this.updateElement('new-count', data.totalCount - data.duplicateCount)
        
        // Update table
        this.updatePreviewTable(data.previewTransactions)
        
        // Setup import form
        this.setupImportForm(dokumentId)
    }
    
    updateElement(id, value) {
        const element = document.getElementById(id)
        if (element) {
            element.textContent = value
        }
    }
    
    updatePreviewTable(transactions) {
        const tbody = document.getElementById('preview-table-body')
        if (!tbody) return
        
        tbody.innerHTML = ''
        
        transactions.forEach(transaction => {
            const row = this.createTableRow(transaction)
            tbody.appendChild(row)
        })
    }
    
    createTableRow(transaction) {
        const row = document.createElement('tr')
        row.className = transaction.isDuplicate ? 'bg-yellow-50' : ''
        
        row.innerHTML = `
            <td class="px-3 py-2 text-sm text-gray-900">${transaction.date}</td>
            <td class="px-3 py-2 text-sm">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${transaction.typeClass}">
                    ${this.truncateText(transaction.type, 12)}
                </span>
            </td>
            <td class="px-3 py-2 text-sm text-gray-900 truncate max-w-32" title="${transaction.partner}">${this.truncateText(transaction.partner, 20)}</td>
            <td class="px-3 py-2 text-sm text-gray-600 truncate max-w-48" title="${transaction.purpose}">${transaction.purpose}</td>
            <td class="px-3 py-2 text-sm text-right font-medium ${transaction.amountClass}">${transaction.amount}</td>
            <td class="px-3 py-2 text-center">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${transaction.statusClass}">
                    ${transaction.status}
                </span>
            </td>
        `
        
        return row
    }

    truncateText(text, maxLength) {
        if (text.length <= maxLength) return text
        return text.substring(0, maxLength - 3) + '...'
    }

    setupImportForm(dokumentId) {
        const confirmButton = document.getElementById('confirm-import')
        const cancelButton = document.getElementById('cancel-import')

        if (confirmButton) {
            confirmButton.onclick = () => this.handleImport(dokumentId)
        }

        if (cancelButton) {
            cancelButton.onclick = () => this.hideModal()
        }
    }

    async handleImport(dokumentId) {
        const importMode = document.querySelector('input[name="import-mode"]:checked')?.value || 'all'
        const createProviders = document.getElementById('create-providers')?.checked || true

        const formData = new FormData()
        const csrfToken = document.getElementById('import-csrf-token')?.value || ''
        formData.append('_token', csrfToken)
        formData.append('import_mode', importMode)
        formData.append('create_providers', createProviders ? '1' : '0')

        try {
            const response = await fetch(`/dokument/${dokumentId}/import-payments`, {
                method: 'POST',
                body: formData
            })

            if (response.redirected) {
                // Follow the redirect (usually to zahlung index)
                window.location.href = response.url
            } else {
                // Handle error case
                const data = await response.text()
                this.showError('Import fehlgeschlagen')
            }
        } catch (error) {
            console.error('Import error:', error)
            this.showError('Import fehlgeschlagen: ' + error.message)
        }
    }

    async getCsrfToken(tokenId) {
        // Get CSRF token from Symfony's built-in function
        try {
            const response = await fetch('/token/' + tokenId)
            if (response.ok) {
                return await response.text()
            }
        } catch (error) {
            console.warn('Could not fetch CSRF token, using fallback')
        }
        
        // Fallback: try to find token in DOM
        const tokenElement = document.querySelector(`meta[name="csrf-token-${tokenId}"]`)
        if (tokenElement) {
            return tokenElement.getAttribute('content')
        }
        
        // Last resort: generate random token (not secure, but prevents errors)
        return Math.random().toString(36).substring(2, 15)
    }

    handleOutsideClick(event) {
        const modal = document.getElementById('csvPreviewModal')
        if (modal && event.target === modal) {
            this.hideModal()
        }
    }

    handleEscapeKey(event) {
        if (event.key === 'Escape') {
            this.hideModal()
        }
    }
}