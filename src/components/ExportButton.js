import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

const ExportButton = ({ hooks, disabled }) => {
    const exportHooks = () => {
        // Create a filename with timestamp
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
        const filename = `hooks-export-${timestamp}.json`;
        
        // Group hooks by name
        const groupedHooks = {};
        hooks.forEach(hook => {
            if (!groupedHooks[hook.name]) {
                groupedHooks[hook.name] = [];
            }
            groupedHooks[hook.name].push(hook);
        });

        // Create and download the file
        const blob = new Blob([JSON.stringify(groupedHooks, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    return (
        <Button
            isSecondary
            onClick={exportHooks}
            disabled={disabled}
        >
            {__('Export Hooks', 'hooks-reference')}
        </Button>
    );
};

export default ExportButton; 