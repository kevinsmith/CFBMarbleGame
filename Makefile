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
