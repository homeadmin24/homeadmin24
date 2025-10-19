// assets/js/data-table.js
import { DataTable } from "simple-datatables";
import 'simple-datatables/dist/style.css';

export function initializeDataTables() {
    const tableElement = document.querySelector("#default-table");
    if (tableElement && !tableElement.classList.contains('dataTable-initialized')) {
        try {
            console.log("Initializing DataTable");
            new DataTable(tableElement, {
                sortable: true,
                searchable: true,
                fixedHeight: false,
                perPageSelect: [10, 25, 50, 100],
                perPage: 25,
                columns: [
                    { select: 1, width: "30%" }, // Bezeichnung
                    { select: 4, width: "20%" }, // Kostenkonto
                    { select: 5, sortable: false, width: "15%" } // Aktionen
                ]
            });
            tableElement.classList.add('dataTable-initialized');
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