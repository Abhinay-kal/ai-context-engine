=== AI Context Engine ===
Contributors: Abhinay Kalkhanday
Tags: ai, chatgpt, debugging, mcp
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your WordPress site directly to AI agents like ChatGPT, Cursor, and Claude to debug settings, errors, and plugins.

== Description ==

Tired of copying and pasting error logs, plugin lists, and code snippets into ChatGPT? 

**WP AI Context** is a secure bridge between your WordPress site and modern AI agents (ChatGPT, Cursor, Claude, Google Gemini). It allows the AI to "read" your site's exact configuration, understand your database structure, and diagnose bugs in seconds.

Built on the open-source **Model Context Protocol (MCP)** and **OpenAPI v3**, this plugin turns your WordPress installation into a machine-readable format that AI models natively understand.

### 🚀 What can you do with this?
*   **Fix White Screens of Death (WSOD):** Attach Query Monitor error logs directly to your AI prompt.
*   **Debug Plugin Conflicts:** Let the AI read your exact plugin versions and settings to find incompatibilities.
*   **Automate ChatGPT:** Paste our Universal OpenAPI Schema into ChatGPT's Custom Actions, allowing ChatGPT Web to communicate directly with your site.
*   **Cursor IDE Integration:** Connect the plugin to Cursor to let the AI analyze your live production settings while you code.

### 🛡️ Security First
We take security seriously. The AI **cannot** change your site without your permission.
*   **Read-Only Core:** The free version strictly limits the AI to reading configuration and log files. 
*   **Application Passwords:** Authentication is handled natively by WordPress core. You never share your actual admin password.
*   **Delta Mode:** Sensitive user data is stripped out. The AI only sees system architecture, database schemas, and plugin settings.

### 🔓 WP AI Context PRO
Want the AI to fix the bugs for you? **WP AI Context Pro** unlocks our Human-in-the-Loop Auto-Fix engine.
*   **1-Click AI Approvals:** The AI proposes a code patch or settings change. You review the diff in your dashboard and click "Approve."
*   **Auto-Snapshots:** Before the AI changes a single line of code, the plugin takes a full snapshot. If the AI breaks something, roll it back with one click.
*   **Safe Mode Preview:** Preview the AI's changes in an isolated sandbox before pushing them live.

[Discover WP AI Context Pro](https://yourwebsite.com/pro)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ai-context-engine` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **WP AI Context** in your admin sidebar.
4. Go to the **Setup** tab and click **"Generate AI Password"**.
5. Copy the generated credentials and paste them into your AI tool (ChatGPT, Cursor, or Claude) alongside the provided setup prompt.

== Frequently Asked Questions ==

= Does this send my customer data to OpenAI? =
No. WP AI Context is designed for debugging architecture, not analyzing user data. It exports plugin configurations, active themes, database schemas, and PHP error logs. It does not export user tables, WooCommerce orders, or personal data.

= Which AI models does this work with? =
Because we use the Model Context Protocol (MCP) and OpenAPI v3, it works with almost everything: ChatGPT (via Custom Actions), Claude Desktop, Cursor IDE, Google Gemini (Custom Agents), Microsoft Copilot, and automation tools like Zapier and Make.com.

= Can the AI break my site? =
In the Free version, the AI only has "Read" access. It cannot change anything. If you upgrade to Pro to unlock the Auto-Fix engine, the AI still cannot change anything without you manually clicking "Approve" in the dashboard. Plus, the Pro version takes automatic snapshots before any change is executed.

= Does this slow down my site? =
No. The plugin only runs when an AI agent explicitly requests context (usually triggered manually by you in a chat interface). It adds zero overhead to your frontend website speed.

== Screenshots ==

1. **The Dashboard:** Select what context you want the AI to read (Plugins, Error Logs, Visuals).
2. **Universal AI Integration:** Connect easily to ChatGPT, Gemini, or Copilot using the OpenAPI link.
3. **Pro Approvals (Premium):** Review and approve code patches proposed by the AI before they go live.
4. **Snapshots (Premium):** Instantly rollback the site if an AI change doesn't work as expected.

== Changelog ==

= 1.0.0 =
* Initial public release.
* Added support for Delta (Optimized) and Full Context exports.
* Added native 1-Click Application Password generation.
* Added Universal OpenAPI v3 schema generation for ChatGPT Web integration.
* Added Query Monitor integration for fetching fatal errors and slow queries.
