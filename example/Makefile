.PHONY: build
build: # Generates a installable zip package for the plugin
	# Reset build
	rm -rf ./build
	mkdir ./build

	# Copy current version of the plugin
	cp -R ./plugin-foo ./build/plugin-foo

	docker run --rm \
		--user $(DOCKER_USER) \
		-v "${PWD}:/app" \
		joshkeegan/zip:latest \
		sh -c "cd /app/build && zip -r plugin.zip plugin-foo"

deploy_prod:
	$(MAKE) build
	@echo -n "Do you want to deploy to Prod the build that was just created? [y/N] " && read ans && [ $${ans:-N} = y ]
	docker run --rm \
		--user $(DOCKER_USER) \
		--network="host" \
		-v "${PWD}:/app" \
		php:7-cli \
		bash -c "php -d memory_limit=1G /app/deploy/upload.php"