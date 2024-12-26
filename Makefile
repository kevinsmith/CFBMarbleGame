hadolint:
	docker run --rm -i -v $(shell pwd)/.hadolint.yaml:/.config/hadolint.yaml hadolint/hadolint < $(shell pwd)/docker/app/Dockerfile
