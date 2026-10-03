import { Controller } from '@hotwired/stimulus';

/*
 * CSV Import controller for handling file uploads and previewing transactions
 */
export default class extends Controller {
    static targets = [
        'uploadForm',
        'uploadButton',
        'uploadSection',
        'previewSection',
        'previewContent',
        'fileInput',
        'fileInfo'
    ];

    static values = {
        uploadUrl: String,
        importUrl: String,
        zahlungUrl: String,
        csrfToken: String
    };

    connect() {
        console.log('CSV Import controller connected');
        this.cleanupStaleFileDisplay();
    }

    cleanupStaleFileDisplay() {
        // Browser clears file input on reload, so remove any leftover display
        if (this.hasFileInputTarget && !this.fileInputTarget.files[0]) {
            const existingInfo = this.element.querySelector('.file-selected');
            if (existingInfo) {
                existingInfo.remove();
                console.log('Removed stale file selection display');
            }
        }
    }

    fileSelected(event) {
        const file = event.target.files[0];
        if (!file) return;

        console.log('File selected:', file.name);

        // Remove existing file info
        const existingInfo = this.element.querySelector('.file-selected');
        if (existingInfo) existingInfo.remove();

        // Create new file info element
        const fileInfo = document.createElement('div');
        fileInfo.className = 'file-selected text-center mt-4 mb-4 p-3 bg-green-50 border border-green-200 rounded-lg block w-full';
        fileInfo.innerHTML = `<i class="fas fa-file-csv text-green-600 mr-2"></i>Ausgewählt: <strong class="text-green-800">${file.name}</strong>`;

        // Insert before upload button
        const buttonContainer = this.element.querySelector('#button-container');
        if (buttonContainer) {
            this.uploadFormTarget.insertBefore(fileInfo, buttonContainer);
        }
    }

    async uploadCsv(event) {
        event.preventDefault();
        console.log('Form submitted');

        if (!this.fileInputTarget.files[0]) {
            alert('Bitte wählen Sie eine CSV-Datei aus.');
            return;
        }

        // Show loading state
        this.uploadButtonTarget.disabled = true;
        this.uploadButtonTarget.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Analysiere CSV...';

        try {
            const formData = new FormData(this.uploadFormTarget);
            const response = await fetch(this.uploadUrlValue, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.error) {
                alert('Fehler: ' + data.message);
                return;
            }

            // Show preview
            this.showPreview(data);
            this.previewSectionTarget.classList.remove('hidden');
            this.uploadSectionTarget.scrollIntoView({ behavior: 'smooth' });

        } catch (error) {
            alert('Fehler beim Upload: ' + error.message);
        } finally {
            this.uploadButtonTarget.disabled = false;
            this.uploadButtonTarget.innerHTML = '<i class="fas fa-upload mr-2"></i>CSV analysieren';
        }
    }

    showPreview(data) {
        this.previewContentTarget.innerHTML = `
            <!-- Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h4 class="text-sm font-medium text-blue-900">Transaktionen</h4>
                    <p class="text-2xl font-bold text-blue-600">${data.totalCount}</p>
                    <p class="text-sm text-blue-700">${data.dateFrom} - ${data.dateTo}</p>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <h4 class="text-sm font-medium text-green-900">Einnahmen</h4>
                    <p class="text-lg font-bold text-green-600">${data.incomeCount}</p>
                    <p class="text-sm text-green-700">${data.incomeAmount}</p>
                </div>
                <div class="bg-red-50 p-4 rounded-lg">
                    <h4 class="text-sm font-medium text-red-900">Ausgaben</h4>
                    <p class="text-lg font-bold text-red-600">${data.expenseCount}</p>
                    <p class="text-sm text-red-700">${data.expenseAmount}</p>
                </div>
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <h4 class="text-sm font-medium text-yellow-900">Neue Anbieter</h4>
                    <p class="text-2xl font-bold text-yellow-600">${data.newProvidersCount}</p>
                    <p class="text-sm text-yellow-700">Duplikate: ${data.duplicateCount}</p>
                </div>
            </div>

            <!-- New Providers List -->
            ${data.newProviders && data.newProviders.length > 0 ? `
            <div class="mb-6">
                <h4 class="text-md font-medium text-gray-900 mb-3">
                    Neue Dienstleister (${data.newProviders.length})
                    <span class="text-sm font-normal text-gray-600">- werden automatisch angelegt wenn aktiviert</span>
                </h4>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <ul class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        ${data.newProviders.map(provider => `
                            <li class="flex items-center text-sm text-blue-900">
                                <i class="fas fa-building text-blue-600 mr-2"></i>
                                ${this.escapeHtml(provider)}
                            </li>
                        `).join('')}
                    </ul>
                </div>
            </div>
            ` : ''}

            <!-- Preview Table -->
            <div class="mb-6">
                <h4 class="text-md font-medium text-gray-900 mb-3">Alle Transaktionen (${data.totalCount})</h4>
                <div class="overflow-x-auto max-h-96 border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Partner</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Verwendungszweck</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Betrag</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            ${data.previewTransactions.map(tx => `
                                <tr class="${tx.isDuplicate ? 'bg-yellow-50' : ''}">
                                    <td class="px-3 py-2 text-sm text-gray-900">${this.escapeHtml(tx.date)}</td>
                                    <td class="px-3 py-2 text-sm text-gray-900">${this.escapeHtml(tx.partner)}</td>
                                    <td class="px-3 py-2 text-sm text-gray-600">${this.escapeHtml(tx.purpose.substring(0, 50))}</td>
                                    <td class="px-3 py-2 text-sm text-right font-medium ${tx.amountClass}">${this.escapeHtml(tx.amount)}</td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${tx.statusClass}">
                                            ${this.escapeHtml(tx.status)}
                                        </span>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Import Form -->
            <form id="import-form" action="${this.importUrlValue}" method="POST">
                <input type="hidden" name="_token" value="${this.csrfTokenValue}">
                <input type="hidden" name="filename" value="${this.escapeHtml(data.filename)}">
                <input type="hidden" name="original_name" value="${this.escapeHtml(data.originalName)}">

                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <h4 class="text-md font-medium text-gray-900 mb-3">Import Optionen</h4>
                    <div class="space-y-3">
                        <label class="flex items-center">
                            <input type="radio" name="import_mode" value="all" checked class="mr-2">
                            <span class="text-sm">Alle Transaktionen importieren (${data.totalCount})</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="import_mode" value="new-only" class="mr-2">
                            <span class="text-sm">Nur neue Transaktionen importieren (${data.totalCount - data.duplicateCount})</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="create_providers" checked class="mr-2">
                            <span class="text-sm">Neue Dienstleister automatisch anlegen</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <a href="${this.zahlungUrlValue}"
                       class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">
                        Abbrechen
                    </a>
                    <button type="submit"
                            class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700">
                        Import starten
                    </button>
                </div>
            </form>
        `;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
