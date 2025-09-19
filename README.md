# WP Verify Twilio 

A WordPress plugin that integrates [Twilio Verify](https://www.twilio.com/verify) with your site to provide phone/email OTP verification via REST API and shortcodes.

## Features

- 📱 **Phone verification** via Twilio Verify (SMS).
- 📧 **Email verification** via Twilio Verify.
- 🔐 Stores verification status in a custom database table.
- 📝 WordPress Admin Settings page to configure Twilio credentials:
  - Account SID
  - Auth Token
  - Verify Service SID
- 🧩 Provides a shortcode `[wpvts_controls]` to render verification UI controls.
- ⚡ REST API endpoints for integration with custom workflows.
- 🔍 Logs events (HTTP calls, verification attempts, database writes) for debugging.

## Installation

1. Upload the plugin folder to your WordPress installation under `/wp-content/plugins/wp-verify-twilio-static/`.
2. Activate the plugin in the **Plugins** menu in WordPress.
3. Navigate to **Settings > WP Verify Twilio** and enter your Twilio credentials.
