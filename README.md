# OpenRouter Provider for WordPress

A WordPress plugin that registers [OpenRouter](https://openrouter.ai) as an AI provider for the WordPress AI Client. OpenRouter is an OpenAI-compatible API gateway offering hundreds of models from multiple providers through a single API key.

## Requirements

- WordPress 7.0 or higher
- PHP 7.4 or higher
- [AI Experiments](https://wordpress.org/plugins/ai/) plugin (for running experiments through the WordPress admin)

## Installation

Clone this repository into your `wp-content/plugins/` directory:

```bash
git clone https://github.com/coenjacobs/wordpress-openrouter-provider.git wp-content/plugins/openrouter-provider
cd wp-content/plugins/openrouter-provider/plugin
composer install
```

Activate the plugin through the WordPress admin panel or WP-CLI:

```bash
wp plugin activate openrouter-provider
```

## Configuration

### API Key

The API key can be configured in three ways (in order of precedence):

1. **Environment variable**: Set `OPENROUTER_API_KEY` in your environment
2. **PHP constant**: Define `OPENROUTER_API_KEY` in `wp-config.php`
3. **Settings page**: Enter it at **Settings > OpenRouter** in the WordPress admin

### Model Selection

Visit **Settings > OpenRouter** to enable specific models. The settings page displays all available models grouped by provider (e.g. Anthropic, Google, OpenAI, Meta). Only enabled models are exposed to the WordPress AI Client and available for use in AI Experiments.

A **Free/Paid filter** dropdown lets you narrow the list to free or paid models. Use the **Refresh Model List** button to update the available models from the OpenRouter API.

## How It Works

The plugin registers a single provider (`openrouter`) with the WordPress AI Client registry on the `init` hook. All OpenRouter models use the OpenAI-compatible `/chat/completions` endpoint, so only a single model class is needed.

Model IDs follow the format `provider/model-name` (e.g. `openai/gpt-4o`, `anthropic/claude-3.5-sonnet`). The settings page groups models by this provider prefix.

## Development Environment

The project includes a Docker-based development environment. No PHP, Composer, or other tools are needed on the host machine.

### Quick Start

```bash
make build    # Build the Docker image
make setup    # Full setup: download WordPress, configure, install, activate plugin
```

This gives you a working WordPress 7.0-beta2 installation at **http://localhost:8081** (admin/admin) with the plugin activated.

### Makefile Targets

| Target | Purpose |
|--------|---------|
| `make build` | Build the Docker image |
| `make setup` | Full clean setup: download WordPress, configure, install, activate plugin |
| `make up` / `make down` | Start/stop containers |
| `make clean-wp` | Stop containers and wipe the WordPress directory |
| `make composer` | Run `composer install` for the plugin |
| `make activate` | Activate the plugin via WP-CLI |

### Docker Stack

- **PHP**: 8.5 CLI Alpine with built-in web server
- **Database**: MariaDB 11
- **WordPress**: 7.0-beta2 (downloaded via `curl` + `tar`)

### Volume Mounts

- `./wordpress/` → `/var/www/html` — WordPress root (gitignored)
- `./plugin/` → `/var/www/html/wp-content/plugins/openrouter-provider` — plugin source
- `./docker/mariadb/data/` → `/var/lib/mysql` — database storage (gitignored)
