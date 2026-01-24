// assets/js/data-table.js
import { DataTable } from "simple-datatables";
import 'simple-datatables/dist/style.css';

export function initializeDataTables() {
    const tableElement = document.querySelector("#default-table");
    if (tableElement && !tableElement.classList.contains('dataTable-initialized')) {
        try {
            console.log("Initializing DataTable");

            // Read perPage from URL parameter, default to 25
            const urlParams = new URLSearchParams(window.location.search);
            const perPage = parseInt(urlParams.get('per_page')) || 25;

            const dataTable = new DataTable(tableElement, {
                sortable: true,
                searchable: true,
                fixedHeight: false,
                perPageSelect: [10, 25, 50, 100, 200, 300],
                perPage: perPage,
                columns: [
                    { select: 1, width: "30%" }, // Bezeichnung
                    { select: 4, width: "20%" }, // Kostenkonto
                    { select: 5, sortable: false, width: "15%" } // Aktionen
                ]
            });
            tableElement.classList.add('dataTable-initialized');

            // Store dataTable instance for later access
            tableElement.dataTableInstance = dataTable;

            // Function to sync hidden field with DataTable dropdown
            const syncPerPageField = () => {
                const perPageSelect = document.querySelector('.datatable-selector');
                const hiddenField = document.querySelector('input[name="per_page"]');

                if (perPageSelect && hiddenField) {
                    console.log('DataTable dropdown found, current value:', perPageSelect.value);
                    hiddenField.value = perPageSelect.value;
                    console.log('Hidden field synced to:', perPageSelect.value);
                    return true;
                }
                return false;
            };

            // Try multiple times with increasing delays to catch the dropdown
            const trySync = (attempts = 0, maxAttempts = 10) => {
                if (attempts >= maxAttempts) {
                    console.warn('DataTable dropdown not found after multiple attempts');
                    return;
                }

                setTimeout(() => {
                    const perPageSelect = document.querySelector('.datatable-selector');
                    const hiddenField = document.querySelector('input[name="per_page"]');

                    if (perPageSelect && hiddenField) {
                        console.log('DataTable dropdown found on attempt', attempts + 1, 'value:', perPageSelect.value);

                        // Set initial value
                        hiddenField.value = perPage;
                        console.log('Hidden field initialized to:', perPage);

                        // Listen for changes
                        perPageSelect.addEventListener('change', function() {
                            const hiddenField = document.querySelector('input[name="per_page"]');
                            if (hiddenField) {
                                hiddenField.value = this.value;
                                console.log('Dropdown changed, hidden field updated to:', this.value);
                            }
                        });
                    } else {
                        // Try again with exponential backoff
                        trySync(attempts + 1, maxAttempts);
                    }
                }, 50 * (attempts + 1));
            };

            // Start trying to find and sync the dropdown
            trySync();

            // Also ensure hidden field stays in sync before any form submission
            const filterForm = document.querySelector('#filter-form');
            if (filterForm) {
                filterForm.addEventListener('submit', function(e) {
                    const synced = syncPerPageField();
                    if (synced) {
                        console.log('Form submitting with per_page:', document.querySelector('input[name="per_page"]').value);
                    } else {
                        console.warn('Could not sync per_page before form submission');
                    }
                });
            }
        } catch (error) {
            console.error("Error initializing DataTable:", error);
        }
    }
}

// Add event listeners
document.addEventListener('DOMContentLoaded', initializeDataTables);

// Create custom event for content updates
document.addEventListener('app:content-updated', function() {
    setTimeout(initializeDataTables, 50);
});

// Listen for Turbo/Turbolinks events
document.addEventListener('turbo:load', initializeDataTables);
document.addEventListener('turbolinks:load', initializeDataTables);

// Export functions for global use
window.initializeDataTables = initializeDataTables;
window.dispatchContentUpdated = function() {
    document.dispatchEvent(new CustomEvent('app:content-updated'));
};