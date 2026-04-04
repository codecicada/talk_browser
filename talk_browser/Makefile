APP_ID     = talk_browser
CONTAINER  = nextcloud_app
APP_DIR    = /apps-dev/$(APP_ID)
PHAR       = translationtool.phar
TRANS_DIR  = translationfiles

.PHONY: help l10n-extract l10n-compile

help:
	@echo "Available targets:"
	@echo "  l10n-extract   Extract translatable strings into translationfiles/templates/$(APP_ID).pot"
	@echo "  l10n-compile   Compile .po files in translationfiles/<lang>/ into l10n/<lang>.json and l10n/<lang>.js"

l10n-extract: $(PHAR)
	@echo "Copying $(PHAR) to container $(CONTAINER):$(APP_DIR)/$(PHAR) ..."
	docker cp $(PHAR) $(CONTAINER):$(APP_DIR)/$(PHAR)
	@echo "Running string extraction inside container ..."
	docker exec -u 0 $(CONTAINER) php $(APP_DIR)/$(PHAR) create-pot-files
	@echo "Copying extracted template back to host ..."
	docker cp $(CONTAINER):$(APP_DIR)/translationfiles/. $(TRANS_DIR)/
	@echo "Extraction complete. Template written to $(TRANS_DIR)/templates/$(APP_ID).pot"

l10n-compile: $(PHAR)
	@echo "Copying $(PHAR) to container $(CONTAINER):$(APP_DIR)/$(PHAR) ..."
	docker cp $(PHAR) $(CONTAINER):$(APP_DIR)/$(PHAR)
	@echo "Copying .po files to container ..."
	docker cp $(TRANS_DIR)/. $(CONTAINER):$(APP_DIR)/translationfiles/
	@echo "Compiling translations inside container ..."
	docker exec -u 0 $(CONTAINER) php $(APP_DIR)/$(PHAR) convert-po-files
	@echo "Copying compiled l10n files back to host ..."
	docker cp $(CONTAINER):$(APP_DIR)/l10n/. l10n/
	@echo "Compilation complete. Compiled files written to l10n/"
