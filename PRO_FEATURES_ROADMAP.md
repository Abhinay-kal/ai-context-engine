ac# WP AI Context Pro - Feature Roadmap

This document outlines the development plan for the premium features of WP AI Context. These features are designed to transform the tool from a simple settings exporter into a powerful diagnostic and development assistant.

## Phase 1: Deep Debugging & Error Logs
**Goal:** Provide AI with actual error data to diagnose broken sites.

### 1.1 PHP & WP Debug Log Integration
- **Component:** `collectors/class-wp-ai-context-log-collector.php`
- **Functionality:** 
  - Check if `WP_DEBUG_LOG` is enabled.
  - Locate `debug.log` (usually in `/wp-content/`).
  - Read the last N lines (e.g., 100 lines) of the log file.
  - Parse the lines to extract relevant fatal errors, warnings, and notices.
  - Add to the context package under a `debug_logs` section.

### 1.2 Query Monitor Integration Adapter
- **Component:** `adapters/class-wp-ai-context-adapter-query-monitor.php`
- **Functionality:**
  - Detect if the Query Monitor plugin is active.
  - Hook into Query Monitor's data output to capture slow queries, PHP memory usage, and execution time.
  - Summarize this data for the AI context.

---

## Phase 2: Code & Schema Context
**Goal:** Give AI the structural and code-level understanding needed to write custom queries and fix theme issues.

### 2.1 Database Schema & CPT Exporter
- **Component:** `collectors/class-wp-ai-context-schema-collector.php`
- **Functionality:**
  - Query registered Custom Post Types (`get_post_types()`) and Taxonomies (`get_taxonomies()`).
  - Use `$wpdb` to list custom database tables and their schema (`DESCRIBE table_name`). *Do not export row data.*
  - Output a structured map of the database architecture.

### 2.2 Active Theme Code Snapshot
- **Component:** `collectors/class-wp-ai-context-theme-collector.php`
- **Functionality:**
  - Identify the active theme and child theme.
  - Safely extract the contents of key files like `functions.php`, `header.php`, and `footer.php`.
  - Ensure sensitive strings (if any are hardcoded) are passed through the Privacy Filter.

---

## Phase 3: Specialized Context Presets
**Goal:** Make the UI frictionless by offering one-click diagnostic packages for specific common issues.

### 3.1 Preset Engine
- **Component:** `admin/class-wp-ai-context-presets.php`
- **Functionality:**
  - Update the Admin UI to include a "Presets" tab.
  - **SEO Audit Preset:** Gathers Yoast/RankMath settings, permalink structures, active caching rules.
  - **WooCommerce Diagnostic Preset:** Gathers WC settings, shipping zones, payment gateway status.
  - **Performance Preset:** Gathers PHP limits, server specs, caching plugin configurations.

---

## Phase 4: State Snapshots (Diffing)
**Goal:** Allow AI to compare working vs. broken states to pinpoint issues caused by updates or changes.

### 4.1 Automated Snapshots
- **Component:** `core/class-wp-ai-context-snapshot.php`
- **Functionality:**
  - Hook into WordPress core/plugin update actions (`upgrader_process_complete`).
  - Generate and save a silent Context Package to a protected directory or custom database table before the update occurs.
  - Store packages with timestamps.

### 4.2 Context Diff Exporter
- **Component:** `exporters/class-wp-ai-context-diff-exporter.php`
- **Functionality:**
  - Allow users to select a "Broken" state (current) and a "Working" state (historical).
  - Generate a unified diff of the two context packages to show exactly what changed (e.g., "Plugin X updated from 1.2 to 1.3", "Option Y changed from true to false").

---

## Phase 5: "Fix It For Me" (Two-Way MCP Communication)
**Goal:** Allow the AI to suggest and execute safe actions on the WordPress site.

### 5.1 Action API Endpoint
- **Component:** `api/class-wp-ai-context-action-api.php`
- **Functionality:**
  - Create a secure, authenticated REST API endpoint to receive actionable commands (e.g., `deactivate_plugin`, `update_option`).
  - **Crucial:** These actions are placed into a "Pending Actions" queue in the database, NOT executed immediately.

### 5.2 Action Approval UI
- **Component:** `admin/class-wp-ai-context-action-ui.php`
- **Functionality:**
  - Display pending actions in the WP Admin (e.g., "Claude suggests deactivating WooCommerce to test conflicts.").
  - Provide an "Approve & Execute" button for the admin user.

---

## Phase 6: Multi-modal / Visual Context
**Goal:** Provide AI with visual representation of the site.

### 6.1 Screenshot Integrator
- **Component:** `collectors/class-wp-ai-context-screenshot.php`
- **Functionality:**
  - Integrate with a free/low-cost screenshot API (e.g., WordPress.com mshots API or similar).
  - Capture the homepage or a specific URL provided by the user.
  - Append the image URL (or base64 encoded image, depending on token limits) to the context package.

-add palaywright to make things like application password
-make a kind of staging site so that the user won't have to have the problem of site down