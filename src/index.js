import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { 
    Card,
    CardBody,
    CardHeader,
    SelectControl,
    TextControl,
    Button,
    Spinner,
    Notice,
    Flex,
    FlexItem,
    Panel,
    PanelBody,
} from '@wordpress/components';
import ExportButton from './components/ExportButton';

// Get the localized data
if (!window.hooksReferenceData) {
    console.error('hooksReferenceData data not found. Make sure the script is properly localized.');
    window.hooksReferenceData = {
        restUrl: '/wp-json/hooks-reference/v1',
        nonce: ''
    };
}
const { restUrl, nonce } = window.hooksReferenceData;

const HooksReference = () => {
    const [hooks, setHooks] = useState([]);
    const [plugins, setPlugins] = useState([]);
    const [selectedPlugin, setSelectedPlugin] = useState('');
    const [selectedFunction, setSelectedFunction] = useState('');
    const [searchTerm, setSearchTerm] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [isLoadingPlugins, setIsLoadingPlugins] = useState(true);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);
    const [useCache, setUseCache] = useState(true);

    // Read URL parameters on initial load
    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const plugin = params.get('plugin');
        const functionName = params.get('function');
        const search = params.get('search');
        
        if (plugin) setSelectedPlugin(plugin);
        if (functionName) setSelectedFunction(functionName);
        if (search) setSearchTerm(search);

        // Check cache setting
        fetch(`${restUrl}/settings`, {
            headers: {
                'X-WP-Nonce': nonce
            }
        })
        .then(response => response.json())
        .then(data => {
            setUseCache(data.use_cache);
        })
        .catch(error => {
            console.error('Error fetching settings:', error);
            setUseCache(true); // Default to true if there's an error
        });
    }, []);

    // Update URL when filters change
    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        // Preserve the page parameter
        const page = params.get('page');
        
        // Clear existing params and set page
        params.delete('plugin');
        params.delete('function');
        params.delete('search');
        
        if (selectedPlugin) params.set('plugin', selectedPlugin);
        if (selectedFunction) params.set('function', selectedFunction);
        if (searchTerm) params.set('search', searchTerm);
        
        // Always include the page parameter
        if (page) {
            params.set('page', page);
        }

        const newUrl = window.location.pathname + (params.toString() ? `?${params.toString()}` : '');
        window.history.pushState({}, '', newUrl);
    }, [selectedPlugin, selectedFunction, searchTerm]);

    useEffect(() => {
        fetchPlugins();
    }, []);

    useEffect(() => {
        if (selectedPlugin) {
            fetchHooks();
        } else {
            setHooks([]);
        }
    }, [selectedPlugin]);

    const fetchPlugins = async () => {
        try {
            setIsLoadingPlugins(true);
            setError(null);
            
            const response = await fetch(`${restUrl}/plugins`, {
                headers: {
                    'X-WP-Nonce': nonce
                }
            });
            
            if (!response.ok) {
                throw new Error('Failed to fetch plugins');
            }
            
            const data = await response.json();
            setPlugins(data);
            
        } catch (err) {
            setError(err.message);
        } finally {
            setIsLoadingPlugins(false);
        }
    };

    const fetchHooks = async () => {
        setIsLoading(true);
        setError(null);
        
        try {
            const params = new URLSearchParams();
            if (selectedPlugin) params.append('plugin', selectedPlugin);
            if (selectedFunction) params.append('function', selectedFunction);
            if (searchTerm) params.append('search', searchTerm);

            const response = await fetch(`${restUrl}/hooks?${params.toString()}`, {
                headers: {
                    'X-WP-Nonce': nonce
                }
            });
            
            if (!response.ok) {
                throw new Error('Failed to fetch hooks');
            }
            
            const data = await response.json();
            setHooks(data);
        } catch (err) {
            setError(err.message);
        } finally {
            setIsLoading(false);
        }
    };

    const refreshHooks = async () => {
        try {
            setIsLoading(true);
            setError(null);
            setSuccess(null);
            
            const response = await fetch(`${restUrl}/refresh`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': nonce
                }
            });
            
            if (!response.ok) {
                throw new Error('Failed to refresh hooks');
            }
            
            await fetchPlugins();
            if (selectedPlugin) {
                await fetchHooks();
            }
            setSuccess(__('Hooks refreshed successfully.', 'hooks-reference'));
      
        } catch (err) {
            setError(err.message);
        } finally {
            setIsLoading(false);
        }
    };

    const clearCache = async () => {
        try {
            setIsLoading(true);
            setError(null);
            setSuccess(null);
            
            const response = await fetch(`${restUrl}/clear-cache`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': nonce
                }
            });
            
            if (!response.ok) {
                throw new Error('Failed to clear cache');
            }
            
            setSuccess(__('Cache cleared successfully.', 'hooks-reference'));
            
        } catch (err) {
            setError(err.message);
        } finally {
            setIsLoading(false);
        }
    };

    const filteredHooks = hooks.filter(hook => {
        if (selectedFunction) {
            const hookFunction = hook.functionName;
            if (hookFunction !== selectedFunction) {
                return false;
            }
        }
        if (searchTerm && !hook.name.toLowerCase().includes(searchTerm.toLowerCase())) {
            return false;
        }
        return true;
    });

    // Group hooks by name
    const groupedHooks = filteredHooks.reduce((acc, hook) => {
        if (!acc[hook.name]) {
            acc[hook.name] = [];
        }
        acc[hook.name].push(hook);
        return acc;
    }, {});

    return (
        <div className="hooks-reference">
            <Card>
                <CardHeader>
                    <h2>{__('Hooks Reference', 'hooks-reference')}</h2>
                </CardHeader>
                <CardBody>
                    {error && (
                        <Notice status="error" isDismissible={false}>
                            {error}
                        </Notice>
                    )}

                    {success && (
                        <Notice status="success" isDismissible={true}>
                            {success}
                        </Notice>
                    )}

                    {!useCache && (
                        <Notice status="warning" isDismissible={false}>
                            {__('Caching is disabled. All data is being fetched fresh.', 'hooks-reference')}
                        </Notice>
                    )}

                    <div className="hooks-reference-filters">
                        <SelectControl
                            label={__('Select Plugin', 'hooks-reference')}
                            value={selectedPlugin}
                            options={[
                                { label: __('Select a plugin...', 'hooks-reference'), value: '' },
                                ...plugins.map(plugin => ({
                                    label: `${plugin.name}${plugin.active ? ' (Active)' : ' (Inactive)'}`, 
                                    value: plugin.name 
                                }))
                            ]}
                            onChange={setSelectedPlugin}
                            disabled={isLoadingPlugins}
                        />

                        {selectedPlugin && (
                            <>
                                <SelectControl
                                    label={__('Filter by Function', 'hooks-reference')}
                                    value={selectedFunction}
                                    options={[
                                        { label: __('All Functions', 'hooks-reference'), value: '' },
                                        { label: 'add_action', value: 'add_action' },
                                        { label: 'do_action', value: 'do_action' },
                                        { label: 'add_filter', value: 'add_filter' },
                                        { label: 'apply_filters', value: 'apply_filters' }
                                    ]}
                                    onChange={setSelectedFunction}
                                />

                                <TextControl
                                    label={__('Search Hooks', 'hooks-reference')}
                                    value={searchTerm}
                                    onChange={setSearchTerm}
                                    placeholder={__('Search hook names...', 'hooks-reference')}
                                />
                            </>
                        )}
                        
                        <Flex className="hooks-reference-buttons" justify="flex-end" gap={2} style={{ marginBottom: '16px' }}>
                            <FlexItem>
                                <Button
                                    isPrimary
                                    onClick={refreshHooks}
                                    disabled={isLoading || isLoadingPlugins}
                                >
                                    {isLoading ? (
                                        <>
                                            <Spinner />
                                            {__('Refreshing...', 'hooks-reference')}
                                        </>
                                    ) : (
                                        __('Refresh Hooks', 'hooks-reference')
                                    )}
                                </Button>
                            </FlexItem>
                            <FlexItem>
                                <Button
                                    isSecondary
                                    onClick={clearCache}
                                    disabled={isLoading || isLoadingPlugins}
                                >
                                    {__('Clear Cache', 'hooks-reference')}
                                </Button>
                            </FlexItem>
                            <FlexItem>
                                <ExportButton 
                                    hooks={filteredHooks}
                                    disabled={isLoading || isLoadingPlugins || filteredHooks.length === 0}
                                />
                            </FlexItem>
                        </Flex>
                    </div>

                    <div className="hooks-reference-results">
                        {isLoadingPlugins && (
                            <div style={{ textAlign: 'center', padding: '20px' }}>
                                <Spinner />
                                <p>{__('Loading plugins...', 'hooks-reference')}</p>
                            </div>
                        )}
                        
                        {!isLoadingPlugins && !selectedPlugin && (
                            <div style={{ textAlign: 'center', padding: '20px' }}>
                                <p>{__('Please select a plugin to view its hooks.', 'hooks-reference')}</p>
                            </div>
                        )}
  
                        {selectedPlugin && isLoading && (
                            <div style={{ textAlign: 'center', padding: '20px' }}>
                                <Spinner />
                                <p>{__('Loading hooks...', 'hooks-reference')}</p>
                            </div>
                        )}
                        
                        {!isLoading && selectedPlugin && Object.keys(groupedHooks).length === 0 && (
                            <div style={{ textAlign: 'center', padding: '20px' }}>
                                <p>{__('No hooks found matching your criteria.', 'hooks-reference')}</p>
                            </div>
                        )}
                        
                        {!isLoading && selectedPlugin && Object.keys(groupedHooks).length > 0 && (
                            <>
                                {Object.entries(groupedHooks).map(([hookName, hooks]) => (
                                    <div key={hookName} style={{ marginBottom: '16px' }}>
                                        <Panel>
                                            <PanelBody
                                                title={
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                                        <span>{hookName}</span>
                                                        <span style={{
                                                            fontSize: '12px',
                                                            padding: '2px 6px',
                                                            borderRadius: '3px',
                                                            background: hooks[0].functionName.startsWith('add_') ? '#e6f3ff' : '#fff8e5',
                                                            color: hooks[0].functionName.startsWith('add_') ? '#0066cc' : '#996600'
                                                        }}>
                                                            {hooks[0].functionName}
                                                        </span>
                                                    </div>
                                                }
                                                initialOpen={false}
                                            >
                                                <table className="wp-list-table widefat fixed striped">
                                                    <thead>
                                                        <tr>
                                                            <th>{__('Plugin', 'hooks-reference')}</th>
                                                            <th>{__('File', 'hooks-reference')}</th>
                                                            <th>{__('Line', 'hooks-reference')}</th>
                                                            <th>{__('Function', 'hooks-reference')}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {hooks.map((hook, index) => (
                                                            <tr key={index}>
                                                                <td>{hook.plugin}</td>
                                                                <td>{hook.file}</td>
                                                                <td>{hook.line}</td>
                                                                <td>{hook.functionName}</td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </PanelBody>
                                        </Panel>
                                    </div>
                                ))}
                            </>
                        )}
                    </div>
                </CardBody>
            </Card>
        </div>
    );
};

// Initialize the app
const app = document.getElementById('hooks-reference-app');
if (app) {
    const root = ReactDOM.createRoot(app);
    root.render(<HooksReference />);
} 