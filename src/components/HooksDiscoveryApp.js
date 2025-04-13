const HooksReferenceApp = {
    data() {
        return {
            hooks: [],
            plugins: [],
            selectedPlugin: '',
            selectedFunction: '',
            searchTerm: '',
            loading: false,
            error: null,
            useCache: true
        };
    },
    computed: {
        filteredHooks() {
            return this.hooks.filter(hook => {
                const matchesPlugin = !this.selectedPlugin || hook.plugin === this.selectedPlugin;
                const matchesFunction = !this.selectedFunction || hook.functionName === this.selectedFunction;
                const matchesSearch = !this.searchTerm || 
                    hook.hook.toLowerCase().includes(this.searchTerm.toLowerCase()) ||
                    hook.plugin.toLowerCase().includes(this.searchTerm.toLowerCase());
                return matchesPlugin && matchesFunction && matchesSearch;
            });
        }
    },
    methods: {
        async fetchHooks() {
            this.loading = true;
            this.error = null;
            try {
                const params = new URLSearchParams();
                if (this.selectedPlugin) {
                    params.append('plugin', this.selectedPlugin);
                }
                if (this.selectedFunction) {
                    params.append('function', this.selectedFunction);
                }
                if (this.searchTerm) {
                    params.append('search', this.searchTerm);
                }

                const response = await fetch(`${window.HooksReference.restUrl}/hooks-reference/v1/hooks?${params.toString()}`, {
                    headers: {
                        'X-WP-Nonce': window.HooksReference.nonce
                    }
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch hooks');
                }

                const data = await response.json();
                this.hooks = data;
            } catch (err) {
                this.error = err.message;
            } finally {
                this.loading = false;
            }
        },

        async fetchPlugins() {
            try {
                const response = await fetch(`${window.HooksReference.restUrl}/hooks-reference/v1/plugins`, {
                    headers: {
                        'X-WP-Nonce': window.HooksReference.nonce
                    }
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch plugins');
                }

                const data = await response.json();
                this.plugins = data;
            } catch (err) {
                this.error = err.message;
            }
        },

        async refreshHooks() {
            this.loading = true;
            try {
                const response = await fetch(`${window.HooksReference.restUrl}/hooks-reference/v1/refresh`, {
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': window.HooksReference.nonce
                    }
                });

                if (!response.ok) {
                    throw new Error('Failed to refresh hooks');
                }

                await this.fetchHooks();
            } catch (err) {
                this.error = err.message;
            } finally {
                this.loading = false;
            }
        },

        async clearCache() {
            this.loading = true;
            try {
                const response = await fetch(`${window.HooksReference.restUrl}/hooks-reference/v1/clear-cache`, {
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': window.HooksReference.nonce
                    }
                });

                if (!response.ok) {
                    throw new Error('Failed to clear cache');
                }

                await this.fetchHooks();
            } catch (err) {
                this.error = err.message;
            } finally {
                this.loading = false;
            }
        },

        exportHooks() {
            // Create a filename with timestamp
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
            const filename = `hooks-export-${timestamp}.json`;
            
            // Group hooks by name
            const groupedHooks = {};
            this.filteredHooks.forEach(hook => {
                if (!groupedHooks[hook.hook]) {
                    groupedHooks[hook.hook] = [];
                }
                groupedHooks[hook.hook].push(hook);
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
        }
    },
    mounted() {
        this.fetchHooks();
        this.fetchPlugins();
    }
};

export default HooksReferenceApp; 