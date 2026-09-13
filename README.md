# WP AI Context

**WP AI Context** is a WordPress plugin that exports your site's configuration into an AI-friendly format. When you need help from ChatGPT, Claude, or Gemini regarding your WordPress setup, this plugin gives the AI the exact context it needs—reducing hallucinations and avoiding the need for dozens of screenshots.

## The Problem
When asking an AI for help with a WordPress plugin, the AI often lacks context:
- What version of the plugin are you using?
- What settings are enabled or disabled?
- What is your server environment?

## The Solution
WP AI Context generates a structured "Context Package" (JSON or Markdown) that you can paste directly into your AI prompt. It includes:
- WordPress and PHP environment details
- Active plugins and themes
- Structured settings for popular plugins
- **Redacted secrets** (API keys, passwords, and tokens are safely masked)

## Installation
*(Coming soon)*

## Architecture
This plugin is built with extensibility in mind. It uses:
- **Collectors**: To gather environment and system data.
- **Adapters**: To extract settings from specific plugins.
- **Privacy Filters**: To ensure no sensitive data is exported.
- **Exporters**: To format the data for AI consumption.
