import { startStimulusApp } from '@symfony/stimulus-bridge';

console.log('🚀 Starting Stimulus app...');

// Registers Stimulus controllers from controllers.json and in the controllers/ directory
export const app = startStimulusApp(require.context(
    '@symfony/stimulus-bridge/lazy-controller-loader!./controllers',
    true,
    /\.[jt]sx?$/
));

console.log('✅ Stimulus app started:', app);
console.log('📦 Controllers loaded:', app.controllers.length);

// Expose Stimulus to window for debugging
window.Stimulus = app;
console.log('🌍 window.Stimulus set:', window.Stimulus);

// Dispatch content-updated event on DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    if (window.dispatchContentUpdated) {
        window.dispatchContentUpdated();
    }
});

// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);