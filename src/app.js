import { createRoot } from '@wordpress/element';
import HooksReference from './index';

// Initialize the app
const app = document.getElementById('hooks-discovery-app');
if (app) {
    const root = createRoot(app);
    root.render(<HooksReference />);
} 