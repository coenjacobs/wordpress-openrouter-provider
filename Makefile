.PHONY: up down build setup clean-wp download-wp configure-wp install-wp install-ai-plugin composer mozart activate test test-lint test-phpstan test-phpmd test-docs

DOCKER_EXEC = docker compose exec -T wordpress
WP = $(DOCKER_EXEC) wp --allow-root
WP_VERSION = 7.0-beta5

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

setup: clean-wp download-wp configure-wp install-wp install-ai-plugin composer mozart activate

clean-wp:
	docker compose down
	docker run --rm -v "$(CURDIR)/wordpress:/var/www/html" alpine sh -c 'rm -rf /var/www/html/*'
	rm -rf wordpress
	mkdir -p wordpress

download-wp:
	docker compose up -d
	$(DOCKER_EXEC) sh -c '\
		curl -sL https://wordpress.org/wordpress-$(WP_VERSION).tar.gz -o /tmp/wp.tar.gz \
		&& mkdir -p /tmp/wp \
		&& tar xzf /tmp/wp.tar.gz -C /tmp/wp --strip-components=1 \
		&& cp -a /tmp/wp/. /var/www/html/ \
		&& rm -rf /tmp/wp /tmp/wp.tar.gz'
	docker compose down
	docker compose up -d

configure-wp:
	$(WP) config create \
		--dbname=wordpress \
		--dbuser=wordpress \
		--dbpass=wordpress \
		--dbhost=db

install-wp:
	$(WP) core install \
		--url="http://localhost:8081" \
		--title="OpenRouter Provider Dev" \
		--admin_user=admin \
		--admin_password=admin \
		--admin_email=admin@example.com

install-ai-plugin:
	$(WP) plugin install ai

composer:
	$(DOCKER_EXEC) sh -c "cd /var/www/html/wp-content/plugins/openrouter-provider && composer install --no-interaction"

mozart:
	cp -r vendor/coenjacobs/wordpress-ai-provider/assets/. assets
	docker run --rm -v "$(CURDIR):/project" coenjacobs/mozart:1.2.1 /mozart/bin/mozart compose
	$(DOCKER_EXEC) sh -c "cd /var/www/html/wp-content/plugins/openrouter-provider && composer dump-autoload"

activate:
	$(WP) plugin activate openrouter-provider

test:
	$(DOCKER_EXEC) sh -c "cd /var/www/html/wp-content/plugins/openrouter-provider && composer test"

test-lint:
	$(DOCKER_EXEC) sh -c "cd /var/www/html/wp-content/plugins/openrouter-provider && composer test:lint"

test-phpstan:
	$(DOCKER_EXEC) sh -c "cd /var/www/html/wp-content/plugins/openrouter-provider && composer test:phpstan"

test-phpmd:
	$(DOCKER_EXEC) sh -c "cd /var/www/html/wp-content/plugins/openrouter-provider && composer test:phpmd"

test-docs:
	$(DOCKER_EXEC) sh -c "cd /var/www/html/wp-content/plugins/openrouter-provider && composer test:docs"
