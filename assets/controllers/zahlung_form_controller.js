// assets/controllers/zahlung_form_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        "hauptkategorie",
        "kostenkonto",
        "eigentuemer",
        "rechnung",
        "dienstleister",
        "mehrwertsteuer",
        "hndAnteil",
        "helpText",
        "kostenkontoTo" // For Umbuchung category
    ];

    static values = {
        rechnungByDienstleisterUrl: String
    };

    kategorieConfigs = {};

    // Custom getter for optional betrag target
    get betragTarget() {
        return this.element.querySelector('[data-zahlung-form-target="betrag"]');
    }

    get hasBetragTarget() {
        return this.betragTarget !== null;
    }

    connect() {
        console.log("ZahlungForm controller connected");
        console.log("Available targets:", this.constructor.targets);
        console.log("Target elements found:", {
            hauptkategorie: this.hasHauptkategorieTarget,
            kostenkonto: this.hasKostenkontoTarget,
            eigentuemer: this.hasEigentuemerTarget,
            dienstleister: this.hasDienstleisterTarget,
            rechnung: this.hasRechnungTarget,
            mehrwertsteuer: this.hasMehrwertsteuerTarget,
            hndAnteil: this.hasHndAnteilTarget,
            betrag: this.hasBetragTarget
        });
        this.loadKategorieConfigurations();
        this.setupFormFields();
        
        // Check if this is a kategorisieren form
        if (this.element.querySelector('.text-lg.font-bold')) {
            console.log("Detected kategorisieren form, initializing...");
            this.initializeKategorisierenForm();
        }
    }

    async loadKategorieConfigurations() {
        // Load category configurations from data attributes on options
        if (!this.hasHauptkategorieTarget) {
            console.error("No hauptkategorie target found!");
            return;
        }
        
        const options = this.hauptkategorieTarget.querySelectorAll('option');
        console.log("Found", options.length, "options in hauptkategorie");
        
        options.forEach(option => {
            if (option.value) {
                console.log("Processing option:", option.value, option.textContent.trim());
                console.log("Option datasets:", option.dataset);
                
                const config = {
                    id: parseInt(option.value),
                    name: option.textContent.trim(),
                    isPositive: option.dataset.isPositive === '1',
                    allowsZeroAmount: option.dataset.allowsZeroAmount === '1',
                    fieldConfig: JSON.parse(option.dataset.fieldConfig || '{}'),
                    validationRules: JSON.parse(option.dataset.validationRules || '{}'),
                    helpText: option.dataset.helpText || ''
                };
                this.kategorieConfigs[config.id] = config;
                console.log("Stored config for", config.id, ":", config);
            }
        });
        
        console.log("All kategorie configs loaded:", this.kategorieConfigs);
    }

    setupFormFields() {
        if (this.hasHauptkategorieTarget) {
            this.setupKategorieChangeHandler();
            
            // Setup betrag field handler only if betrag target exists
            if (this.hasBetragTarget) {
                this.setupBetragFieldHandler();
            }
        }

        if (this.hasDienstleisterTarget) {
            this.setupDienstleisterDependentFields();
        }

        // Initialize visibility for existing forms (edit mode)
        this.updateVisibility();
        
        // Initialize kostenkonto filtering if a category is already selected
        if (this.hasHauptkategorieTarget && this.hauptkategorieTarget.value) {
            this.filterKostenkonto();
        }
    }

    setupKategorieChangeHandler() {
        this.hauptkategorieTarget.addEventListener('change', () => {
            console.log("Hauptkategorie changed to:", this.hauptkategorieTarget.value);
            this.updateVisibility();
            this.updateHelpText();
            this.filterKostenkonto();
        });
    }

    setupBetragFieldHandler() {
        if (!this.hasBetragTarget) return;
        
        this.betragTarget.addEventListener('input', () => {
            this.filterKategoriesByBetrag();
            this.updateVisibility();
        });

        // Initial filtering
        if (this.betragTarget.value) {
            this.filterKategoriesByBetrag();
        }
    }

    filterKategoriesByBetrag() {
        // Skip filtering if no betrag target exists (e.g., in kategorisieren form)
        if (!this.hasBetragTarget) return;
        
        const betrag = parseFloat(this.betragTarget.value) || 0;
        const options = this.hauptkategorieTarget.querySelectorAll('option');

        options.forEach(option => {
            if (!option.value) return;

            const config = this.kategorieConfigs[option.value];
            if (!config) return;

            // Check if category allows this amount
            let shouldHide = false;
            
            if (betrag === 0 && !config.allowsZeroAmount) {
                shouldHide = true;
            } else if (betrag > 0 && !config.isPositive) {
                shouldHide = true;
            } else if (betrag < 0 && config.isPositive) {
                shouldHide = true;
            }

            option.disabled = shouldHide;
            option.hidden = shouldHide;
        });

        // Reset selection if current selection is invalid
        const selectedOption = this.hauptkategorieTarget.selectedOptions[0];
        if (selectedOption && selectedOption.value && (selectedOption.disabled || selectedOption.hidden)) {
            this.hauptkategorieTarget.value = '';
            this.hideAllGroups();
            this.updateHelpText();
        }
    }

    filterKostenkonto() {
        // Skip if no kostenkonto target exists
        if (!this.hasKostenkontoTarget) return;
        
        const kategorieId = parseInt(this.hauptkategorieTarget.value);
        if (!kategorieId) {
            // No category selected, show all active kostenkonto
            this.showAllKostenkonto();
            return;
        }
        
        // Get the kostenkonto_filter from the selected option's data attribute
        const selectedOption = this.hauptkategorieTarget.selectedOptions[0];
        if (!selectedOption || !selectedOption.value) {
            this.showAllKostenkonto();
            return;
        }
        
        const kostenkontoFilter = JSON.parse(selectedOption.dataset.kostenkontoFilter || '[]');
        console.log("Filtering kostenkonto for kategorie:", selectedOption.textContent, "filter:", kostenkontoFilter);
        
        // Filter kostenkonto options
        const options = this.kostenkontoTarget.querySelectorAll('option');
        options.forEach(option => {
            if (!option.value) return; // Skip placeholder
            
            const kostenkontoId = parseInt(option.value);
            const shouldShow = kostenkontoFilter.length === 0 || kostenkontoFilter.includes(kostenkontoId);
            
            option.disabled = !shouldShow;
            option.hidden = !shouldShow;
        });
        
        // Reset selection if current selection is now invalid
        const currentSelectedOption = this.kostenkontoTarget.selectedOptions[0];
        if (currentSelectedOption && currentSelectedOption.value && (currentSelectedOption.disabled || currentSelectedOption.hidden)) {
            this.kostenkontoTarget.value = '';
        }
    }
    
    showAllKostenkonto() {
        if (!this.hasKostenkontoTarget) return;
        
        const options = this.kostenkontoTarget.querySelectorAll('option');
        options.forEach(option => {
            option.disabled = false;
            option.hidden = false;
        });
    }

    updateVisibility() {
        console.log("updateVisibility called");
        this.hideAllGroups();

        if (!this.hasHauptkategorieTarget) {
            console.log("No hauptkategorie target found");
            return;
        }

        const kategorieId = parseInt(this.hauptkategorieTarget.value);
        console.log("Selected kategorie ID:", kategorieId);
        
        if (!kategorieId) {
            console.log("No kategorie selected");
            return;
        }

        const config = this.kategorieConfigs[kategorieId];
        console.log("Config for kategorie", kategorieId, ":", config);
        
        if (!config) {
            console.log("No config found for kategorie", kategorieId);
            return;
        }
        
        if (!config.fieldConfig.show) {
            console.log("No show configuration found for kategorie", kategorieId);
            return;
        }

        console.log("Showing fields:", config.fieldConfig.show);
        
        // Add fields with auto_set values to the show list
        let fieldsToShow = [...config.fieldConfig.show];
        if (config.fieldConfig.auto_set) {
            Object.keys(config.fieldConfig.auto_set).forEach(field => {
                if (!fieldsToShow.includes(field)) {
                    fieldsToShow.push(field);
                }
            });
        }
        
        // Show fields based on configuration
        this.showGroups(fieldsToShow);
        
        // Apply auto_set values AFTER showing fields (with small delay to ensure DOM is updated)
        setTimeout(() => {
            this.applyAutoSetFields();
        }, 10);
    }

    updateHelpText() {
        if (!this.hasHelpTextTarget) return;

        const kategorieId = parseInt(this.hauptkategorieTarget.value);
        const config = this.kategorieConfigs[kategorieId];

        let helpContent = '';
        
        if (config && config.helpText) {
            helpContent += config.helpText;
        }
        
        // Add auto_set information
        if (config && config.fieldConfig.auto_set) {
            const autoSetInfo = [];
            Object.entries(config.fieldConfig.auto_set).forEach(([field, value]) => {
                let displayValue = value;
                // Convert kostenkonto IDs to readable names
                if (field === 'kostenkonto') {
                    const kostenkontoElement = this.hasKostenkontoTarget ? this.kostenkontoTarget : null;
                    if (kostenkontoElement) {
                        const option = kostenkontoElement.querySelector(`option[value="${value}"]`);
                        if (option) {
                            displayValue = option.textContent;
                        }
                    }
                }
                autoSetInfo.push(`${field}: ${displayValue}`);
            });
            
            if (autoSetInfo.length > 0) {
                if (helpContent) helpContent += ' | ';
                helpContent += `Automatisch gesetzt: ${autoSetInfo.join(', ')}`;
            }
        }

        if (helpContent) {
            this.helpTextTarget.textContent = helpContent;
            this.helpTextTarget.closest('.help-text-container')?.classList.remove('hidden');
        } else {
            this.helpTextTarget.closest('.help-text-container')?.classList.add('hidden');
        }
    }

    applyAutoSetFields() {
        const kategorieId = parseInt(this.hauptkategorieTarget.value);
        const config = this.kategorieConfigs[kategorieId];

        if (!config || !config.fieldConfig.auto_set) return;

        // Auto-set field values based on configuration
        Object.entries(config.fieldConfig.auto_set).forEach(([field, value]) => {
            const targetName = `${field}Target`;
            const hasTargetName = `has${field.charAt(0).toUpperCase() + field.slice(1)}Target`;
            
            if (this[hasTargetName]) {
                this[targetName].value = value;
                
                // Trigger change event in case other logic depends on it
                this[targetName].dispatchEvent(new Event('change', { bubbles: true }));
                
                // Force a visual update for select elements
                if (this[targetName].tagName === 'SELECT') {
                    this[targetName].dispatchEvent(new Event('input', { bubbles: true }));
                }
            }
        });
    }

    hideAllGroups() {
        console.log("hideAllGroups called");
        const allFields = [
            'kostenkonto', 
            'eigentuemer', 
            'rechnung', 
            'dienstleister', 
            'mehrwertsteuer', 
            'hndAnteil',
            'kostenkontoTo'
        ];

        allFields.forEach(field => {
            this.hideFieldGroup(field);
        });
    }

    hideFieldGroup(fieldName) {
        const targetName = `has${fieldName.charAt(0).toUpperCase() + fieldName.slice(1)}Target`;
        console.log("hideFieldGroup:", fieldName, "hasTarget:", this[targetName]);
        
        if (this[targetName]) {
            const target = this[`${fieldName}Target`];
            const container = target.closest('.form-group, .col-span-1, .mb-3');
            console.log("Found container to hide:", container);
            if (container) {
                container.classList.add('hidden');
            }
        }
    }

    showGroups(groups) {
        console.log("showGroups called with:", groups);
        groups.forEach(group => {
            console.log("Showing group:", group);
            this.showFieldGroup(group);
        });
    }

    showFieldGroup(fieldName) {
        const targetName = `has${fieldName.charAt(0).toUpperCase() + fieldName.slice(1)}Target`;
        console.log("showFieldGroup:", fieldName, "targetName:", targetName, "hasTarget:", this[targetName]);
        
        if (this[targetName]) {
            const target = this[`${fieldName}Target`];
            console.log("Found target element:", target);
            
            const container = target.closest('.form-group, .col-span-1, .mb-3');
            console.log("Found container:", container);
            
            if (container) {
                console.log("Removing hidden class from container");
                container.classList.remove('hidden');
            } else {
                console.log("No container found for", fieldName);
            }
        } else {
            console.log("No target found for", fieldName);
        }
    }

    // Rest of the methods remain the same...
    setupDienstleisterDependentFields() {
        this.dienstleisterTarget.addEventListener('change', (event) => {
            this.updateRechnungOptions(event.target.value);
        });

        // Initial update
        if (this.dienstleisterTarget.value) {
            this.updateRechnungOptions(this.dienstleisterTarget.value);
        }
    }

    async updateRechnungOptions(dienstleisterId) {
        if (!this.hasRechnungTarget) return;

        if (!dienstleisterId) {
            this.rechnungTarget.disabled = true;
            this.rechnungTarget.innerHTML = '<option value="">-- Bitte erst Dienstleister auswählen --</option>';
            return;
        }

        this.rechnungTarget.disabled = false;
        this.rechnungTarget.innerHTML = '<option value="">Laden...</option>';

        try {
            let url = '';
            if (this.hasRechnungByDienstleisterUrlValue) {
                url = this.rechnungByDienstleisterUrlValue.replace('DIENSTLEISTER_ID', dienstleisterId);
            } else {
                // Try to get from parent controller
                const parentController = this.application.getControllerForElementAndIdentifier(
                    document.querySelector('[data-controller="zahlung"]'),
                    'zahlung'
                );

                if (parentController && parentController.rechnungByDienstleisterUrlValue) {
                    url = parentController.rechnungByDienstleisterUrlValue.replace('DIENSTLEISTER_ID', dienstleisterId);
                } else {
                    console.error('Cannot find rechnungByDienstleisterUrl');
                    return;
                }
            }

            const response = await fetch(url);

            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            const data = await response.json();

            let options = '<option value="">-- Rechnung auswählen --</option>';

            if (data.rechnungen && data.rechnungen.length > 0) {
                data.rechnungen.forEach(rechnung => {
                    options += `<option value="${rechnung.id}">${rechnung.information} (${rechnung.rechnungsnummer || 'Keine Nr.'}) - ${rechnung.betragMitSteuern} €</option>`;
                });
            } else {
                options += '<option value="" disabled>Keine Rechnungen gefunden</option>';
            }

            this.rechnungTarget.innerHTML = options;
            this.rechnungTarget.disabled = false;
        } catch (error) {
            console.error('Error fetching rechnungen:', error);
            this.rechnungTarget.innerHTML = '<option value="">-- Fehler beim Laden der Rechnungen --</option>';
        }
    }

    // Validation methods that can be called before form submission
    validateForm() {
        const kategorieId = parseInt(this.hauptkategorieTarget.value);
        const config = this.kategorieConfigs[kategorieId];
        
        if (!config) return true; // No config means no validation

        const errors = [];

        // Validate required fields
        if (config.fieldConfig.required) {
            config.fieldConfig.required.forEach(field => {
                const targetName = `has${field.charAt(0).toUpperCase() + field.slice(1)}Target`;
                if (this[targetName]) {
                    const value = this[`${field}Target`].value;
                    if (!value) {
                        errors.push(`${field} ist erforderlich`);
                    }
                }
            });
        }

        // Apply validation rules
        if (config.validationRules) {
            // Validate betrag (only if betrag target exists)
            if (config.validationRules.betrag && this.hasBetragTarget) {
                const betrag = parseFloat(this.betragTarget.value) || 0;
                const rules = config.validationRules.betrag;
                
                if (rules.min !== undefined && betrag < rules.min) {
                    errors.push(`Betrag muss mindestens ${rules.min} sein`);
                }
                if (rules.max !== undefined && betrag > rules.max) {
                    errors.push(`Betrag darf maximal ${rules.max} sein`);
                }
            }

            // Add more validation rules as needed...
        }

        if (errors.length > 0) {
            alert('Bitte korrigieren Sie folgende Fehler:\n' + errors.join('\n'));
            return false;
        }

        return true;
    }

    // For the kategorisieren form
    initializeKategorisierenForm() {
        console.log("initializeKategorisierenForm called");
        
        // In kategorisieren form, we don't filter by betrag since it's displayed as text
        // We just need to initialize visibility based on any pre-selected category
        if (this.hasHauptkategorieTarget && this.hauptkategorieTarget.value) {
            console.log("Pre-selected category found:", this.hauptkategorieTarget.value);
            this.updateVisibility();
        }
    }
}