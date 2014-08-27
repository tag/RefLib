VENDOR_DIR = ./vendor
BIN_DIR = $(VENDOR_DIR)/bin
PHPCS = $(BIN_DIR)/phpcs
PHPCS_STANDARD = PSR2
PHPMD = $(BIN_DIR)/phpmd
IGNORE = vendor,tests

test:
	php -f tests/fix-date.php
	php -f tests/endnotexml.php
	
phpcs: .check-installation
	@echo "-------------- BEGIN phpcs ------------------"
	$(PHPCS) --standard=$(PHPCS_STANDARD) --ignore=$(IGNORE) .
	@echo "-------------- END phpcs ------------------"

phpmd: .check-installation
	@echo "-------------- BEGIN phpmd ------------------"
	$(PHPMD) . text codesize,unusedcode,naming,design --exclude $(IGNORE)
	@echo "-------------- END phpmd ------------------"

install: clean .check-composer
	@echo "Executing a composer installation of development dependencies.."
	$(COMPOSER) install --dev

clean:
	@echo "Removing Composer..."
	rm -f composer.phar
	rm -f composer.lock
	rm -rf $(VENDOR_DIR)

.check-composer:
	@echo "Checking if Composer is installed..."
	@test -f composer.phar || curl -s http://getcomposer.org/installer | php;

.check-installation: .check-composer
	@echo "Checking for vendor directory..."
	@test -d $(VENDOR_DIR) || make install
	@echo "Checking for bin directory..."
	@test -d $(BIN_DIR) || make install
