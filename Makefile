PHP_CONTAINER := $(shell docker compose ps -q web)
PHP_RUN := docker exec -it $(PHP_CONTAINER)
COMPOSE_RUN := docker compose run --rm -T

console:
	$(PHP_RUN) ./console $(command)

hadolint:
	docker run --rm -i -v $(shell pwd)/.hadolint.yaml:/.hadolint.yaml hadolint/hadolint < $(shell pwd)/docker/app/Dockerfile

phpcbf:
	$(COMPOSE_RUN) --entrypoint vendor/bin/phpcbf web

phpcs:
	$(COMPOSE_RUN) --entrypoint vendor/bin/phpcs web

phpstan:
	$(COMPOSE_RUN) --entrypoint vendor/bin/phpstan web

quality: hadolint phpcbf phpcs phpstan

tests:
	$(COMPOSE_RUN) --entrypoint vendor/bin/phpunit web

tests-unit:
	$(COMPOSE_RUN) --entrypoint vendor/bin/phpunit web --exclude-group integration

playwright-install:
	cd playwright && npm ci

playwright-tests:
	cd playwright && PW_BASE_URL=https://cfbmarblegame.test npx playwright test

playwright-report:
	cd playwright && npx playwright show-report

migrate:
	$(PHP_RUN) composer phinx migrate

rollback:
	$(PHP_RUN) composer phinx rollback

migration:
	$(PHP_RUN) composer phinx create $(name)
