NODE_AVAILABLE := $(shell command -v node 2> /dev/null)

build_tools:
	docker compose -p cfbmarblegame-tools -f docker-compose.tools.yml build

hadolint:
	docker run --rm -i -v $(shell pwd)/.hadolint.yaml:/.config/hadolint.yaml hadolint/hadolint < $(shell pwd)/docker/app/Dockerfile

phpcbf:
	docker compose -p cfbmarblegame-tools -f docker-compose.tools.yml run --rm phpcbf

phpcs:
	docker compose -p cfbmarblegame-tools -f docker-compose.tools.yml run --rm phpcs

phpstan:
	docker compose -p cfbmarblegame-tools -f docker-compose.tools.yml run --rm phpstan

quality: hadolint phpcbf phpcs phpstan

phpunit:
	docker compose -p cfbmarblegame-tools -f docker-compose.tools.yml run --rm phpunit

playwright-install:
	touch playwright/.env
ifdef NODE_AVAILABLE
	cd playwright && npm ci
else
	docker compose -p cfbmarblegame-tools -f docker-compose.tools.yml run --service-ports --rm playwright npm --no-update-notifier ci
endif

playwright-test:
ifdef NODE_AVAILABLE
	cd playwright && PW_BASE_URL=http://localhost:9000 npx playwright test
else
	docker compose -p cfbmarblegame-tools -f docker-compose.tools.yml run --service-ports --rm playwright npx --no-update-notifier playwright test
endif

playwright-report:
ifdef NODE_AVAILABLE
	cd playwright && npx playwright show-report
else
	docker compose -p cfbmarblegame-tools -f docker-compose.tools.yml run --service-ports --rm playwright npx --no-update-notifier playwright show-report --host 0.0.0.0
endif
