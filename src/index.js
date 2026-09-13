import { useState } from '@wordpress/element';
import { Button, CheckboxControl, SelectControl, TextareaControl, Notice, Spinner } from '@wordpress/components';
import { render } from '@wordpress/element';
import './style.css';

function Tooltip( { title, description } ) {
    const tooltipText = title + ( description ? ` - ${ description }` : '' );
    return (
        <span
            className="ml-1.5 inline-flex items-center align-middle text-textmuted"
            title={ tooltipText }
        >
            <svg className="w-4 h-4 cursor-help" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true" focusable="false" style={{ width: '16px', height: '16px' }}>
                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3 3 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd"></path>
            </svg>
        </span>
    );
}

function App() {
    // Check if Pro version is active
    const isPro = window.wpAiContextPro && window.wpAiContextPro.isPro;

    const [activeTab, setActiveTab] = useState('manual'); // 'presets', 'manual', or 'snapshots'
    const [selectedPlugins, setSelectedPlugins] = useState([]);
    const [mode, setMode] = useState('delta');
    const [includeLogs, setIncludeLogs] = useState(false);
    const [includeVisuals, setIncludeVisuals] = useState(false);
    const [isGenerating, setIsGenerating] = useState(false);
    const [markdown, setMarkdown] = useState('');
    const [error, setError] = useState('');

    const plugins = window.wpAiContextData.plugins;
    const presets = window.wpAiContextData.presets || [];
    const snapshots = window.wpAiContextData.snapshots || [];
    const [pendingActions, setPendingActions] = useState(window.wpAiContextData.pendingActions || []);
    const [confirmedCritical, setConfirmedCritical] = useState({});
    
    // State for App Password Generator
    const [appPassword, setAppPassword] = useState(null);
    const [isGeneratingPassword, setIsGeneratingPassword] = useState(false);
    
    const pluginList = Object.keys(plugins)
        .map(slug => ({
            slug,
            name: plugins[slug].name,
            version: plugins[slug].version
        }))
        .filter(p => p.slug !== (window.wpAiContextData.selfSlug || 'ai-context-engine')
            && p.slug !== (window.wpAiContextData.selfSlugPrev || 'wp-ai-context'));

    const handleGenerate = (presetId = null) => {
        setIsGenerating(true);
        setError('');
        setMarkdown('');

        const data = new URLSearchParams();
        data.append('action', 'wp_ai_context_generate');
        data.append('nonce', window.wpAiContextData.nonce);
        data.append('mode', mode);
        data.append('include_logs', includeLogs);
        data.append('include_visuals', includeVisuals);

        if (presetId) {
            data.append('preset', presetId);
        } else {
            selectedPlugins.forEach(p => data.append('plugins[]', p));
        }

        fetch(window.wpAiContextData.ajaxUrl, {
            method: 'POST',
            body: data,
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                setMarkdown(res.data.markdown);
            } else {
                setError(res.data || 'An error occurred generating context.');
            }
        })
        .catch(err => setError(err.message))
        .finally(() => setIsGenerating(false));
    };

    const handleGenerateDiff = (snapshotId) => {
        setIsGenerating(true);
        setError('');
        setMarkdown('');

        const data = new URLSearchParams();
        data.append('action', 'wp_ai_context_generate_diff');
        data.append('nonce', window.wpAiContextData.nonce);
        data.append('snapshot_id', snapshotId);

        fetch(window.wpAiContextData.ajaxUrl, {
            method: 'POST',
            body: data,
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                setMarkdown(res.data.markdown);
            } else {
                setError(res.data || 'An error occurred generating diff.');
            }
        })
        .catch(err => setError(err.message))
        .finally(() => setIsGenerating(false));
    };

    const handleTakeSnapshot = () => {
        setIsGenerating(true);
        setError('');

        const data = new URLSearchParams();
        data.append('action', 'wp_ai_context_take_snapshot');
        data.append('nonce', window.wpAiContextData.nonce);

        fetch(window.wpAiContextData.ajaxUrl, {
            method: 'POST',
            body: data,
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                alert('Snapshot created! Please refresh the page to see it.');
            } else {
                setError(res.data || 'Error taking snapshot.');
            }
        })
        .catch(err => setError(err.message))
        .finally(() => setIsGenerating(false));
    };

    const handlePushCloud = (snapshotId) => {
        setIsGenerating(true);
        setError('');

        const data = new URLSearchParams();
        data.append('action', 'wp_ai_context_push_cloud');
        data.append('nonce', window.wpAiContextData.nonce);
        data.append('snapshot_id', snapshotId);

        fetch(window.wpAiContextData.ajaxUrl, {
            method: 'POST',
            body: data,
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                alert('Pushed to cloud successfully!');
            } else {
                setError(res.data || 'Error pushing to cloud. Is the endpoint configured?');
            }
        })
        .catch(err => setError(err.message))
        .finally(() => setIsGenerating(false));
    };

    const handleActionApproval = (actionId, isApprove) => {
        setIsGenerating(true);
        setError('');

        const data = new URLSearchParams();
        data.append('action', isApprove ? 'wp_ai_context_approve_action' : 'wp_ai_context_reject_action');
        data.append('nonce', window.wpAiContextData.nonce);
        data.append('action_id', actionId);
        data.append('mode', mode);
        data.append('include_logs', includeLogs);
        data.append('include_visuals', includeVisuals);

        fetch(window.wpAiContextData.ajaxUrl, {
            method: 'POST',
            body: data,
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                alert(res.data);
                setPendingActions(pendingActions.filter(a => a.id !== actionId));
            } else {
                setError(res.data || 'Error processing action.');
            }
        })
        .catch(err => setError(err.message))
        .finally(() => setIsGenerating(false));
    };

    const handleActionPreview = (actionId) => {
        setIsGenerating(true);
        setError('');

        const data = new URLSearchParams();
        data.append('action', 'wp_ai_context_preview_action');
        data.append('nonce', window.wpAiContextData.nonce);
        data.append('action_id', actionId);

        fetch(window.wpAiContextData.ajaxUrl, {
            method: 'POST',
            body: data,
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                // Open the preview URL in a new tab
                window.open(res.data, '_blank');
            } else {
                setError(res.data || 'Error enabling preview mode.');
            }
        })
        .catch(err => setError(err.message))
        .finally(() => setIsGenerating(false));
    };

    const handlePlaygroundExport = () => {
        setIsGenerating(true);
        setError('');

        const data = new URLSearchParams();
        data.append('action', 'wp_ai_context_export_playground');
        data.append('nonce', window.wpAiContextData.nonce);

        fetch(window.wpAiContextData.ajaxUrl, {
            method: 'POST',
            body: data,
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                // In a real app we might trigger a JSON download here.
                alert('Playground Blueprint generated! Redirecting...');
                window.open(res.data.url, '_blank');
            } else {
                setError(res.data || 'Error exporting to Playground.');
            }
        })
        .catch(err => setError(err.message))
        .finally(() => setIsGenerating(false));
    };

    const togglePlugin = (slug, checked) => {
        if (checked) {
            setSelectedPlugins([...selectedPlugins, slug]);
        } else {
            setSelectedPlugins(selectedPlugins.filter(p => p !== slug));
        }
    };

    const generateAppPassword = async () => {
        setIsGeneratingPassword(true);
        try {
            const formData = new URLSearchParams();
            formData.append('action', 'wp_ai_context_generate_app_password');
            formData.append('nonce', window.wpAiContextData.nonce);

            const response = await fetch(window.wpAiContextData.ajaxUrl, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.success) {
                setAppPassword(result.data);
            } else {
                alert(result.data || 'Failed to generate password.');
            }
        } catch (err) {
            alert('A network error occurred.');
        } finally {
            setIsGeneratingPassword(false);
        }
    };

    const renderSetupTab = () => {
        return (
            <div className="space-y-8">
                {/* 1-Click Connect (App Password) */}
                <div className="bg-white rounded-xl shadow-sm border border-bordergray overflow-hidden">
                    <div className="bg-gray-50 px-6 py-4 border-b border-bordergray flex justify-between items-center">
                        <div>
                            <h3 className="font-semibold text-textmain text-lg">1. Connect Your AI Agent (Instant)</h3>
                            <p className="text-sm text-textmuted">Generate a secure REST API key to let your AI read context and propose fixes.</p>
                        </div>
                        <Button isPrimary onClick={generateAppPassword} disabled={isGeneratingPassword} className="bg-ghblue hover:bg-ghbluehover">
                            {isGeneratingPassword ? 'Generating...' : 'Generate AI Password'}
                        </Button>
                    </div>
                    <div className="p-6">
                        {appPassword ? (
                            <div className="bg-green-50 border border-green-200 rounded p-4">
                                <h4 className="text-green-800 font-bold mb-2">✅ Success! Connection Credentials Generated</h4>
                                <p className="text-sm mb-4">Paste the following securely into your AI agent's instructions (like Cursor or Claude):</p>
                                <div className="bg-gray-900 text-gray-100 p-4 rounded font-mono text-sm mb-2">
                                    Username: {appPassword.username}<br />
                                    Application Password: {appPassword.password}
                                </div>
                                <p className="text-xs text-red-600 font-bold">WARNING: This password will only be shown once. Copy it now.</p>
                            </div>
                        ) : (
                            <div className="text-sm text-textmuted">
                                <p>This will generate an official WordPress Application Password tied to your admin account. It operates strictly within the security guardrails of this plugin.</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Universal API Integration */}
                <div className="bg-white rounded-xl shadow-sm border border-bordergray overflow-hidden">
                    <div className="bg-gray-50 px-6 py-4 border-b border-bordergray">
                        <h3 className="font-semibold text-textmain text-lg flex items-center">
                            2. Universal Cloud AI Integration (ChatGPT, Gemini, Copilot)
                            <Tooltip 
                                title="Universal API Schema" 
                                description="Generates a standard OpenAPI v3 schema. This is the global industry standard for APIs, meaning it works with almost every major AI platform on the market." 
                            />
                        </h3>
                        <p className="text-sm text-textmuted">Bypass localhost MCP and connect standard web-based AI directly to this site.</p>
                    </div>
                    <div className="p-6 space-y-6">
                        <p className="text-sm">
                            If you don't use Desktop AI Agents (like Cursor) and want to use standard Web AI interfaces, you can easily connect them using our Universal API URL.
                        </p>
                        
                        <div className="bg-gray-100 border border-gray-300 rounded p-4 relative group">
                            <h5 className="text-xs font-bold uppercase text-gray-500 mb-2">Your Universal API Schema URL:</h5>
                            <pre className="text-sm font-mono whitespace-pre-wrap text-gray-800">
{`${window.location.origin}/wp-json/wp-ai-context/v1/openapi.json`}
                            </pre>
                            <button 
                                onClick={(e) => {
                                    navigator.clipboard.writeText(`${window.location.origin}/wp-json/wp-ai-context/v1/openapi.json`);
                                    e.target.innerText = 'Copied!';
                                    setTimeout(() => e.target.innerText = 'Copy URL', 2000);
                                }}
                                className="absolute top-2 right-2 bg-white border border-gray-300 text-xs px-2 py-1 rounded hover:bg-gray-50"
                            >
                                Copy URL
                            </button>
                        </div>
                        <p className="text-xs text-textmuted">Note: For all platforms below, you will need to enter the Application Password you generated in Step 1 as your "API Key" or "Basic Auth" credentials.</p>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="border border-bordergray rounded-lg p-4 bg-white">
                                <h4 className="font-bold text-sm mb-2 flex items-center"><span className="mr-2">🟢</span> ChatGPT (OpenAI)</h4>
                                <ol className="text-xs space-y-1 text-textmuted list-decimal list-inside">
                                    <li>Go to <strong>Explore GPTs &rarr; Create</strong></li>
                                    <li>Click the <strong>Configure</strong> tab</li>
                                    <li>Scroll down and click <strong>Create New Action</strong></li>
                                    <li>Click <strong>Import from URL</strong> and paste the link above</li>
                                </ol>
                            </div>
                            
                            <div className="border border-bordergray rounded-lg p-4 bg-white">
                                <h4 className="font-bold text-sm mb-2 flex items-center"><span className="mr-2">🔵</span> Google Gemini</h4>
                                <ol className="text-xs space-y-1 text-textmuted list-decimal list-inside">
                                    <li>Go to <strong>Gemini Advanced / Vertex AI</strong></li>
                                    <li>Create a new <strong>Gemini Custom Agent</strong></li>
                                    <li>Navigate to the <strong>Extensions / Tools</strong> menu</li>
                                    <li>Select <strong>Import OpenAPI Spec</strong> and paste the link</li>
                                </ol>
                            </div>
                            
                            <div className="border border-bordergray rounded-lg p-4 bg-white">
                                <h4 className="font-bold text-sm mb-2 flex items-center"><span className="mr-2">🟣</span> Microsoft Copilot</h4>
                                <ol className="text-xs space-y-1 text-textmuted list-decimal list-inside">
                                    <li>Open <strong>Copilot Studio</strong></li>
                                    <li>Select your Copilot and go to <strong>Actions</strong></li>
                                    <li>Click <strong>Add an Action</strong></li>
                                    <li>Select <strong>OpenAPI</strong> and import the URL</li>
                                </ol>
                            </div>

                            <div className="border border-bordergray rounded-lg p-4 bg-white">
                                <h4 className="font-bold text-sm mb-2 flex items-center"><span className="mr-2">🟠</span> Zapier / Make.com</h4>
                                <ol className="text-xs space-y-1 text-textmuted list-decimal list-inside">
                                    <li>Create a new Zap / Scenario</li>
                                    <li>Add an <strong>API Request / HTTP</strong> module</li>
                                    <li>Use the URL to fetch <code>/context</code> or push <code>/propose-action</code></li>
                                    <li>Automate your WP site without code!</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Playwright Advertising */}
                <div className="bg-white rounded-xl shadow-sm border border-bordergray overflow-hidden">
                    <div className="bg-gray-50 px-6 py-4 border-b border-bordergray">
                        <h3 className="font-semibold text-textmain text-lg">3. Automate Browsing with Playwright (Advanced)</h3>
                        <p className="text-sm text-textmuted">Let your AI physically click around your site, test checkouts, and log in just like a real user.</p>
                    </div>
                    <div className="p-6 space-y-4">
                        <p className="text-sm">
                            Because WordPress hosts block headless browsers, we cannot bundle Playwright directly in this plugin. 
                            <strong> However, your AI Agent running on your computer already supports it via MCP!</strong>
                        </p>
                        <p className="text-sm">
                            To give your AI the power to physically browse your site (e.g. to test if a WooCommerce checkout button is working), just copy and paste the exact prompt below into your AI chat box. The AI will do the rest automatically.
                        </p>
                        
                        <div className="bg-gray-100 border border-gray-300 rounded p-4 relative group">
                            <h5 className="text-xs font-bold uppercase text-gray-500 mb-2">Copy this prompt to your AI:</h5>
                            <pre className="text-sm font-mono whitespace-pre-wrap text-gray-800">
{`Please set up and connect to the official Playwright MCP server on my machine so you can automate browsing my WordPress site. 

Once connected, I want you to go to ${window.location.origin} and test if the site is rendering correctly for logged-out users.`}
                            </pre>
                            <button 
                                onClick={(e) => {
                                    navigator.clipboard.writeText(`Please set up and connect to the official Playwright MCP server on my machine so you can automate browsing my WordPress site. \n\nOnce connected, I want you to go to ${window.location.origin} and test if the site is rendering correctly for logged-out users.`);
                                    e.target.innerText = 'Copied!';
                                    setTimeout(() => e.target.innerText = 'Copy Prompt', 2000);
                                }}
                                className="absolute top-2 right-2 bg-white border border-gray-300 text-xs px-2 py-1 rounded hover:bg-gray-50"
                            >
                                Copy Prompt
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        );
    };

    return (
        <div className="wp-ai-context-react-app max-w-5xl mx-auto mt-6 space-y-6 pb-12">
            {/* Ambient Header */}
            <div className="bg-white rounded-xl shadow-sm border border-bordergray overflow-hidden mb-6">
                <div className="bg-gradient-to-r from-ambient-50 to-white px-8 py-8 border-b border-bordergray flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-ambient-900 mb-2 flex items-center">
                            <span className="mr-3 text-4xl">🤖</span> WP AI Context
                        </h1>
                        <p className="text-ambient-900/80 text-base max-w-2xl">
                            Connect your site directly to AI models like ChatGPT, Claude, and Cursor. 
                            Export configurations, debug errors instantly, and let the AI propose autonomous fixes.
                        </p>
                    </div>
                    <div className="hidden md:block">
                        <span className="inline-flex items-center rounded-full bg-ambient-100 px-3 py-1 text-sm font-semibold text-ambient-900 ring-1 ring-inset ring-ambient-500/20">
                            {isPro ? 'Pro Version Active' : 'Free Version'}
                        </span>
                    </div>
                </div>
            </div>

            {error && <Notice status="error" onRemove={() => setError('')}>{error}</Notice>}
            
            <div className="bg-white rounded-xl border border-bordergray shadow-sm overflow-hidden">
                {/* Modern Pill-Style Tabs */}
                <div className="bg-gray-50 border-b border-bordergray p-3">
                    <div className="flex flex-wrap gap-2 overflow-x-auto">
                        <button 
                            className={`px-4 py-2.5 rounded-lg text-sm font-semibold whitespace-nowrap focus:outline-none transition-all duration-200 flex items-center ${activeTab === 'presets' ? 'bg-ambient-500 text-white shadow-sm hover:bg-ambient-900' : 'text-textmuted hover:text-textmain hover:bg-gray-200/50 bg-transparent'}`}
                            onClick={() => setActiveTab('presets')}
                        >
                            <span className="mr-2">✨</span> Presets (Recommended)
                        </button>
                        <button 
                            className={`px-4 py-2.5 rounded-lg text-sm font-semibold whitespace-nowrap focus:outline-none transition-all duration-200 flex items-center ${activeTab === 'manual' ? 'bg-ambient-500 text-white shadow-sm hover:bg-ambient-900' : 'text-textmuted hover:text-textmain hover:bg-gray-200/50 bg-transparent'}`}
                            onClick={() => setActiveTab('manual')}
                        >
                            <span className="mr-2">⚙️</span> Manual Selection
                        </button>
                        <button 
                            className={`px-4 py-2.5 rounded-lg text-sm font-semibold whitespace-nowrap focus:outline-none transition-all duration-200 flex items-center ${activeTab === 'snapshots' ? 'bg-ambient-500 text-white shadow-sm hover:bg-ambient-900' : 'text-textmuted hover:text-textmain hover:bg-gray-200/50 bg-transparent'}`}
                            onClick={() => setActiveTab('snapshots')}
                        >
                            <span className="mr-2">📸</span> Snapshots & Diffing {!isPro && <span className="ml-1 opacity-70">🔒</span>}
                        </button>
                        <button 
                            className={`px-4 py-2.5 rounded-lg text-sm font-semibold whitespace-nowrap focus:outline-none transition-all duration-200 flex items-center ${activeTab === 'approvals' ? 'bg-ambient-500 text-white shadow-sm hover:bg-ambient-900' : 'text-textmuted hover:text-textmain hover:bg-gray-200/50 bg-transparent'}`}
                            onClick={() => setActiveTab('approvals')}
                        >
                            <span className="mr-2">⚖️</span> AI Approvals {!isPro && <span className="ml-1 opacity-70">🔒</span>}
                            {pendingActions.length > 0 && (
                                <span className="ml-2 inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700">
                                    {pendingActions.length}
                                </span>
                            )}
                        </button>
                        <button 
                            className={`px-4 py-2.5 rounded-lg text-sm font-semibold whitespace-nowrap focus:outline-none transition-all duration-200 flex items-center ${activeTab === 'setup' ? 'bg-ambient-500 text-white shadow-sm hover:bg-ambient-900' : 'text-textmuted hover:text-textmain hover:bg-gray-200/50 bg-transparent'}`}
                            onClick={() => setActiveTab('setup')}
                        >
                            <span className="mr-2">🔌</span> Connect AI Agent
                        </button>
                    </div>
                </div>
                
                <div className="p-5">
                    {activeTab === 'presets' && (
                        <div>
                            <p className="text-sm text-textmuted mb-4">Select a diagnostic preset below. The engine will automatically gather the correct plugins, environment variables, and schema for that specific task.</p>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {presets.map(preset => (
                                    <div key={preset.id} className={`border rounded-lg p-4 transition-colors bg-white ${preset.is_recommended ? 'border-amber-400 shadow-sm' : 'border-bordergray hover:border-ghblue'}`}>
                                        <div className="flex justify-between items-start mb-1">
                                            <h4 className="font-semibold text-textmain">{preset.title}</h4>
                                            {preset.is_recommended && (
                                                <span className="inline-flex items-center rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                                    Recommended
                                                </span>
                                            )}
                                        </div>
                                        <p className="text-xs text-textmuted mb-4">{preset.description}</p>
                                        <Button 
                                            isSecondary 
                                            onClick={() => handleGenerate(preset.id)}
                                            disabled={isGenerating}
                                        >
                                            {isGenerating ? <Spinner /> : 'Run ' + preset.title}
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {activeTab === 'manual' && (
                        <div>
                            <label className="block text-sm font-semibold text-textmain flex items-center mb-2">
                                Export Mode
                                <Tooltip 
                                    title="What should the AI read?" 
                                    description="Delta mode hides empty/default settings to save tokens and makes AI responses faster. Full mode forces the AI to read every single setting (use if AI is confused)." 
                                />
                            </label>
                            <SelectControl
                                value={mode}
                                options={[
                                    { label: 'Delta (Optimized) - Only non-default settings', value: 'delta' },
                                    { label: 'Full Export - Complete settings dump', value: 'full' }
                                ]}
                                onChange={(val) => setMode(val)}
                            />
                            
                            <div className="bg-white p-6 rounded-xl border border-bordergray shadow-sm mt-4">
                                <label className="block text-sm font-semibold text-textmain mb-3 flex items-center">
                                    Targeted Plugins Context
                                    <Tooltip 
                                        title="Which plugins to debug?" 
                                        description="Select the plugins causing problems. The AI will read their settings, hooks, and versions. Leave empty for a general site scan." 
                                    />
                                </label>
                                <div className="border border-bordergray rounded-md max-h-64 overflow-y-auto mb-5 p-3">
                                    {pluginList.map(p => (
                                        <CheckboxControl
                                            key={p.slug}
                                            label={`${p.name} (v${p.version})`}
                                            checked={selectedPlugins.includes(p.slug)}
                                            onChange={(val) => togglePlugin(p.slug, val)}
                                        />
                                    ))}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-5">
                                <div className="bg-white p-5 rounded-xl border border-bordergray shadow-sm hover:border-ghblue transition-colors cursor-pointer" onClick={() => setIncludeLogs(!includeLogs)}>
                                    <label className="flex items-start space-x-3 cursor-pointer">
                                        <input 
                                            type="checkbox" 
                                            className="form-checkbox h-5 w-5 text-ghblue mt-0.5 rounded"
                                            checked={includeLogs}
                                            onChange={(e) => setIncludeLogs(e.target.checked)}
                                        />
                                        <div>
                                            <span className="text-sm font-semibold text-textmain flex items-center">
                                                Include Error Logs (Query Monitor)
                                                <Tooltip 
                                                    title="Attach Error Logs" 
                                                    description="Appends the latest PHP fatal errors, warnings, and slow database queries to the AI package. Always turn this ON if your site is crashing." 
                                                />
                                            </span>
                                            <p className="text-xs text-textmuted mt-1">Attach recent PHP errors and slow queries.</p>
                                        </div>
                                    </label>
                                </div>
                                
                                <div className="bg-white p-5 rounded-xl border border-bordergray shadow-sm hover:border-ghblue transition-colors cursor-pointer" onClick={() => setIncludeVisuals(!includeVisuals)}>
                                    <label className="flex items-start space-x-3 cursor-pointer">
                                        <input 
                                            type="checkbox" 
                                            className="form-checkbox h-5 w-5 text-ghblue mt-0.5 rounded"
                                            checked={includeVisuals}
                                            onChange={(e) => setIncludeVisuals(e.target.checked)}
                                        />
                                        <div>
                                            <span className="text-sm font-semibold text-textmain flex items-center">
                                                Include Visual Context (mshots)
                                                <Tooltip 
                                                    title="Let the AI See" 
                                                    description="Takes a live screenshot of your homepage and attaches it. Turn this ON if you want the AI to fix a CSS styling or layout bug." 
                                                />
                                            </span>
                                            <p className="text-xs text-textmuted mt-1">Allow the AI to "see" your homepage design.</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <div className="flex justify-end gap-4 items-center mt-6">
                                <Button 
                                    isPrimary 
                                    onClick={() => handleGenerate(null)}
                                    disabled={isGenerating}
                                    className="bg-ghblue hover:bg-ghbluehover text-white w-full sm:w-auto"
                                >
                                    {isGenerating ? 'Generating Context...' : 'Generate AI Context Package'}
                                </Button>
                                <button
                                    onClick={(e) => {
                                        navigator.clipboard.writeText(`I have the WP AI Context plugin installed at ${window.location.origin}. Please use your MCP tools to fetch the context package and diagnose my site.`);
                                        e.target.innerText = 'Copied!';
                                        setTimeout(() => e.target.innerText = 'Copy Starter Prompt for AI', 2000);
                                    }}
                                    className="text-sm text-ghblue hover:text-ghbluehover font-medium underline"
                                >
                                    Copy Starter Prompt for AI
                                </button>
                            </div>
                        </div>
                    )}

                    {activeTab === 'snapshots' && (
                        <div>
                            {!isPro ? (
                                <div className="bg-gradient-to-r from-purple-50 to-pink-50 border border-purple-200 rounded-xl p-8 text-center shadow-sm mb-4">
                                    <h3 className="text-xl font-bold text-purple-900 mb-2">⭐ WP AI Context Pro Required</h3>
                                    <p className="text-purple-800 mb-6 max-w-xl mx-auto">
                                        The Free version allows AI to <strong>read</strong> your site and diagnose bugs. To let AI <strong>write</strong> code and automatically take safety snapshots, you need Pro.
                                    </p>
                                    <a href="https://yourwebsite.com/pro" target="_blank" rel="noopener noreferrer" className="inline-block bg-purple-600 hover:bg-purple-700 text-white font-semibold py-3 px-8 rounded-lg shadow-md transition-all">
                                        Upgrade to Pro
                                    </a>
                                </div>
                            ) : null}
                            <div className={`flex justify-between items-center bg-white p-4 rounded-lg border border-bordergray mb-4 ${!isPro ? 'opacity-50 pointer-events-none' : ''}`}>
                                <div>
                                    <h3 className="text-lg font-semibold text-textmain flex items-center">
                                        Historical Snapshots
                                        <Tooltip 
                                            title="Time Travel for WordPress" 
                                            description="Before the AI applies any code or setting change, it automatically takes a snapshot here. If the AI breaks your site, you can instantly restore the exact settings from this menu." 
                                        />
                                    </h3>
                                    <p className="text-sm text-textmuted">Restore previously working settings automatically.</p>
                                </div>
                                <div className="space-x-2">
                                    <Button isPrimary onClick={handleTakeSnapshot} disabled={isGenerating}>Take Manual Snapshot</Button>
                                    <Button isSecondary onClick={handlePlaygroundExport} disabled={isGenerating}>Export to WP Playground</Button>
                                </div>
                            </div>
                            
                            {snapshots.length === 0 ? (
                                <div className="bg-gray-50 border border-bordergray rounded-lg p-10 text-center text-textmuted">
                                    <p className="mb-4">No snapshots have been taken yet.</p>
                                    <div className="inline-block bg-white border border-gray-200 rounded p-4 text-left shadow-sm max-w-md">
                                        <p className="text-xs font-bold uppercase text-gray-500 mb-2">💡 AI Pro Tip:</p>
                                        <p className="text-sm mb-2 text-textmain">You can ask your AI to automatically take a snapshot before it makes any changes. Try copying this to your AI:</p>
                                        <div className="bg-gray-100 p-2 rounded relative group">
                                            <code className="text-xs text-gray-800">Please trigger a manual snapshot using the WP AI Context plugin before we begin fixing the site.</code>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="border border-bordergray rounded-md overflow-hidden">
                                    <table className="w-full text-sm text-left">
                                        <thead className="text-xs text-textmuted bg-graybg border-b border-bordergray">
                                            <tr>
                                                <th className="px-4 py-3">Snapshot Date</th>
                                                <th className="px-4 py-3">Event</th>
                                                <th className="px-4 py-3 text-right">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-bordergray">
                                            {snapshots.map(snap => (
                                                <tr key={snap.id} className="hover:bg-gray-50 bg-white">
                                                    <td className="px-4 py-3 font-medium text-textmain">{snap.date}</td>
                                                    <td className="px-4 py-3 text-textmuted">{snap.title}</td>
                                                    <td className="px-4 py-3 text-right space-x-2">
                                                        <Button 
                                                            isSecondary 
                                                            isSmall
                                                            onClick={() => handleGenerateDiff(snap.id)}
                                                            disabled={isGenerating}
                                                        >
                                                            Diff
                                                        </Button>
                                                        <Button 
                                                            isSecondary 
                                                            isSmall
                                                            onClick={() => handlePushCloud(snap.id)}
                                                            disabled={isGenerating}
                                                        >
                                                            Push to Cloud
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    )}

                    {activeTab === 'approvals' && (
                        <div>
                            {!isPro ? (
                                <div className="bg-gradient-to-r from-purple-50 to-pink-50 border border-purple-200 rounded-xl p-8 text-center shadow-sm mb-6">
                                    <h3 className="text-xl font-bold text-purple-900 mb-2">⭐ WP AI Context Pro Required</h3>
                                    <p className="text-purple-800 mb-6 max-w-xl mx-auto">
                                        Unlock the <strong>Human-in-the-Loop Approvals</strong> engine. Let your AI agent write code, patch files, and fix settings safely with 1-click reviews.
                                    </p>
                                    <a href="https://yourwebsite.com/pro" target="_blank" rel="noopener noreferrer" className="inline-block bg-purple-600 hover:bg-purple-700 text-white font-semibold py-3 px-8 rounded-lg shadow-md transition-all">
                                        Get Pro to Auto-Fix Bugs
                                    </a>
                                </div>
                            ) : null}
                            <div className={`flex justify-between items-start mb-6 ${!isPro ? 'opacity-50 pointer-events-none' : ''}`}>
                                <div>
                                    <h3 className="text-lg font-semibold text-textmain flex items-center">
                                        Pending AI Approvals
                                        <Tooltip 
                                            title="Human-in-the-Loop" 
                                            description="The AI cannot change your site without your permission. When the AI proposes a fix, it appears here. You must review the code/settings before allowing it to execute." 
                                        />
                                    </h3>
                                    <p className="text-sm text-textmuted mb-4">Review and approve changes proposed by external AI agents.</p>
                                </div>
                            </div>
                            
                            {pendingActions.length === 0 ? (
                                <div className="bg-gray-50 border border-bordergray rounded-lg p-10 text-center text-textmuted">
                                    <p className="mb-4">No pending actions to approve.</p>
                                    <div className="inline-block bg-white border border-gray-200 rounded p-4 text-left shadow-sm max-w-md">
                                        <p className="text-xs font-bold uppercase text-gray-500 mb-2">💡 AI Pro Tip:</p>
                                        <p className="text-sm mb-2 text-textmain">When your AI finds a bug, it will propose a code or settings fix here. To get started, copy this to your AI:</p>
                                        <div className="bg-gray-100 p-2 rounded relative group">
                                            <code className="text-xs text-gray-800">I have the WP AI Context plugin installed at {window.location.origin}. Please connect to it and propose a fix for my broken checkout page.</code>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {pendingActions.map(action => (
                                        <div key={action.id} className="border border-bordergray rounded-lg p-4 bg-white">
                                            <div className="flex justify-between items-start mb-2">
                                                <div>
                                                    {action.type === 'file_patch' ? (
                                                        <h4 className="font-semibold text-textmain mb-1">
                                                            File edit proposed for <code className="bg-gray-100 px-1 py-0.5 rounded">{action.file_path}</code>
                                                        </h4>
                                                    ) : (
                                                        <h4 className="font-semibold text-textmain mb-1">
                                                            Changes proposed in <code className="bg-gray-100 px-1 py-0.5 rounded">{action.plugin_slug}</code>
                                                        </h4>
                                                    )}
                                                    <p className="text-xs text-textmuted">Proposed at {action.date}</p>
                                                </div>
                                                <div className="space-x-2">
                                                    <Button 
                                                        isSecondary 
                                                        isDestructive
                                                        onClick={() => handleActionApproval(action.id, false)}
                                                        disabled={isGenerating}
                                                    >
                                                        Reject
                                                    </Button>
                                                    <Button 
                                                        isSecondary 
                                                        onClick={() => handleActionPreview(action.id)}
                                                        disabled={isGenerating}
                                                        className="text-amber-600 border-amber-600 hover:bg-amber-50"
                                                    >
                                                        Preview (Safe Mode)
                                                    </Button>
                                                    <Button 
                                                        isPrimary 
                                                        onClick={() => handleActionApproval(action.id, true)}
                                                        disabled={isGenerating || (action.is_critical && !confirmedCritical[action.id])}
                                                        className="bg-ghblue hover:bg-ghbluehover"
                                                    >
                                                        Approve & Execute (Auto-Rollback Supported)
                                                    </Button>
                                                </div>
                                            </div>
                                            
                                            {action.is_critical && (
                                                <div className="bg-red-50 border border-red-200 text-red-800 rounded p-4 mb-4">
                                                    <h4 className="font-bold mb-2">⚠️ DANGER: Server Configuration Edit</h4>
                                                    <p className="text-sm mb-3">The AI has proposed an edit to a critical server file. Our Safe Mode CANNOT catch syntax errors in these files. If this crashes the server, you will see a White Screen of Death (WSOD).</p>
                                                    
                                                    <label className="flex items-start space-x-2">
                                                        <input 
                                                            type="checkbox" 
                                                            checked={!!confirmedCritical[action.id]}
                                                            onChange={(e) => {
                                                                setConfirmedCritical({
                                                                    ...confirmedCritical,
                                                                    [action.id]: e.target.checked
                                                                });
                                                            }}
                                                            className="form-checkbox h-4 w-4 text-red-600 mt-1"
                                                        />
                                                        <span className="text-sm font-semibold">
                                                            I explicitly confirm that I have FTP, SSH, or Hosting Panel access to manually restore this file if the server crashes.
                                                        </span>
                                                    </label>
                                                </div>
                                            )}

                                            <div className="bg-blue-50 border border-blue-100 text-blue-800 rounded p-3 text-sm mb-3">
                                                <strong>AI Reasoning:</strong> {action.reason}
                                            </div>
                                            
                                            {action.type === 'file_patch' ? (
                                                <div className="space-y-2">
                                                    <h5 className="text-xs text-textmuted uppercase tracking-wide mb-2">New File Content:</h5>
                                                    <div className="bg-gray-50 border border-bordergray rounded p-2 text-sm flex flex-col">
                                                        <pre className="bg-gray-900 text-gray-100 p-2 rounded text-xs overflow-x-auto m-0">
                                                            {action.content}
                                                        </pre>
                                                    </div>
                                                </div>
                                            ) : (
                                                <div>
                                                    <h5 className="text-xs text-textmuted uppercase tracking-wide mb-2">Batched Settings:</h5>
                                                    <div className="space-y-2">
                                                        {action.changes.map((change, idx) => (
                                                            <div key={idx} className="bg-gray-50 border border-bordergray rounded p-2 text-sm flex flex-col">
                                                                <span className="font-mono text-xs text-ghblue font-bold mb-1">{change.key}</span>
                                                                <pre className="bg-gray-900 text-gray-100 p-2 rounded text-xs overflow-x-auto m-0">
                                                                    {typeof change.value === 'object' ? JSON.stringify(change.value, null, 2) : String(change.value)}
                                                                </pre>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>

            {markdown && (
                <div className="bg-white rounded-lg border border-bordergray shadow-sm overflow-hidden" id="context-output">
                    <div className="bg-graybg border-b border-bordergray px-5 py-3 flex justify-between">
                        <h3 className="text-sm font-semibold text-textmain">Generated Markdown</h3>
                        <Button
                            isSecondary
                            isSmall
                            onClick={() => {
                                navigator.clipboard.writeText(markdown);
                                alert('Copied to clipboard!');
                            }}
                        >
                            Copy to Clipboard
                        </Button>
                    </div>
                    <div className="p-0">
                        <TextareaControl
                            value={markdown}
                            readOnly
                            className="w-full h-96 font-mono text-sm p-4 border-none focus:ring-0 m-0"
                            style={{ margin: 0 }}
                        />
                    </div>
                </div>
            )}
        </div>
    );
}

// Mount the app when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const rootElement = document.getElementById('wp-ai-context-react-root');
    if (rootElement) {
        render(<App />, rootElement);
    }
});
