# OpenRouter Provider for WordPress

A WordPress plugin that registers [OpenRouter](https://openrouter.ai) as an AI provider for the WordPress AI Client. OpenRouter is an OpenAI-compatible API gateway offering hundreds of models from multiple providers through a single API key.

## Requirements

- WordPress 7.0 or higher
- PHP 7.4 or higher
- [AI Experiments](https://wordpress.org/plugins/ai/) plugin (for running experiments through the WordPress admin)

## Installation

Download the latest release zip from the [releases page](https://github.com/coenjacobs/wordpress-openrouter-provider/releases) and install it through **Plugins > Add New > Upload Plugin** in the WordPress admin.

Alternatively, install via WP-CLI:

```bash
wp plugin install path/to/openrouter-provider.zip --activate
```

## Configuration

### API Key

Configure your OpenRouter API key on the **Settings > Connectors** screen in the WordPress admin. The OpenRouter connector appears automatically when the plugin is activated.

The API key can also be provided via environment variable or PHP constant:

1. **Environment variable**: Set `OPENROUTER_API_KEY` in your environment
2. **PHP constant**: Define `OPENROUTER_API_KEY` in `wp-config.php`

### Model Selection

Visit **Settings > OpenRouter** to enable specific models. The settings page displays all available models grouped by provider (e.g. Anthropic, Google, OpenAI, Meta). Only enabled models are exposed to the WordPress AI Client and available for use in AI Experiments.

A **Free/Paid filter** dropdown lets you narrow the list to free or paid models. Use the **Refresh Model List** button to update the available models from the OpenRouter API.

## How It Works

The plugin registers a single provider (`openrouter`) with the WordPress AI Client registry on the `init` hook. The WordPress Connectors system automatically discovers the provider and handles API key storage and validation.

All OpenRouter models use the OpenAI-compatible `/chat/completions` endpoint. Model IDs follow the format `provider/model-name` (e.g. `openai/gpt-4o`, `anthropic/claude-3.5-sonnet`). The settings page groups models by this provider prefix.

## Updates

The plugin checks for updates automatically via its integrated update mechanism. Update notifications appear on the WordPress Plugins page just like any other plugin.

## Development Environment

The project includes a Docker-based development environment. No PHP, Composer, or other tools are needed on the host machine.

### Quick Start

```bash
make build    # Build the Docker image
make setup    # Full setup: download WordPress, configure, install, activate plugin
```

This gives you a working WordPress installation at **http://localhost:8081** (admin/admin) with the plugin activated.

### Makefile Targets

| Target | Purpose |
|--------|---------|
| `make build` | Build the Docker image |
| `make setup` | Full clean setup: download WordPress, configure, install, activate plugin |
| `make up` / `make down` | Start/stop containers |
| `make clean-wp` | Stop containers and wipe the WordPress directory |
| `make composer` | Run `composer install` for the plugin |
| `make mozart` | Bundle shared package dependencies via Mozart |
| `make activate` | Activate the plugin via WP-CLI |
| `make test` | Run all QA checks (lint, PHPStan, PHPMD, docs) |

### Docker Stack

- **PHP**: 8.5 CLI Alpine with built-in web server
- **Database**: MariaDB 11
- **WordPress**: 7.0-RC1 (downloaded via `curl` + `tar`)

### Volume Mounts

- `./wordpress/` → `/var/www/html` — WordPress root (gitignored)
- `./` → `/var/www/html/wp-content/plugins/openrouter-provider` — plugin source
- `./docker/mariadb/data/` → `/var/lib/mysql` — database storage (gitignored)
