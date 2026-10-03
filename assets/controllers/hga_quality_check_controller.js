import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = [
        "resultsModal",
        "resultsContent",
        "resultsFooter",
        "loadingState",
        "feedbackModal",
        "feedbackType",
        "feedbackDescription"
    ]

    connect() {
        console.log('HGA Quality Check Controller connected!');
        this.currentProvider = null;
        this.currentResults = null;
        this.currentDokumentId = null;
    }

    async checkWithOllama(event) {
        event.preventDefault();
        this.currentDokumentId = event.currentTarget.dataset.dokumentId;
        this.currentProvider = 'ollama';
        await this.runQualityCheck('ollama');
    }

    async checkWithClaude(event) {
        event.preventDefault();
        this.currentDokumentId = event.currentTarget.dataset.dokumentId;
        this.currentProvider = 'claude';
        await this.runQualityCheck('claude');
    }

    async runQualityCheck(provider) {
        // Show modal with loading state
        this.showResults();
        this.loadingStateTarget.classList.remove('hidden');
        this.resultsFooterTarget.style.display = 'none';

        try {
            const formData = new FormData();
            formData.append('provider', provider);

            const response = await fetch(`/dokument/${this.currentDokumentId}/quality-check`, {
                method: 'POST',
                body: formData
            });

            // Log response for debugging
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);

            const responseText = await response.text();
            console.log('Response body:', responseText);

            if (!response.ok) {
                // Try to parse JSON error
                let errorMsg = `HTTP error! status: ${response.status}`;
                try {
                    const errorData = JSON.parse(responseText);
                    errorMsg = errorData.error || errorMsg;
                } catch (e) {
                    // Not JSON, use text response
                    errorMsg = responseText || errorMsg;
                }
                throw new Error(errorMsg);
            }

            const data = JSON.parse(responseText);
            this.currentResults = data;

            // Hide loading, show results
            this.loadingStateTarget.classList.add('hidden');
            this.displayResults(data, provider);
            this.resultsFooterTarget.style.display = 'flex';

        } catch (error) {
            console.error('Quality check failed:', error);
            this.loadingStateTarget.classList.add('hidden');
            this.displayError(error.message);
        }
    }

    displayResults(data, provider) {
        const statusColors = {
            'pass': 'green',
            'warning': 'yellow',
            'critical': 'red'
        };

        const statusIcons = {
            'pass': 'check-circle',
            'warning': 'exclamation-triangle',
            'critical': 'times-circle'
        };

        const color = statusColors[data.status] || 'gray';
        const icon = statusIcons[data.status] || 'question-circle';

        let html = `
            <!-- Overall Status -->
            <div class="bg-${color}-50 dark:bg-${color}-900/20 border border-${color}-200 dark:border-${color}-800 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <i class="fas fa-${icon} text-${color}-600 dark:text-${color}-400 text-2xl mr-3"></i>
                    <div>
                        <h4 class="text-lg font-semibold text-${color}-900 dark:text-${color}-100">
                            ${this.getStatusText(data.status)}
                        </h4>
                        <p class="text-sm text-${color}-700 dark:text-${color}-300">
                            Geprüft mit: ${provider === 'ollama' ? 'Ollama (lokal)' : 'Claude (Cloud)'}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Rule-Based Checks -->
            <div class="mb-6">
                <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                    <i class="fas fa-list-check mr-2"></i>Regelbasierte Prüfungen
                </h4>
                ${this.renderChecks(data.checks)}
            </div>

            <!-- AI Analysis -->
            ${data.ai_analysis ? this.renderAIAnalysis(data.ai_analysis) : ''}

            <!-- User Feedback Injected -->
            ${data.user_feedback_injected > 0 ? `
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        <i class="fas fa-info-circle mr-2"></i>
                        ${data.user_feedback_injected} frühere Benutzermeldungen wurden in die KI-Analyse einbezogen
                    </p>
                </div>
            ` : ''}
        `;

        this.resultsContentTarget.innerHTML = html;
    }

    getStatusText(status) {
        const texts = {
            'pass': 'Keine kritischen Probleme gefunden',
            'warning': 'Warnungen gefunden - Bitte prüfen',
            'critical': 'Kritische Probleme gefunden!'
        };
        return texts[status] || 'Status unbekannt';
    }

    renderChecks(checks) {
        if (!checks || checks.length === 0) {
            return '<p class="text-gray-500 dark:text-gray-400">Keine Prüfungen durchgeführt</p>';
        }

        const severityColors = {
            'critical': 'red',
            'warning': 'yellow',
            'info': 'blue'
        };

        const severityIcons = {
            'critical': 'times-circle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };

        return checks.map(check => {
            const color = severityColors[check.severity] || 'gray';
            const icon = severityIcons[check.severity] || 'check-circle';
            const passed = check.status === 'pass';

            return `
                <div class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-4 mb-3">
                    <div class="flex items-start">
                        <i class="fas fa-${passed ? 'check-circle text-green-600' : icon + ' text-' + color + '-600'} dark:text-${passed ? 'green' : color}-400 mt-1 mr-3"></i>
                        <div class="flex-1">
                            <h5 class="font-medium text-gray-900 dark:text-white mb-1">${check.category}</h5>
                            <p class="text-sm text-gray-600 dark:text-gray-400">${check.message}</p>
                            ${check.details && Object.keys(check.details).length > 0 ? `
                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-500">
                                    ${JSON.stringify(check.details)}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    renderAIAnalysis(aiAnalysis) {
        if (!aiAnalysis) return '';

        return `
            <div class="mb-6">
                <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                    <i class="fas fa-brain mr-2"></i>KI-Analyse
                </h4>

                ${aiAnalysis.summary ? `
                    <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg p-4 mb-4">
                        <p class="text-sm text-purple-900 dark:text-purple-100">${aiAnalysis.summary}</p>
                        ${aiAnalysis.confidence ? `
                            <div class="mt-2 flex items-center">
                                <span class="text-xs text-purple-700 dark:text-purple-300 mr-2">Konfidenz:</span>
                                <div class="flex-1 bg-purple-200 dark:bg-purple-800 rounded-full h-2 max-w-xs">
                                    <div class="bg-purple-600 dark:bg-purple-400 h-2 rounded-full" style="width: ${aiAnalysis.confidence * 100}%"></div>
                                </div>
                                <span class="text-xs text-purple-700 dark:text-purple-300 ml-2">${Math.round(aiAnalysis.confidence * 100)}%</span>
                            </div>
                        ` : ''}
                    </div>
                ` : ''}

                ${aiAnalysis.issues_found && aiAnalysis.issues_found.length > 0 ? `
                    <div class="space-y-3">
                        ${aiAnalysis.issues_found.map(issue => {
                            const severityColors = {
                                'critical': 'red',
                                'high': 'orange',
                                'medium': 'yellow',
                                'low': 'blue'
                            };
                            const color = severityColors[issue.severity] || 'yellow';

                            return `
                            <div class="bg-${color}-50 dark:bg-${color}-900/20 border border-${color}-200 dark:border-${color}-800 rounded-lg p-4">
                                <div class="flex items-start mb-2">
                                    <i class="fas fa-exclamation-triangle text-${color}-600 dark:text-${color}-400 mt-1 mr-2"></i>
                                    <div class="flex-1">
                                        <h6 class="font-medium text-${color}-900 dark:text-${color}-100 mb-1">
                                            ${issue.issue || issue.category || 'Problem gefunden'}
                                        </h6>
                                        ${issue.details ? `
                                            <p class="text-sm text-${color}-800 dark:text-${color}-200 mb-2">
                                                ${issue.details}
                                            </p>
                                        ` : ''}
                                        ${issue.recommendation ? `
                                            <div class="mt-2 pt-2 border-t border-${color}-200 dark:border-${color}-700">
                                                <p class="text-xs text-${color}-700 dark:text-${color}-300">
                                                    <i class="fas fa-lightbulb mr-1"></i><strong>Empfehlung:</strong> ${issue.recommendation}
                                                </p>
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        `}).join('')}
                    </div>
                ` : ''}
            </div>
        `;
    }

    displayError(message) {
        this.resultsContentTarget.innerHTML = `
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-6 text-center">
                <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400 text-4xl mb-3"></i>
                <h4 class="text-lg font-semibold text-red-900 dark:text-red-100 mb-2">Fehler bei der Qualitätsprüfung</h4>
                <p class="text-sm text-red-700 dark:text-red-300">${message}</p>
            </div>
        `;
    }

    showResults() {
        this.resultsModalTarget.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    closeResults() {
        this.resultsModalTarget.classList.add('hidden');
        document.body.style.overflow = '';
        this.currentResults = null;
    }

    async rateHelpful(event) {
        event.preventDefault();
        await this.submitRating(true);
    }

    async rateNotHelpful(event) {
        event.preventDefault();
        await this.submitRating(false);
    }

    async submitRating(helpful) {
        try {
            const formData = new FormData();
            formData.append('ai_provider', this.currentProvider);
            formData.append('ai_result', JSON.stringify(this.currentResults));
            formData.append('type', 'rating');
            formData.append('helpful_rating', helpful ? '1' : '0');

            const response = await fetch(`/dokument/${this.currentDokumentId}/quality-feedback`, {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                // Show success message
                this.showSuccessToast(helpful ? 'Vielen Dank für Ihr Feedback!' : 'Danke! Wir werden die Prüfung verbessern.');
            }
        } catch (error) {
            console.error('Failed to submit rating:', error);
        }
    }

    showFeedbackForm(event) {
        event.preventDefault();
        this.feedbackModalTarget.classList.remove('hidden');
    }

    closeFeedback() {
        this.feedbackModalTarget.classList.add('hidden');
        this.feedbackTypeTarget.value = 'false_negative';
        this.feedbackDescriptionTarget.value = '';
    }

    async submitFeedback(event) {
        event.preventDefault();

        const type = this.feedbackTypeTarget.value;
        const description = this.feedbackDescriptionTarget.value;

        if (!description.trim()) {
            alert('Bitte geben Sie eine Beschreibung ein');
            return;
        }

        try {
            const formData = new FormData();
            formData.append('ai_provider', this.currentProvider);
            formData.append('ai_result', JSON.stringify(this.currentResults));
            formData.append('type', type);
            formData.append('description', description);

            const response = await fetch(`/dokument/${this.currentDokumentId}/quality-feedback`, {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                this.closeFeedback();
                this.showSuccessToast('Problem wurde gemeldet. Vielen Dank!');
            } else {
                throw new Error('Failed to submit feedback');
            }
        } catch (error) {
            console.error('Failed to submit feedback:', error);
            alert('Fehler beim Senden des Feedbacks');
        }
    }

    showSuccessToast(message) {
        // Create toast notification
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-4 right-4 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg z-50';
        toast.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span>${message}</span>
            </div>
        `;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
}
