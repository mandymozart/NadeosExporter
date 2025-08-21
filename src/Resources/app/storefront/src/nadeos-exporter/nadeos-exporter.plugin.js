import Plugin from 'src/plugin-system/plugin.class';

export default class NadeosExporter extends Plugin {
    static options = {
        
    };
    
    init() {
        console.log('NadeosExporter init');
        window.addEventListener('scroll', this.onScroll.bind(this));

    }

    onScroll() {
        console.log('NadeosExporter scrolled');
    }

    registerForms() {
        console.log('NadeosExporter registerForms');
        this.registerCommissionsForm();
    }

    registerCommissionsForm() {
        console.log('NadeosExporter registerCommissionsForm');
        
        const form = document.querySelector('[data-nadeos-exporter-controller="commissions-form"]');
        
        if (!form) {
            console.warn('Commissions form not found');
            return;
        }
        
        form.addEventListener('submit', (event) => {
            event.preventDefault(); // Prevent default form submission
            
            // Get current URL
            const currentUrl = new URL(window.location.href);
            
            // Get form data
            const formData = new FormData(form);
            const year = formData.get('year');
            const month = formData.get('month');
            
            // Update URL parameters
            currentUrl.searchParams.set('year', year);
            currentUrl.searchParams.set('month', month);
            
            // Keep existing token parameter if present
            const token = formData.get('token');
            if (token) {
                currentUrl.searchParams.set('token', token);
            }
            
            // Navigate to updated URL
            window.location.href = currentUrl.toString();
        });
        
        console.log('Commissions form registered successfully');
    }
}
