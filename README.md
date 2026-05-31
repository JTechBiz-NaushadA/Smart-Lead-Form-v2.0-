# TX Smart Lead Forms (v2.0)

> A complete lead capture and email delivery solution for WordPress.

![Version](https://img.shields.io/badge/version-2.0-blue)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)
![License](https://img.shields.io/badge/license-MIT-green)

## Plugin Information

| Item               | Value                               |
| ------------------ | ----------------------------------- |
| Contributors       | Naushad A.                          |
| Version            | 2.0                                 |
| Requires WordPress | 5.0+                                |
| Tested Up To       | 6.8.3                               |
| Requires PHP       | 7.4+                                |
| License            | MIT License                         |
| License URL        | https://opensource.org/licenses/MIT |

---

## Description

**TX Smart Lead Forms** is a complete lead capture and email delivery solution for WordPress.

Create unlimited custom lead forms, collect and manage leads, send automated HTML emails, configure SMTP settings, and stay GDPR compliant with built-in unsubscribe and re-subscribe functionality.

Designed for marketers, agencies, and developers who need a lightweight lead generation system without relying on third-party SaaS platforms.

---

## Features

### Unlimited Form Builder

Create and manage multiple lead capture forms directly from the WordPress admin panel.

### Custom Form Fields

Enable or disable optional fields including:

* Organisation
* Role
* Country
* Areas of Interest

### Interest Selection Chips

Create custom interest options that visitors can select during form submission.

### Shortcode Support

Embed any form anywhere using:

```text
[tx_form form="your-form-key"]
```

### Form-Specific Email Settings

Configure sender details, subject lines, preview text, and email content individually for each form.

### Custom HTML Email Templates

Build fully customized HTML email templates with dynamic placeholders.

Available placeholders:

```text
{{name}}
{{email}}
{{unsubscribe}}
```

### Automated Email Delivery

Automatically send emails immediately after successful form submission.

### Global SMTP Configuration

Configure SMTP settings once and use them across all forms for reliable email delivery.

### Email Template Preview

Preview email templates directly from the WordPress admin panel.

### Send Test Emails

Verify email templates and SMTP settings before going live.

### Lead Management Dashboard

View and manage all captured leads from a centralized interface.

### Form-Based Lead Filtering

Filter leads by specific forms for easier management and reporting.

### CSV Export

Export:

* All leads
* Leads from specific forms

### Duplicate Lead Protection

Prevents duplicate submissions for the same email address and form combination.

### GDPR-Compliant Unsubscribe

Automatically generates secure unsubscribe links for every email.

### One-Click Re-Subscribe

Allows previously unsubscribed users to re-subscribe using a secure tokenized link.

### Secure Token-Based Links

Uses unique tokens for secure unsubscribe and re-subscribe actions.

### AJAX Form Submission

Provides a seamless user experience without page reloads.

---

## Security & WordPress Standards

Built following WordPress coding and security standards.

Includes:

* Nonce verification
* Capability checks
* Data sanitization
* Data escaping
* Prepared SQL queries

---

## Responsive Design

Modern responsive form layouts optimized for:

* Desktop
* Tablet
* Mobile devices

---

## Installation

1. Upload the plugin to `/wp-content/plugins/`
2. Activate the plugin from the WordPress Plugins screen
3. Navigate to **TX Leads → Forms**
4. Create your first lead form
5. Configure email settings and SMTP
6. Add the shortcode to any page or post
7. Start collecting leads

---

## How It Works

1. Install and activate the plugin.
2. Create a new form from **TX Leads → Forms**.
3. Configure form fields and interest options.
4. Create your email template.
5. Configure SMTP settings if required.
6. Insert the shortcode into any page or post.
7. Start collecting leads automatically.

---

## Shortcode

Display a form:

```text
[tx_form form="your-form-key"]
```

Example:

```text
[tx_form form="ebook-download"]
```

---

## Email Template Variables

Use the following placeholders inside email templates:

| Placeholder       | Description             |
| ----------------- | ----------------------- |
| `{{name}}`        | Recipient full name     |
| `{{email}}`       | Recipient email address |
| `{{unsubscribe}}` | Secure unsubscribe URL  |

---

## Data Storage

All data remains inside your WordPress database.

Stored data includes:

* Lead information
* Form configurations
* Email settings
* SMTP settings
* Unsubscribe preferences

---

## Changelog

### v2.0

* Added multi-form management system
* Added form-specific email settings
* Added custom form builder
* Added interest selection fields
* Added SMTP configuration panel
* Added email template preview
* Added test email functionality
* Added CSV export support
* Added lead filtering by form
* Added duplicate lead prevention
* Added secure unsubscribe system
* Added one-click re-subscribe workflow
* Improved security with nonce validation and capability checks
* Improved data sanitization and escaping
* Enhanced admin interface

### v1.0.1

Repository:
https://github.com/JTechBiz-NaushadA/Smart-Lead-Form

* Major update with improved architecture
* Added custom HTML email template support
* Added shortcode-based form rendering
* Added GDPR-compliant unsubscribe system
* Improved email automation workflow

---

## Frequently Asked Questions

### Can I create multiple forms?

Yes. You can create unlimited forms and assign unique email templates to each form.

### Can I use my own SMTP server?

Yes. The plugin includes a global SMTP configuration section.

### Can I export leads?

Yes. Leads can be exported as CSV files from the Leads dashboard.

### Is the plugin GDPR compliant?

Yes. Every email includes support for secure unsubscribe links, and users can unsubscribe with a single click.

### Can I customize email templates?

Yes. Full HTML email templates are supported directly from the admin panel.

---

## Contributing

Contributions are welcome.

### Ways to Contribute

1. Open an Issue
2. Submit a Pull Request
3. Suggest New Features
4. Report Bugs
5. Share Improvements

---

## License

Released under the MIT License.

https://opensource.org/licenses/MIT

---

**Made with ❤️ for WordPress marketers, agencies, and developers.**
