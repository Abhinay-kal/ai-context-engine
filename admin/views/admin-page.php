<?php
/**
 * Admin page view for WP AI Context.
 */
defined( 'ABSPATH' ) || exit;

$wp_ai_context_current_key = get_option( 'wp_ai_context_api_key', '' );
?>
<script>
	tailwind.config = {
		theme: {
			extend: {
				colors: {
					wpblue: '#2271b1',
					graybg: '#f6f8fa', // GitHub style background
					bordergray: '#d0d7de',
					textmain: '#24292f',
					textmuted: '#57606a',
					ghblue: '#0969da',
					ghbluehover: '#035fc7',
				}
			}
		}
	}
</script>

<style>
	/* Fix WordPress admin menu overlap with Tailwind */
	#wpcontent { padding-left: 0; }
	.wrap { margin: 0; }
	
	body {
		background-color: #f6f8fa;
	}

	/* Custom Checkboxes GitHub Style */
	.custom-checkbox {
		appearance: none;
		background-color: #fff;
		margin: 0;
		font: inherit;
		width: 16px;
		height: 16px;
		border: 1px solid #d0d7de;
		border-radius: 4px;
		display: grid;
		place-content: center;
		transition: all 0.1s;
		cursor: pointer;
	}
	.custom-checkbox::before {
		content: "";
		width: 10px;
		height: 10px;
		transform: scale(0);
		transition: 100ms transform ease-in-out;
		background-color: white;
		transform-origin: center;
		clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%);
	}
	.custom-checkbox:checked {
		background-color: #0969da;
		border-color: #0969da;
	}
	.custom-checkbox:checked::before {
		transform: scale(1);
	}
	
	/* Scrollbar for plugin list */
	.custom-scrollbar::-webkit-scrollbar {
		width: 6px;
	}
	.custom-scrollbar::-webkit-scrollbar-track {
		background: transparent;
	}
	.custom-scrollbar::-webkit-scrollbar-thumb {
		background-color: #d0d7de;
		border-radius: 10px;
	}
</style>

<div class="wrap bg-graybg min-h-screen text-textmain p-6 font-sans -ml-5 -mt-2">
	
	<div class="max-w-5xl mx-auto pt-4">
		<!-- Header -->
		<header class="mb-8 border-b border-bordergray pb-6 flex items-end justify-between">
			<div>
				<h2 class="text-3xl font-semibold tracking-tight text-slate-900 mb-1"><?php esc_html_e( 'Context Builder', 'wp-ai-context' ); ?></h2>
				<p class="text-textmuted text-sm"><?php esc_html_e( 'Export precise plugin settings and environment data for AI contexts (MCP).', 'wp-ai-context' ); ?></p>
			</div>
			<div class="hidden sm:block">
				<a href="https://github.com" target="_blank" class="text-sm text-textmuted hover:text-ghblue transition-colors flex items-center gap-1.5">
					<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd"></path></svg>
					<?php esc_html_e( 'Documentation', 'wp-ai-context' ); ?>
				</a>
			</div>
		</header>
		
		<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
			
			<!-- Left Column: Plugins & Output (React App Mount) -->
			<div class="lg:col-span-2 space-y-6" id="wp-ai-context-react-root">
				<!-- React mounts here -->
			</div>
			
			<!-- Right Column: Settings / Sidebar -->
			<div class="space-y-6">
				
				<!-- API Settings Card -->
				<div class="bg-white rounded-lg border border-bordergray shadow-sm overflow-hidden">
					<div class="bg-graybg border-b border-bordergray px-5 py-3">
						<h3 class="text-sm font-semibold text-textmain"><?php esc_html_e( 'MCP Server Configuration', 'wp-ai-context' ); ?></h3>
					</div>
					
					<div class="p-5">
						<p class="text-sm text-textmuted mb-4"><?php esc_html_e( 'Configure your Cloud Relay API key for real-time synchronization with Claude Desktop or other MCP clients.', 'wp-ai-context' ); ?></p>
						
						<form method="post" action="" class="space-y-4">
							<?php wp_nonce_field( 'save_api_key', 'wp_ai_context_api_key_nonce' ); ?>
							
							<div>
								<label for="wp_ai_context_api_key" class="block text-sm font-medium text-textmain mb-1"><?php esc_html_e( 'Secret Key', 'wp-ai-context' ); ?></label>
								<div class="flex mt-1">
									<input type="password" name="wp_ai_context_api_key" id="wp_ai_context_api_key" value="<?php echo esc_attr( $wp_ai_context_current_key ); ?>" class="block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-ghblue sm:text-sm sm:leading-6 font-mono" autocomplete="new-password" placeholder="ctx_..." />
									<button type="button" id="toggle-key-visibility" class="ml-2 inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-textmuted shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
										<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
									</button>
								</div>
							</div>
							
							<div class="pt-2 flex flex-col gap-2">
								<button type="button" id="generate-random-key" class="w-full inline-flex justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-textmain shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
									<?php esc_html_e( 'Generate New Key', 'wp-ai-context' ); ?>
								</button>
								<button type="submit" class="w-full inline-flex justify-center rounded-md bg-slate-800 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-700">
									<?php esc_html_e( 'Save Configuration', 'wp-ai-context' ); ?>
								</button>
							</div>
						</form>
					</div>
				</div>
				
				<!-- Status Card -->
				<div class="bg-white rounded-lg border border-bordergray shadow-sm overflow-hidden">
					<div class="bg-graybg border-b border-bordergray px-5 py-3 flex justify-between items-center">
						<h3 class="text-sm font-semibold text-textmain"><?php esc_html_e( 'Connection Status', 'wp-ai-context' ); ?></h3>
						<span class="inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-xs font-medium text-textmuted border border-bordergray">
							<span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
							<?php esc_html_e( 'Offline', 'wp-ai-context' ); ?>
						</span>
					</div>
					<div class="p-5">
						<p class="text-sm text-textmuted"><?php esc_html_e( 'The MCP Server is currently disconnected. Ensure your API key is properly configured in the Relay node.', 'wp-ai-context' ); ?></p>
					</div>
				</div>
				
			</div>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// API Key UI logic remains jQuery for simplicity, but Context Generation is now React.
	$('#generate-random-key').on('click', function() {
		var array = new Uint32Array(4);
		window.crypto.getRandomValues(array);
		var key = 'ctx_';
		for (var i = 0; i < array.length; i++) {
			key += array[i].toString(16);
		}
		$('#wp_ai_context_api_key').val(key).attr('type', 'text');
	});
	
	$('#toggle-key-visibility').on('click', function() {
		var input = $('#wp_ai_context_api_key');
		if (input.attr('type') === 'password') {
			input.attr('type', 'text');
		} else {
			input.attr('type', 'password');
		}
	});
});
</script>
